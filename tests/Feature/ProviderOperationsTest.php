<?php

namespace Tests\Feature;

use App\Filament\Provider\Resources\SupplyDeliveryResource;
use App\Models\Account;
use App\Models\Inspection;
use App\Models\MaintenanceTicket;
use App\Models\Property;
use App\Models\PropertyOwner;
use App\Models\ProviderCompany;
use App\Models\ProviderCompanyMembership;
use App\Models\ProviderInvoice;
use App\Models\Quotation;
use App\Models\ServiceAppointment;
use App\Models\ServiceRequest;
use App\Models\SupplyDelivery;
use App\Models\Unit;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderActivity;
use App\Models\WorkOrderCompletionSubmission;
use App\Models\WorkOrderEvidence;
use App\Services\ProviderInvoiceService;
use App\Services\ServiceAppointmentService;
use App\Services\SupplyDeliveryService;
use App\Services\WorkOrderAssignmentService;
use App\Services\WorkOrderCompletionService;
use App\Services\WorkOrderService;
use App\Support\CurrentAccount;
use App\Support\CurrentProviderCompany;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

class ProviderOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_assignments_enforce_company_active_membership_and_request_role(): void
    {
        $context = $this->operation();
        $technician = $this->member($context['provider'], 'technician');
        $assignment = app(WorkOrderAssignmentService::class)->assign(
            $context['work'], $technician, 'technician', $context['providerOwner'], true,
        );

        $this->assertSame($technician->id, $assignment->provider_company_membership_id);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $technician->user_id]);

        $foreign = $this->provider('Foreign Provider');
        $this->assertValidationError(fn () => app(WorkOrderAssignmentService::class)->assign(
            $context['work'], $foreign['ownerMembership'], 'technician', $context['providerOwner'],
        ), 'membership');

        $inactive = $this->member($context['provider'], 'technician', false);
        $this->assertValidationError(fn () => app(WorkOrderAssignmentService::class)->assign(
            $context['work'], $inactive, 'technician', $context['providerOwner'],
        ), 'membership');

        $accountant = $this->member($context['provider'], 'accountant');
        $this->assertValidationError(fn () => app(WorkOrderAssignmentService::class)->assign(
            $context['work'], $accountant, 'coordinator', $context['providerOwner'],
        ), 'membership');

        $this->expectException(AuthorizationException::class);
        app(WorkOrderAssignmentService::class)->assign($context['work'], $technician, 'technician', $context['accountOwner']);
    }

    public function test_inspector_assignment_works_only_for_inspection_work(): void
    {
        $context = $this->operation(ServiceRequest::TYPE_INSPECTION);
        $inspector = $this->member($context['provider'], 'inspector');

        $assignment = app(WorkOrderAssignmentService::class)->assign(
            $context['work'], $inspector, 'inspector', $context['providerOwner'],
        );

        $this->assertSame('inspector', $assignment->assignment_type);
    }

    public function test_appointments_are_customer_confirmed_and_rescheduling_preserves_history(): void
    {
        $context = $this->operation();
        $appointment = app(ServiceAppointmentService::class)->propose($context['work'], [
            'scheduled_start' => now()->addDay(), 'scheduled_end' => now()->addDay()->addHour(),
        ], $context['providerOwner']);
        $this->assertSame(ServiceAppointment::STATUS_PROPOSED, $appointment->status);

        $this->expectAuthorization(fn () => app(ServiceAppointmentService::class)->confirm($appointment, $context['providerOwner']));
        $confirmed = app(ServiceAppointmentService::class)->confirm($appointment, $context['accountOwner']);
        $this->assertSame(ServiceAppointment::STATUS_CONFIRMED, $confirmed->status);
        $this->assertSame(WorkOrder::STATUS_SCHEDULED, $context['work']->fresh()->status);

        $manager = $this->accountMember($context['account'], Account::ROLE_PROPERTY_MANAGER);
        $oldId = $confirmed->id;
        app(ServiceAppointmentService::class)->requestReschedule($confirmed, $manager, 'Please use the afternoon.');
        $reschedulingWork = $context['work']->fresh();
        $this->assertNull($reschedulingWork->scheduled_start);
        $this->assertNull($reschedulingWork->scheduled_completion);
        $replacement = app(ServiceAppointmentService::class)->propose($context['work']->fresh(), [
            'scheduled_start' => now()->addDays(2), 'scheduled_end' => now()->addDays(2)->addHour(),
        ], $context['providerOwner']);
        $this->assertNotSame($oldId, $replacement->id);
        $this->assertSame(ServiceAppointment::STATUS_RESCHEDULE_REQUESTED, ServiceAppointment::withoutGlobalScopes()->find($oldId)->status);

        $viewer = $this->accountMember($context['account'], Account::ROLE_VIEWER);
        $this->expectAuthorization(fn () => app(ServiceAppointmentService::class)->confirm($replacement, $viewer));
        $replacement = app(ServiceAppointmentService::class)->confirm($replacement, $manager);
        $this->assertEquals($replacement->scheduled_start, $context['work']->fresh()->scheduled_start);
        $this->assertEquals($replacement->scheduled_end, $context['work']->fresh()->scheduled_completion);
    }

    public function test_delivery_resource_is_assignment_scoped_for_operational_staff(): void
    {
        $context = $this->operation(ServiceRequest::TYPE_PRODUCT_SUPPLY);
        $assignedSales = $this->member($context['provider'], 'sales');
        $unassignedSales = $this->member($context['provider'], 'sales');
        $unassignedTechnician = $this->member($context['provider'], 'technician');
        $administrator = $this->member($context['provider'], 'administrator');
        app(WorkOrderAssignmentService::class)->assign(
            $context['work'], $assignedSales, 'delivery', $context['providerOwner'],
        );
        $delivery = app(SupplyDeliveryService::class)->create(
            $context['work'], ['delivery_reference' => 'PRIVATE-DELIVERY'], $assignedSales->user,
        );

        $this->useProvider($assignedSales->user, $context['provider']);
        $this->assertSame([$delivery->id], SupplyDeliveryResource::getEloquentQuery()->pluck('id')->all());
        $this->useProvider($unassignedSales->user, $context['provider']);
        $this->assertSame([], SupplyDeliveryResource::getEloquentQuery()->pluck('id')->all());
        $this->useProvider($unassignedTechnician->user, $context['provider']);
        $this->assertSame([], SupplyDeliveryResource::getEloquentQuery()->pluck('id')->all());
        $this->useProvider($administrator->user, $context['provider']);
        $this->assertSame([$delivery->id], SupplyDeliveryResource::getEloquentQuery()->pluck('id')->all());
        $this->useProvider($context['providerOwner'], $context['provider']);
        $this->assertSame([$delivery->id], SupplyDeliveryResource::getEloquentQuery()->pluck('id')->all());

        $foreign = $this->provider('Foreign visibility provider');
        $this->useProvider($foreign['owner'], $foreign['company']);
        $this->assertSame([], SupplyDeliveryResource::getEloquentQuery()->pluck('id')->all());
    }

    public function test_assigned_staff_least_privilege_and_provider_suspension(): void
    {
        $context = $this->operation();
        $assigned = $this->member($context['provider'], 'technician');
        $unassigned = $this->member($context['provider'], 'technician');
        $accountant = $this->member($context['provider'], 'accountant');
        app(WorkOrderAssignmentService::class)->assign($context['work'], $assigned, 'technician', $context['providerOwner']);

        $this->expectAuthorization(fn () => app(WorkOrderService::class)->start($context['work'], $unassigned->user));
        $this->expectAuthorization(fn () => app(WorkOrderService::class)->start($context['work'], $accountant->user));
        $started = app(WorkOrderService::class)->start($context['work'], $assigned->user);
        $this->assertSame(WorkOrder::STATUS_IN_PROGRESS, $started->status);

        $other = $this->operation();
        $other['provider']->forceFill(['status' => ProviderCompany::STATUS_SUSPENDED])->save();
        $this->assertValidationError(fn () => app(WorkOrderService::class)->start($other['work'], $other['providerOwner']), 'provider');
    }

    public function test_completion_requires_customer_review_and_preserves_revisions(): void
    {
        $context = $this->operation();
        app(WorkOrderService::class)->start($context['work'], $context['providerOwner']);
        $this->assertValidationError(fn () => app(WorkOrderService::class)->transition(
            $context['work']->fresh(), WorkOrder::STATUS_COMPLETED, [], $context['providerOwner'],
        ), 'completion');

        $first = app(WorkOrderCompletionService::class)->submit(
            $context['work']->fresh(), 'Initial repair', null,
            [['evidence_type' => 'after_photo', 'file_path' => 'private/work-orders/after-one.jpg']], $context['providerOwner'],
        );
        $this->assertSame(WorkOrder::STATUS_COMPLETION_SUBMITTED, $context['work']->fresh()->status);
        $this->expectAuthorization(fn () => app(WorkOrderCompletionService::class)->accept(
            $context['work']->fresh(), $first, $context['providerOwner'],
        ));
        $viewer = $this->accountMember($context['account'], Account::ROLE_VIEWER);
        $this->expectAuthorization(fn () => app(WorkOrderCompletionService::class)->accept(
            $context['work']->fresh(), $first, $viewer,
        ));
        $this->assertValidationError(fn () => app(WorkOrderCompletionService::class)->requestRevision(
            $context['work']->fresh(), $first, $context['accountOwner'], '',
        ), 'review_notes');

        $manager = $this->accountMember($context['account'], Account::ROLE_PROPERTY_MANAGER);
        app(WorkOrderCompletionService::class)->requestRevision($context['work']->fresh(), $first, $manager, 'Replace the damaged fitting.');
        app(WorkOrderService::class)->start($context['work']->fresh(), $context['providerOwner']);
        $second = app(WorkOrderCompletionService::class)->submit(
            $context['work']->fresh(), 'Corrected repair', null,
            [['evidence_type' => 'after_photo', 'file_path' => 'private/work-orders/after-two.jpg']], $context['providerOwner'],
        );
        $completed = app(WorkOrderCompletionService::class)->accept($context['work']->fresh(), $second, $manager);

        $this->assertSame(WorkOrder::STATUS_COMPLETED, $completed->status);
        $this->assertSame($second->id, $completed->accepted_completion_submission_id);
        $this->assertSame(WorkOrderCompletionSubmission::STATUS_REVISION_REQUESTED, $first->fresh()->status);
        $this->assertSame(2, $completed->completionSubmissions()->count());
    }

    public function test_completion_review_blocks_provider_cancellation_and_remains_reviewable(): void
    {
        $context = $this->operation();
        app(WorkOrderService::class)->start($context['work'], $context['providerOwner']);
        $submission = app(WorkOrderCompletionService::class)->submit(
            $context['work']->fresh(), 'Awaiting customer review', null,
            [['evidence_type' => 'other', 'text_value' => 'complete']], $context['providerOwner'],
        );

        $this->assertValidationError(fn () => app(WorkOrderService::class)->transition(
            $context['work']->fresh(), WorkOrder::STATUS_CANCELLED, [], $context['providerOwner'],
        ), 'status');
        $this->assertSame(WorkOrder::STATUS_COMPLETION_SUBMITTED, $context['work']->fresh()->status);
        $completed = app(WorkOrderCompletionService::class)->accept(
            $context['work']->fresh(), $submission, $context['accountOwner'],
        );
        $this->assertSame(WorkOrder::STATUS_COMPLETED, $completed->status);
    }

    public function test_terminal_work_orders_reject_assignments_and_delivery_mutation(): void
    {
        foreach ([
            WorkOrder::STATUS_COMPLETION_SUBMITTED,
            WorkOrder::STATUS_COMPLETED,
            WorkOrder::STATUS_CANCELLED,
        ] as $status) {
            $context = $this->operation(ServiceRequest::TYPE_PRODUCT_SUPPLY);
            $sales = $this->member($context['provider'], 'sales');
            $context['work']->forceFill(['status' => $status])->save();
            $this->assertValidationError(fn () => app(WorkOrderAssignmentService::class)->assign(
                $context['work']->fresh(), $sales, 'delivery', $context['providerOwner'],
            ), 'work_order');
            $this->assertValidationError(fn () => app(SupplyDeliveryService::class)->create(
                $context['work']->fresh(), [], $context['providerOwner'],
            ), 'work_order');
        }

        $context = $this->operation(ServiceRequest::TYPE_PRODUCT_SUPPLY);
        $delivery = app(SupplyDeliveryService::class)->create($context['work'], [], $context['providerOwner']);
        $context['work']->forceFill(['status' => WorkOrder::STATUS_COMPLETED])->save();
        $this->assertValidationError(fn () => app(SupplyDeliveryService::class)->transition(
            $delivery, SupplyDelivery::STATUS_READY, [], $context['providerOwner'],
        ), 'work_order');
    }

    public function test_assignment_type_must_match_request_semantics(): void
    {
        $context = $this->operation();
        $technician = $this->member($context['provider'], 'technician');

        $this->assertValidationError(fn () => app(WorkOrderAssignmentService::class)->assign(
            $context['work'], $technician, 'delivery', $context['providerOwner'],
        ), 'assignment_type');
    }

    public function test_cross_workspace_records_and_guessed_submission_are_rejected(): void
    {
        $first = $this->operation();
        $second = $this->operation();

        $this->assertFalse(app(\App\Services\WorkOrderAccessService::class)->canView(
            $first['accountOwner'], $second['work'],
        ));
        $this->expectAuthorization(fn () => app(ServiceAppointmentService::class)->propose(
            $first['work'], ['scheduled_start' => now()->addDay(), 'scheduled_end' => now()->addDay()->addHour()],
            $second['providerOwner'],
        ));

        app(WorkOrderService::class)->start($first['work'], $first['providerOwner']);
        $firstSubmission = app(WorkOrderCompletionService::class)->submit(
            $first['work']->fresh(), 'First completion', null,
            [['evidence_type' => 'other', 'text_value' => 'first']], $first['providerOwner'],
        );
        app(WorkOrderService::class)->start($second['work'], $second['providerOwner']);
        $secondSubmission = app(WorkOrderCompletionService::class)->submit(
            $second['work']->fresh(), 'Second completion', null,
            [['evidence_type' => 'other', 'text_value' => 'second']], $second['providerOwner'],
        );

        $this->expectAuthorization(fn () => app(WorkOrderCompletionService::class)->accept(
            $first['work']->fresh(), $secondSubmission, $second['accountOwner'],
        ));
        $this->assertSame(WorkOrderCompletionSubmission::STATUS_SUBMITTED, $firstSubmission->fresh()->status);
    }

    public function test_invoice_gate_accepts_reviewed_and_legacy_completed_work_only(): void
    {
        $context = $this->operation();
        app(WorkOrderService::class)->start($context['work'], $context['providerOwner']);
        $submission = app(WorkOrderCompletionService::class)->submit(
            $context['work']->fresh(), 'Done', null, [['evidence_type' => 'other', 'text_value' => 'verified']], $context['providerOwner'],
        );
        $this->assertValidationError(fn () => app(ProviderInvoiceService::class)->saveDraft(
            $context['quote'], ['invoice_date' => today()], null, $context['providerOwner'],
        ), 'work_order');

        app(WorkOrderCompletionService::class)->accept($context['work']->fresh(), $submission, $context['accountOwner']);
        $this->assertSame(WorkOrder::STATUS_COMPLETED, $context['work']->fresh()->status);
        $this->assertSame(WorkOrder::STATUS_COMPLETED, Quotation::withoutGlobalScopes()->with([
            'serviceRequest' => fn ($query) => $query->withoutGlobalScopes()->with([
                'workOrder' => fn ($workOrder) => $workOrder->withoutGlobalScopes(),
            ]),
        ])->find($context['quote']->id)->serviceRequest->workOrder?->status);
        $invoice = app(ProviderInvoiceService::class)->saveDraft(
            $context['quote'], ['invoice_date' => today()], null, $context['providerOwner'],
        );
        $this->assertInstanceOf(ProviderInvoice::class, $invoice);

        $legacy = $this->operation();
        $legacy['work']->forceFill(['completion_review_required' => false])->save();
        app(WorkOrderService::class)->start($legacy['work'], $legacy['providerOwner']);
        app(WorkOrderService::class)->transition($legacy['work']->fresh(), WorkOrder::STATUS_COMPLETED, [], $legacy['providerOwner']);
        $this->assertSame(WorkOrder::STATUS_COMPLETED, $legacy['work']->fresh()->status);
        $this->assertInstanceOf(ProviderInvoice::class, app(ProviderInvoiceService::class)->saveDraft(
            $legacy['quote'], ['invoice_date' => today()], null, $legacy['providerOwner'],
        ));
    }

    public function test_delivery_state_machine_evidence_security_and_timeline_immutability(): void
    {
        $context = $this->operation(ServiceRequest::TYPE_PRODUCT_SUPPLY);
        $sales = $this->member($context['provider'], 'sales');
        app(WorkOrderAssignmentService::class)->assign($context['work'], $sales, 'delivery', $context['providerOwner']);
        $delivery = app(SupplyDeliveryService::class)->create($context['work'], ['delivery_reference' => 'SHIP-1'], $sales->user);
        $delivery = app(SupplyDeliveryService::class)->transition($delivery, SupplyDelivery::STATUS_READY, [], $sales->user);
        $delivery = app(SupplyDeliveryService::class)->transition($delivery, SupplyDelivery::STATUS_DISPATCHED, [], $sales->user);
        app(SupplyDeliveryService::class)->addEvidence($delivery, [
            ['evidence_type' => 'delivery_receipt', 'file_path' => 'private/work-orders/receipt.pdf'],
        ], $sales->user);
        $delivery = app(SupplyDeliveryService::class)->transition($delivery, SupplyDelivery::STATUS_DELIVERED, ['recipient_name' => 'Site manager'], $sales->user);
        $this->assertNotNull($delivery->delivered_at);
        $this->assertDatabaseHas('work_order_evidence', ['file_path' => 'private/work-orders/receipt.pdf']);
        $this->assertValidationError(fn () => app(SupplyDeliveryService::class)->transition(
            $delivery, SupplyDelivery::STATUS_DISPATCHED, [], $sales->user,
        ), 'status');

        $foreign = $this->provider('Foreign Delivery');
        $this->expectAuthorization(fn () => app(SupplyDeliveryService::class)->transition(
            $delivery, SupplyDelivery::STATUS_CANCELLED, [], $foreign['owner'],
        ));

        $evidence = WorkOrderEvidence::where('file_path', 'private/work-orders/receipt.pdf')->firstOrFail();
        $this->actingAs($foreign['owner']);
        $this->get(route('work-order-evidence.show', $evidence))->assertForbidden();
        $activity = WorkOrderActivity::where('work_order_id', $context['work']->id)->firstOrFail();
        $this->expectException(LogicException::class);
        $activity->delete();
    }

    public function test_unfinished_supply_delivery_blocks_completion_until_terminal(): void
    {
        $context = $this->operation(ServiceRequest::TYPE_PRODUCT_SUPPLY);
        $delivery = app(SupplyDeliveryService::class)->create($context['work'], [], $context['providerOwner']);
        app(WorkOrderService::class)->start($context['work'], $context['providerOwner']);
        $this->assertValidationError(fn () => app(WorkOrderCompletionService::class)->submit(
            $context['work']->fresh(), 'Too early', null,
            [['evidence_type' => 'other', 'text_value' => 'not delivered']], $context['providerOwner'],
        ), 'delivery');

        $delivery = app(SupplyDeliveryService::class)->transition($delivery, SupplyDelivery::STATUS_READY, [], $context['providerOwner']);
        $delivery = app(SupplyDeliveryService::class)->transition($delivery, SupplyDelivery::STATUS_DISPATCHED, [], $context['providerOwner']);
        app(SupplyDeliveryService::class)->transition(
            $delivery, SupplyDelivery::STATUS_DELIVERED, ['recipient_name' => 'Customer'], $context['providerOwner'],
        );
        $submission = app(WorkOrderCompletionService::class)->submit(
            $context['work']->fresh(), 'Delivered', null,
            [['evidence_type' => 'delivery_receipt', 'text_value' => 'signed']], $context['providerOwner'],
        );
        $this->assertSame(WorkOrderCompletionSubmission::STATUS_SUBMITTED, $submission->status);
    }

    public function test_completion_review_notifies_managers_and_assigned_staff_only(): void
    {
        $context = $this->operation();
        $assigned = $this->member($context['provider'], 'technician');
        $unassigned = $this->member($context['provider'], 'technician');
        app(WorkOrderAssignmentService::class)->assign(
            $context['work'], $assigned, 'technician', $context['providerOwner'],
        );
        app(WorkOrderService::class)->start($context['work'], $assigned->user);
        $submission = app(WorkOrderCompletionService::class)->submit(
            $context['work']->fresh(), 'Ready for review', null,
            [['evidence_type' => 'other', 'text_value' => 'done']], $assigned->user,
        );
        $ownerBefore = $context['providerOwner']->notifications()->count();
        $assignedBefore = $assigned->user->notifications()->count();
        $unassignedBefore = $unassigned->user->notifications()->count();

        app(WorkOrderCompletionService::class)->requestRevision(
            $context['work']->fresh(), $submission, $context['accountOwner'], 'Correct the finishing.',
        );

        $this->assertSame($ownerBefore + 1, $context['providerOwner']->notifications()->count());
        $this->assertSame($assignedBefore + 1, $assigned->user->notifications()->count());
        $this->assertSame($unassignedBefore, $unassigned->user->notifications()->count());
    }

    public function test_maintenance_closes_only_after_account_acceptance(): void
    {
        $context = $this->operation();
        app(WorkOrderService::class)->start($context['work'], $context['providerOwner']);
        $submission = app(WorkOrderCompletionService::class)->submit(
            $context['work']->fresh(), 'Repair finished', null,
            [['evidence_type' => 'after_photo', 'file_path' => 'private/work-orders/repair.jpg']], $context['providerOwner'],
        );
        $this->assertSame(MaintenanceTicket::STATUS_IN_PROGRESS, $context['maintenanceTicket']->fresh()->status);

        app(WorkOrderCompletionService::class)->accept($context['work']->fresh(), $submission, $context['accountOwner']);
        $this->assertSame(MaintenanceTicket::STATUS_RESOLVED, $context['maintenanceTicket']->fresh()->status);
    }

    public function test_external_inspection_acceptance_records_reference_without_claiming_internal_inspector(): void
    {
        $context = $this->operation(ServiceRequest::TYPE_INSPECTION);
        $inspector = $this->member($context['provider'], 'inspector');
        app(WorkOrderAssignmentService::class)->assign($context['work'], $inspector, 'inspector', $context['providerOwner']);
        app(WorkOrderService::class)->start($context['work'], $inspector->user);
        $submission = app(WorkOrderCompletionService::class)->submit(
            $context['work']->fresh(), 'External inspection finished', null,
            [['evidence_type' => 'inspection_report', 'file_path' => 'private/work-orders/report.pdf']], $inspector->user,
        );
        app(WorkOrderCompletionService::class)->accept($context['work']->fresh(), $submission, $context['accountOwner']);

        $inspection = $context['inspection']->fresh();
        $this->assertSame($context['work']->id, $inspection->external_work_order_id);
        $this->assertNotNull($inspection->external_completed_at);
        $this->assertNull($inspection->inspected_by);
    }

    public function test_account_and_provider_operational_screens_render_in_their_workspaces(): void
    {
        $context = $this->operation(ServiceRequest::TYPE_PRODUCT_SUPPLY);
        $this->actingAs($context['accountOwner']);
        app(CurrentProviderCompany::class)->forget();
        app(CurrentAccount::class)->forget();
        app(CurrentAccount::class)->switch($context['accountOwner'], $context['account']->id);
        $this->get('/admin/work-orders')->assertOk();
        $this->get('/admin/work-orders/'.$context['work']->id)->assertOk();

        $this->actingAs($context['providerOwner']);
        app(CurrentAccount::class)->forget();
        app(CurrentProviderCompany::class)->forget();
        app(CurrentProviderCompany::class)->switch($context['providerOwner'], $context['provider']->id);
        $this->get('/provider/work-orders')->assertOk();
        $this->get('/provider/work-orders/'.$context['work']->id)->assertOk();
        $this->get('/provider/supply-deliveries')->assertOk();
    }

    private function operation(string $type = ServiceRequest::TYPE_MAINTENANCE): array
    {
        app(CurrentAccount::class)->forget();
        app(CurrentProviderCompany::class)->forget();
        auth()->logout();
        $accountOwner = User::factory()->create();
        $account = Account::create([
            'name' => 'Operations '.str()->random(8), 'slug' => 'operations-'.str()->lower(str()->random(10)),
            'type' => Account::TYPE_INDIVIDUAL_LANDLORD, 'status' => Account::STATUS_ACTIVE,
            'currency' => 'RWF', 'timezone' => 'Africa/Kigali',
        ]);
        $account->users()->attach($accountOwner, ['role' => Account::ROLE_OWNER, 'is_active' => true]);
        $this->actingAs($accountOwner);
        app(CurrentAccount::class)->switch($accountOwner, $account->id);
        $propertyOwner = PropertyOwner::create(['name' => 'Operations owner']);
        $property = Property::create(['property_owner_id' => $propertyOwner->id, 'name' => 'Operations property', 'type' => 'apartment']);
        $unit = Unit::create(['property_id' => $property->id, 'unit_code' => 'OPS-'.str()->upper(str()->random(5)), 'monthly_rent' => 1000, 'status' => Unit::STATUS_VACANT]);
        $maintenanceTicket = $type === ServiceRequest::TYPE_MAINTENANCE ? MaintenanceTicket::create([
            'unit_id' => $unit->id, 'ticket_no' => 'MT-'.str()->upper(str()->random(10)),
            'title' => 'Repair', 'priority' => 'medium', 'status' => MaintenanceTicket::STATUS_OPEN,
        ]) : null;
        $inspection = $type === ServiceRequest::TYPE_INSPECTION ? Inspection::create([
            'unit_id' => $unit->id, 'type' => 'routine', 'inspected_on' => today(),
        ]) : null;
        $providerData = $this->provider('Provider '.str()->random(8));
        $provider = $providerData['company'];
        $providerOwner = $providerData['owner'];
        $request = ServiceRequest::withoutGlobalScopes()->create([
            'account_id' => $account->id, 'request_number' => 'SR-'.str()->upper(str()->random(10)),
            'request_type' => $type, 'title' => 'Operations job', 'description' => 'Scoped operational details',
            'priority' => 'normal', 'status' => ServiceRequest::STATUS_QUOTE_ACCEPTED, 'created_by' => $accountOwner->id,
            'property_owner_id' => $propertyOwner->id, 'property_id' => $property->id, 'unit_id' => $unit->id,
            'maintenance_ticket_id' => $maintenanceTicket?->id, 'inspection_id' => $inspection?->id,
        ]);
        $quote = Quotation::withoutGlobalScopes()->create([
            'service_request_id' => $request->id, 'provider_company_id' => $provider->id,
            'quotation_number' => 'QT-'.str()->upper(str()->random(10)), 'status' => Quotation::STATUS_DRAFT,
            'currency' => 'RWF', 'subtotal' => 1000, 'total_amount' => 1000, 'created_by' => $providerOwner->id,
        ]);
        $quote->lines()->create([
            'description' => 'Operational work', 'quantity' => 1, 'unit_price' => 1000,
            'tax_amount' => 0, 'discount_amount' => 0, 'line_total' => 1000, 'is_alternative' => false,
        ]);
        $quote->forceFill(['status' => Quotation::STATUS_ACCEPTED, 'accepted_at' => now(), 'accepted_by' => $accountOwner->id])->save();
        $request->forceFill(['accepted_quotation_id' => $quote->id])->saveQuietly();
        $work = WorkOrder::withoutGlobalScopes()->create([
            'service_request_id' => $request->id, 'quotation_id' => $quote->id,
            'provider_company_id' => $provider->id, 'work_order_number' => 'WO-'.str()->upper(str()->random(10)),
            'status' => WorkOrder::STATUS_PENDING, 'completion_review_required' => true, 'created_by' => $accountOwner->id,
        ]);

        return compact(
            'account', 'accountOwner', 'provider', 'providerOwner', 'request', 'quote', 'work',
            'propertyOwner', 'property', 'unit', 'maintenanceTicket', 'inspection',
        );
    }

    private function provider(string $name): array
    {
        auth()->logout();
        app(CurrentAccount::class)->forget();
        app(CurrentProviderCompany::class)->forget();
        $company = ProviderCompany::create([
            'name' => $name, 'slug' => str()->slug($name).'-'.str()->lower(str()->random(8)),
            'status' => ProviderCompany::STATUS_ACTIVE, 'email' => str()->random(8).'@provider.test',
        ]);
        $owner = User::factory()->create();
        $ownerMembership = ProviderCompanyMembership::withoutGlobalScopes()->create([
            'provider_company_id' => $company->id, 'user_id' => $owner->id, 'role' => 'owner', 'is_active' => true,
        ]);

        return ['company' => $company, 'owner' => $owner, 'ownerMembership' => $ownerMembership];
    }

    private function member(ProviderCompany $company, string $role, bool $active = true): ProviderCompanyMembership
    {
        return ProviderCompanyMembership::withoutGlobalScopes()->create([
            'provider_company_id' => $company->id, 'user_id' => User::factory()->create()->id,
            'role' => $role, 'is_active' => $active,
        ])->load('user');
    }

    private function accountMember(Account $account, string $role): User
    {
        $user = User::factory()->create();
        $account->users()->attach($user, ['role' => $role, 'is_active' => true]);

        return $user;
    }

    private function useProvider(User $user, ProviderCompany $company): void
    {
        $this->actingAs($user);
        app(CurrentAccount::class)->forget();
        app(CurrentProviderCompany::class)->forget();
        app(CurrentProviderCompany::class)->switch($user, $company->id);
    }

    private function expectAuthorization(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected authorization to be rejected.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }
    }

    private function assertValidationError(callable $callback, string $key): void
    {
        try {
            $callback();
            $this->fail("Expected validation error for {$key}.");
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($key, $exception->errors());
        }
    }
}
