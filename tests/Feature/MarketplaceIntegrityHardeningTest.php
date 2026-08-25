<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Inspection;
use App\Models\Lease;
use App\Models\MaintenanceTicket;
use App\Models\ManagementAgreement;
use App\Models\Property;
use App\Models\PropertyOwner;
use App\Models\ProviderCapability;
use App\Models\ProviderCompany;
use App\Models\ProviderCompanyMembership;
use App\Models\ProviderInvitation;
use App\Models\ProviderStaffInvitation;
use App\Models\Quotation;
use App\Models\ServiceRequest;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\ProviderInvoiceService;
use App\Services\ProviderStaffInvitationService;
use App\Services\QuotationAcceptanceService;
use App\Services\QuotationService;
use App\Services\ServiceRequestService;
use App\Services\WorkOrderService;
use App\Support\CurrentAccount;
use App\Support\CurrentProviderCompany;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class MarketplaceIntegrityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_late_quotes_are_blocked_after_acceptance_and_request_status_never_regresses(): void
    {
        [$owner, $account, $portfolio] = $this->workspace();
        $first = $this->provider('Accepted RFQ Provider');
        $late = $this->provider('Late RFQ Provider');
        $request = $this->request($account, $portfolio, $owner);
        app(ServiceRequestService::class)->invite($request, [$first['company']->id, $late['company']->id], $owner);
        $acceptedQuote = $this->submittedQuote($request, $first);

        $this->useAccount($owner, $account);
        app(QuotationAcceptanceService::class)->accept($acceptedQuote, $owner);

        $this->useProvider($late['user'], $late['company']);
        $this->assertValidationError(
            fn () => app(QuotationService::class)->saveDraft($request->fresh(), $late['company'], [], $this->quoteLines(), $late['user']),
            'service_request',
        );
        $this->assertSame(ServiceRequest::STATUS_QUOTE_ACCEPTED, $request->fresh()->status);
        $this->assertSame(
            ProviderInvitation::STATUS_NOT_SELECTED,
            ProviderInvitation::where('service_request_id', $request->id)->where('provider_company_id', $late['company']->id)->value('status'),
        );
    }

    public function test_completed_invoiced_and_closed_requests_cannot_receive_quotes(): void
    {
        [$owner, $account, $portfolio] = $this->workspace();
        $provider = $this->provider('Terminal State Provider');

        foreach ([ServiceRequest::STATUS_COMPLETED, ServiceRequest::STATUS_INVOICED, ServiceRequest::STATUS_CLOSED] as $status) {
            $request = $this->request($account, $portfolio, $owner);
            app(ServiceRequestService::class)->invite($request, [$provider['company']->id], $owner);
            ServiceRequest::withoutGlobalScopes()->whereKey($request->id)->update(['status' => $status]);
            $this->useProvider($provider['user'], $provider['company']);
            $this->assertValidationError(
                fn () => app(QuotationService::class)->saveDraft($request->fresh(), $provider['company'], [], $this->quoteLines(), $provider['user']),
                'service_request',
            );
        }
    }

    public function test_expired_submitted_quotation_cannot_be_accepted(): void
    {
        [$owner, $account, $portfolio] = $this->workspace();
        $provider = $this->provider('Expired Quote Provider');
        $request = $this->request($account, $portfolio, $owner);
        app(ServiceRequestService::class)->invite($request, [$provider['company']->id], $owner);
        $quote = $this->submittedQuote($request, $provider, '500000.00', ['valid_until' => today()->subDay()->toDateString()]);

        $this->useAccount($owner, $account);
        $this->assertValidationError(fn () => app(QuotationAcceptanceService::class)->accept($quote, $owner), 'quotation');
        $this->assertSame(Quotation::STATUS_EXPIRED, $quote->fresh()->status);
    }

    public function test_owner_approval_is_role_restricted_and_bound_to_exact_quote_and_amount(): void
    {
        [$owner, $account, $portfolio] = $this->workspace(Account::TYPE_PROPERTY_MANAGEMENT_COMPANY);
        ManagementAgreement::create([
            'property_owner_id' => $portfolio['owner']->id, 'property_id' => $portfolio['property']->id,
            'reference_number' => 'AGR-HARDEN', 'start_date' => today()->subYear(),
            'management_fee_type' => ManagementAgreement::FEE_FIXED, 'management_fee_fixed_amount' => 0,
            'maintenance_approval_limit' => '100000.00', 'status' => ManagementAgreement::STATUS_ACTIVE,
        ]);
        $first = $this->provider('Approved Quote Provider');
        $second = $this->provider('Unapproved Quote Provider');
        $request = $this->request($account, $portfolio, $owner);
        app(ServiceRequestService::class)->invite($request, [$first['company']->id, $second['company']->id], $owner);
        $quoteA = $this->submittedQuote($request, $first, '500000.00');
        $quoteB = $this->submittedQuote($request, $second, '600000.00');

        $maintenance = $this->accountUser($account, Account::ROLE_MAINTENANCE);
        $this->useAccount($maintenance, $account);
        $this->assertForbiddenCall(fn () => app(ServiceRequestService::class)
            ->recordOwnerApproval($request, $quoteA, $maintenance, 'Not authorized'));

        $manager = $this->accountUser($account, Account::ROLE_PROPERTY_MANAGER);
        $this->useAccount($manager, $account);
        $approved = app(ServiceRequestService::class)->recordOwnerApproval($request, $quoteA, $manager, 'Owner email REF-1');
        $this->assertSame($quoteA->id, $approved->owner_approved_quotation_id);
        $this->assertSame('500000.00', $approved->owner_approved_amount);

        $this->assertValidationError(fn () => app(QuotationAcceptanceService::class)->accept($quoteB, $manager), 'owner_approval');
        ServiceRequest::withoutGlobalScopes()->whereKey($request->id)->update(['owner_approved_amount' => '499999.00']);
        $this->assertValidationError(fn () => app(QuotationAcceptanceService::class)->accept($quoteA, $manager), 'owner_approval');

        app(ServiceRequestService::class)->recordOwnerApproval($request->fresh(), $quoteA, $manager, 'Owner email REF-2');
        $this->assertSame(Quotation::STATUS_ACCEPTED, app(QuotationAcceptanceService::class)->accept($quoteA, $manager)->status);
    }

    public function test_forged_same_account_hierarchy_and_source_ids_are_rejected(): void
    {
        [$owner, $account, $first] = $this->workspace();
        $secondOwner = PropertyOwner::create(['name' => 'Second Owner']);
        $secondProperty = Property::create(['property_owner_id' => $secondOwner->id, 'name' => 'Second Property', 'type' => 'house']);
        $secondUnit = Unit::create(['property_id' => $secondProperty->id, 'unit_code' => 'SECOND', 'monthly_rent' => 1, 'status' => Unit::STATUS_VACANT]);
        $tenant = Tenant::create(['full_name' => 'Second Tenant', 'id_number' => 'SECOND-ID']);
        $secondLease = Lease::create([
            'unit_id' => $secondUnit->id, 'tenant_id' => $tenant->id, 'start_date' => today(),
            'monthly_rent' => 1, 'deposit' => 1, 'status' => Lease::STATUS_DRAFT,
        ]);
        $ticket = MaintenanceTicket::create(['unit_id' => $secondUnit->id, 'lease_id' => $secondLease->id, 'title' => 'Forged ticket', 'description' => 'x']);
        $inspection = Inspection::create(['unit_id' => $secondUnit->id, 'lease_id' => $secondLease->id, 'type' => 'move_in', 'inspected_on' => today()]);

        $base = [
            'property_owner_id' => $first['owner']->id, 'property_id' => $first['property']->id,
            'unit_id' => $first['unit']->id, 'lease_id' => $first['lease']->id,
            'request_type' => ServiceRequest::TYPE_MAINTENANCE, 'title' => 'Forged hierarchy',
            'description' => 'Must fail', 'priority' => 'normal',
        ];
        $service = app(ServiceRequestService::class);
        $lines = [['description' => 'Line', 'quantity' => 1]];

        $this->assertValidationError(fn () => $service->create($account, [...$base, 'lease_id' => $secondLease->id], $lines, $owner), 'lease_id');
        $this->assertValidationError(fn () => $service->create($account, [...$base, 'maintenance_ticket_id' => $ticket->id], $lines, $owner), 'maintenance_ticket_id');
        $this->assertValidationError(fn () => $service->create($account, [
            ...$base, 'request_type' => ServiceRequest::TYPE_INSPECTION, 'inspection_id' => $inspection->id,
        ], $lines, $owner), 'inspection_id');
    }

    public function test_default_provider_invoice_preserves_quote_delivery_and_completed_transition_cannot_replay(): void
    {
        [$owner, $account, $portfolio] = $this->workspace();
        $provider = $this->provider('Delivery Provider');
        $request = $this->request($account, $portfolio, $owner);
        app(ServiceRequestService::class)->invite($request, [$provider['company']->id], $owner);
        $quote = $this->submittedQuote($request, $provider, '500000.00', ['delivery_amount' => '20000.00'], '90000.00', '10000.00');
        $this->useAccount($owner, $account);
        app(QuotationAcceptanceService::class)->accept($quote, $owner);

        $this->useProvider($provider['user'], $provider['company']);
        $work = WorkOrder::firstOrFail();
        app(WorkOrderService::class)->transition($work, WorkOrder::STATUS_IN_PROGRESS, [], $provider['user']);
        $completed = app(WorkOrderService::class)->transition($work->fresh(), WorkOrder::STATUS_COMPLETED, ['completion_notes' => 'Done'], $provider['user']);
        $this->assertValidationError(
            fn () => app(WorkOrderService::class)->transition($completed, WorkOrder::STATUS_COMPLETED, ['completion_notes' => 'Replay'], $provider['user']),
            'status',
        );

        $invoice = app(ProviderInvoiceService::class)->saveDraft($quote, ['invoice_date' => today()], null, $provider['user']);
        $this->assertSame('20000.00', $invoice->delivery_amount);
        $this->assertSame('600000.00', $invoice->total_amount);
        $this->assertSame($quote->fresh()->total_amount, $invoice->total_amount);
    }

    public function test_staff_invitation_is_email_bound_and_does_not_enrol_users_by_id(): void
    {
        $provider = $this->provider('Private Staff Provider');
        $invited = User::factory()->create(['email' => 'invited@example.test']);
        $other = User::factory()->create(['email' => 'other@example.test']);
        $this->useProvider($provider['user'], $provider['company']);
        $invitation = app(ProviderStaffInvitationService::class)->invite(
            $provider['company'], 'INVITED@example.test', 'technician', $provider['user'],
        );

        $this->assertSame('invited@example.test', $invitation->email);
        $this->assertDatabaseMissing('provider_company_memberships', [
            'provider_company_id' => $provider['company']->id, 'user_id' => $invited->id,
        ]);
        $this->assertValidationError(
            fn () => app(ProviderStaffInvitationService::class)->accept($invitation->plainTextToken, $other),
            'email',
        );
        $membership = app(ProviderStaffInvitationService::class)->accept($invitation->plainTextToken, $invited);
        $this->assertSame('technician', $membership->role);
        $this->assertSame(ProviderStaffInvitation::STATUS_ACCEPTED, $invitation->fresh()->status);
    }

    public function test_suspended_provider_cannot_switch_quote_change_work_or_submit_invoice(): void
    {
        [$owner, $account, $portfolio] = $this->workspace();
        $provider = $this->provider('Suspended Provider');

        $quoteRequest = $this->request($account, $portfolio, $owner);
        app(ServiceRequestService::class)->invite($quoteRequest, [$provider['company']->id], $owner);

        $workRequest = $this->request($account, $portfolio, $owner);
        app(ServiceRequestService::class)->invite($workRequest, [$provider['company']->id], $owner);
        $workQuote = $this->submittedQuote($workRequest, $provider);
        $this->useAccount($owner, $account);
        app(QuotationAcceptanceService::class)->accept($workQuote, $owner);

        $invoiceRequest = $this->request($account, $portfolio, $owner);
        app(ServiceRequestService::class)->invite($invoiceRequest, [$provider['company']->id], $owner);
        $invoiceQuote = $this->submittedQuote($invoiceRequest, $provider);
        $this->useAccount($owner, $account);
        app(QuotationAcceptanceService::class)->accept($invoiceQuote, $owner);
        $this->useProvider($provider['user'], $provider['company']);
        $invoiceWork = WorkOrder::withoutGlobalScopes()->where('service_request_id', $invoiceRequest->id)->firstOrFail();
        app(WorkOrderService::class)->transition($invoiceWork, WorkOrder::STATUS_IN_PROGRESS, [], $provider['user']);
        app(WorkOrderService::class)->transition($invoiceWork->fresh(), WorkOrder::STATUS_COMPLETED, [], $provider['user']);
        $invoice = app(ProviderInvoiceService::class)->saveDraft($invoiceQuote, ['invoice_date' => today()], null, $provider['user']);

        $provider['company']->forceFill(['status' => ProviderCompany::STATUS_SUSPENDED])->save();
        app(CurrentProviderCompany::class)->forget();
        try {
            app(CurrentProviderCompany::class)->switch($provider['user'], $provider['company']->id);
            $this->fail('Suspended provider workspace was switched into.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }

        $this->assertValidationError(
            fn () => app(QuotationService::class)->saveDraft($quoteRequest, $provider['company'], [], $this->quoteLines(), $provider['user']),
            'provider',
        );
        $work = WorkOrder::withoutGlobalScopes()->where('service_request_id', $workRequest->id)->firstOrFail();
        $this->assertValidationError(
            fn () => app(WorkOrderService::class)->transition($work, WorkOrder::STATUS_IN_PROGRESS, [], $provider['user']),
            'provider',
        );
        $this->assertValidationError(fn () => app(ProviderInvoiceService::class)->submit($invoice, $provider['user']), 'provider');
    }

    private function workspace(string $type = Account::TYPE_INDIVIDUAL_LANDLORD): array
    {
        $user = User::factory()->create();
        $account = Account::create([
            'name' => 'Hardening '.str()->random(6), 'slug' => 'hardening-'.str()->lower(str()->random(8)),
            'type' => $type, 'status' => Account::STATUS_ACTIVE, 'currency' => 'RWF', 'timezone' => 'Africa/Kigali',
        ]);
        $account->users()->attach($user, ['role' => Account::ROLE_OWNER, 'is_active' => true]);
        $this->useAccount($user, $account);
        $owner = PropertyOwner::create(['name' => 'Hardening Owner']);
        if ($type === Account::TYPE_INDIVIDUAL_LANDLORD) {
            $account->forceFill(['self_property_owner_id' => $owner->id])->saveQuietly();
        }
        $property = Property::create(['property_owner_id' => $owner->id, 'name' => 'Hardening Property', 'type' => 'apartment']);
        $unit = Unit::create(['property_id' => $property->id, 'unit_code' => 'HARD-1', 'monthly_rent' => 500000, 'status' => Unit::STATUS_OCCUPIED]);
        $tenant = Tenant::create(['full_name' => 'Hardening Tenant', 'id_number' => 'HARD-ID']);
        $lease = Lease::create([
            'unit_id' => $unit->id, 'tenant_id' => $tenant->id, 'start_date' => today()->subYear(),
            'monthly_rent' => 500000, 'deposit' => 500000, 'status' => Lease::STATUS_DRAFT,
        ]);

        return [$user, $account, compact('owner', 'property', 'unit', 'tenant', 'lease')];
    }

    private function provider(string $name): array
    {
        auth()->logout();
        app(CurrentAccount::class)->forget();
        app(CurrentProviderCompany::class)->forget();
        $company = ProviderCompany::create([
            'name' => $name, 'slug' => str()->slug($name).'-'.str()->lower(str()->random(5)),
            'status' => ProviderCompany::STATUS_ACTIVE,
        ]);
        ProviderCapability::withoutGlobalScopes()->create(['provider_company_id' => $company->id, 'capability' => 'maintenance']);
        $user = User::factory()->create();
        ProviderCompanyMembership::withoutGlobalScopes()->create([
            'provider_company_id' => $company->id, 'user_id' => $user->id, 'role' => 'owner', 'is_active' => true,
        ]);

        return compact('company', 'user');
    }

    private function accountUser(Account $account, string $role): User
    {
        $user = User::factory()->create();
        $account->users()->attach($user, ['role' => $role, 'is_active' => true]);

        return $user;
    }

    private function request(Account $account, array $portfolio, User $user): ServiceRequest
    {
        $this->useAccount($user, $account);

        return app(ServiceRequestService::class)->create($account, [
            'property_owner_id' => $portfolio['owner']->id, 'property_id' => $portfolio['property']->id,
            'unit_id' => $portfolio['unit']->id, 'lease_id' => $portfolio['lease']->id,
            'request_type' => ServiceRequest::TYPE_MAINTENANCE, 'title' => 'Hardening request',
            'description' => 'Hardening request details', 'priority' => 'normal',
        ], [['description' => 'Required work', 'quantity' => 1]], $user);
    }

    private function submittedQuote(
        ServiceRequest $request,
        array $provider,
        string $amount = '500000.00',
        array $attributes = [],
        string $tax = '0',
        string $discount = '0',
    ): Quotation {
        $this->useProvider($provider['user'], $provider['company']);
        $quote = app(QuotationService::class)->saveDraft(
            $request, $provider['company'], $attributes, $this->quoteLines($amount, $tax, $discount), $provider['user'],
        );

        return app(QuotationService::class)->submit($quote, $provider['user']);
    }

    private function quoteLines(string $amount = '500000.00', string $tax = '0', string $discount = '0'): array
    {
        return [[
            'description' => 'Labour and materials', 'quantity' => 1, 'unit_price' => $amount,
            'tax_amount' => $tax, 'discount_amount' => $discount, 'is_alternative' => false,
        ]];
    }

    private function useAccount(User $user, Account $account): void
    {
        $this->actingAs($user);
        app(CurrentProviderCompany::class)->forget();
        app(CurrentAccount::class)->forget();
        app(CurrentAccount::class)->switch($user, $account->id);
    }

    private function useProvider(User $user, ProviderCompany $company): void
    {
        $this->actingAs($user);
        app(CurrentAccount::class)->forget();
        app(CurrentProviderCompany::class)->forget();
        app(CurrentProviderCompany::class)->switch($user, $company->id);
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

    private function assertForbiddenCall(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected the operation to be forbidden.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }
}
