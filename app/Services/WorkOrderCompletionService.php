<?php

namespace App\Services;

use App\Models\Inspection;
use App\Models\MaintenanceTicket;
use App\Models\ProviderCompany;
use App\Models\ServiceAppointment;
use App\Models\ServiceRequest;
use App\Models\SupplyDelivery;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderAssignment;
use App\Models\WorkOrderCompletionSubmission;
use App\Notifications\MarketplaceNotification;
use App\Support\AccountAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkOrderCompletionService
{
    public function __construct(
        private readonly WorkOrderAccessService $access,
        private readonly WorkOrderActivityService $activities,
        private readonly WorkOrderService $workOrders,
    ) {}

    public function submit(WorkOrder $workOrder, string $summary, ?string $notes, array $evidence, User $user): WorkOrderCompletionSubmission
    {
        $this->access->authorizeProviderOperation($user, $workOrder);
        if (blank($summary)) {
            throw ValidationException::withMessages(['summary' => 'A completion summary is required.']);
        }

        return DB::transaction(function () use ($workOrder, $summary, $notes, $evidence, $user) {
            ProviderCompany::lockForUpdate()->findOrFail($workOrder->provider_company_id);
            $workOrder = WorkOrder::withoutGlobalScopes()->lockForUpdate()->findOrFail($workOrder->getKey());
            $this->access->authorizeProviderOperation($user, $workOrder);
            if ($workOrder->status !== WorkOrder::STATUS_IN_PROGRESS) {
                throw ValidationException::withMessages(['status' => 'Completion can only be submitted while work is in progress.']);
            }
            if (! $workOrder->completion_review_required) {
                throw ValidationException::withMessages(['completion' => 'This legacy work order uses the legacy completion path.']);
            }

            $request = ServiceRequest::withoutGlobalScopes()->findOrFail($workOrder->service_request_id);
            if ($request->request_type === ServiceRequest::TYPE_PRODUCT_SUPPLY
                && SupplyDelivery::withoutGlobalScopes()
                    ->where('work_order_id', $workOrder->getKey())
                    ->whereIn('status', [
                        SupplyDelivery::STATUS_PREPARING,
                        SupplyDelivery::STATUS_READY,
                        SupplyDelivery::STATUS_DISPATCHED,
                    ])->exists()) {
                throw ValidationException::withMessages([
                    'delivery' => 'Finish or cancel every existing delivery before submitting completion.',
                ]);
            }
            $this->validateEvidenceTypes($request->request_type, $evidence);
            $submissionNumber = ((int) WorkOrderCompletionSubmission::where('work_order_id', $workOrder->getKey())
                ->max('submission_number')) + 1;
            $submission = WorkOrderCompletionSubmission::create([
                'work_order_id' => $workOrder->getKey(), 'submission_number' => $submissionNumber,
                'summary' => $summary, 'provider_notes' => $notes,
                'status' => WorkOrderCompletionSubmission::STATUS_SUBMITTED,
                'submitted_by' => $user->getKey(), 'submitted_at' => now(),
            ]);
            $evidenceIds = $this->workOrders->storeEvidence($workOrder, $submission->getKey(), $evidence, $user);
            $workOrder->forceFill(['status' => WorkOrder::STATUS_COMPLETION_SUBMITTED])->save();
            $this->activities->record($workOrder, 'completion_submitted', 'Provider submitted completion for Account review.', $user, [
                'submission_id' => $submission->getKey(), 'submission_number' => $submissionNumber,
                'evidence_ids' => $evidenceIds,
            ]);
            $request->creator?->notify(new MarketplaceNotification([
                'title' => 'Completion submitted for review', 'work_order_id' => $workOrder->getKey(),
                'completion_submission_id' => $submission->getKey(),
            ]));

            return $submission->fresh('evidence');
        });
    }

    public function accept(WorkOrder $workOrder, WorkOrderCompletionSubmission $submission, User $user): WorkOrder
    {
        $this->access->authorizeAccount($user, $workOrder, AccountAccess::REVIEW_MARKETPLACE_COMPLETION);

        return DB::transaction(function () use ($workOrder, $submission, $user) {
            $workOrder = WorkOrder::withoutGlobalScopes()->lockForUpdate()->findOrFail($workOrder->getKey());
            $submission = WorkOrderCompletionSubmission::lockForUpdate()->findOrFail($submission->getKey());
            $this->access->authorizeAccount($user, $workOrder, AccountAccess::REVIEW_MARKETPLACE_COMPLETION);
            $this->assertReviewable($workOrder, $submission);
            $recipients = $this->providerReviewRecipients($workOrder);

            $submission->forceFill([
                'status' => WorkOrderCompletionSubmission::STATUS_ACCEPTED,
                'reviewed_by' => $user->getKey(), 'reviewed_at' => now(),
            ])->save();
            $workOrder->forceFill([
                'status' => WorkOrder::STATUS_COMPLETED, 'completed_at' => now(),
                'accepted_completion_submission_id' => $submission->getKey(),
            ])->save();
            $request = ServiceRequest::withoutGlobalScopes()->lockForUpdate()->findOrFail($workOrder->service_request_id);
            $request->forceFill(['status' => ServiceRequest::STATUS_COMPLETED])->saveQuietly();
            $this->synchronizeAcceptedCompletion($workOrder, $request);
            WorkOrderAssignment::withoutGlobalScopes()->where('work_order_id', $workOrder->getKey())
                ->whereIn('status', WorkOrderAssignment::ACTIVE_STATUSES)
                ->update(['status' => WorkOrderAssignment::STATUS_COMPLETED]);
            ServiceAppointment::withoutGlobalScopes()->where('work_order_id', $workOrder->getKey())
                ->where('status', ServiceAppointment::STATUS_CONFIRMED)
                ->update(['status' => ServiceAppointment::STATUS_COMPLETED]);
            $this->activities->record($workOrder, 'completion_accepted', 'The Account accepted provider completion.', $user, [
                'submission_id' => $submission->getKey(),
            ]);
            $this->notifyProviderReviewRecipients($recipients, $workOrder, 'Completion accepted');

            return $workOrder->refresh();
        });
    }

    public function requestRevision(WorkOrder $workOrder, WorkOrderCompletionSubmission $submission, User $user, string $reason): WorkOrder
    {
        if (blank($reason)) {
            throw ValidationException::withMessages(['review_notes' => 'Explain what the provider must correct.']);
        }
        $this->access->authorizeAccount($user, $workOrder, AccountAccess::REVIEW_MARKETPLACE_COMPLETION);

        return DB::transaction(function () use ($workOrder, $submission, $user, $reason) {
            $workOrder = WorkOrder::withoutGlobalScopes()->lockForUpdate()->findOrFail($workOrder->getKey());
            $submission = WorkOrderCompletionSubmission::lockForUpdate()->findOrFail($submission->getKey());
            $this->access->authorizeAccount($user, $workOrder, AccountAccess::REVIEW_MARKETPLACE_COMPLETION);
            $this->assertReviewable($workOrder, $submission);
            $recipients = $this->providerReviewRecipients($workOrder);
            $submission->forceFill([
                'status' => WorkOrderCompletionSubmission::STATUS_REVISION_REQUESTED,
                'reviewed_by' => $user->getKey(), 'reviewed_at' => now(), 'review_notes' => $reason,
            ])->save();
            $workOrder->forceFill(['status' => WorkOrder::STATUS_REVISION_REQUESTED])->save();
            $this->activities->record($workOrder, 'completion_revision_requested', 'The Account requested completion corrections.', $user, [
                'submission_id' => $submission->getKey(), 'review_notes' => $reason,
            ]);
            $this->notifyProviderReviewRecipients($recipients, $workOrder, 'Completion revision requested');

            return $workOrder->refresh();
        });
    }

    private function assertReviewable(WorkOrder $workOrder, WorkOrderCompletionSubmission $submission): void
    {
        if ((int) $submission->work_order_id !== (int) $workOrder->getKey()) {
            throw ValidationException::withMessages(['submission' => 'The completion submission belongs to another work order.']);
        }
        if ($workOrder->status !== WorkOrder::STATUS_COMPLETION_SUBMITTED
            || $submission->status !== WorkOrderCompletionSubmission::STATUS_SUBMITTED) {
            throw ValidationException::withMessages(['submission' => 'This completion submission is no longer awaiting review.']);
        }
    }

    private function validateEvidenceTypes(string $requestType, array $evidence): void
    {
        $allowed = match ($requestType) {
            ServiceRequest::TYPE_MAINTENANCE => ['before_photo', 'after_photo', 'other'],
            ServiceRequest::TYPE_INSPECTION => ['before_photo', 'after_photo', 'inspection_report', 'other'],
            ServiceRequest::TYPE_PRODUCT_SUPPLY => ['delivery_receipt', 'delivery_photo', 'serial_number', 'model_received', 'other'],
            default => [],
        };
        foreach ($evidence as $index => $item) {
            if (! in_array($item['evidence_type'] ?? null, $allowed, true)) {
                throw ValidationException::withMessages(["evidence.{$index}.evidence_type" => 'This evidence type is not valid for the job type.']);
            }
        }
    }

    private function synchronizeAcceptedCompletion(WorkOrder $workOrder, ServiceRequest $request): void
    {
        if ($request->request_type === ServiceRequest::TYPE_MAINTENANCE && $request->maintenance_ticket_id) {
            MaintenanceTicket::withoutGlobalScopes()->whereKey($request->maintenance_ticket_id)->update([
                'status' => MaintenanceTicket::STATUS_RESOLVED, 'resolved_on' => today(),
            ]);
        }
        if ($request->request_type === ServiceRequest::TYPE_INSPECTION && $request->inspection_id) {
            Inspection::withoutGlobalScopes()->whereKey($request->inspection_id)->update([
                'external_work_order_id' => $workOrder->getKey(), 'external_completed_at' => now(),
            ]);
        }
    }

    private function providerReviewRecipients(WorkOrder $workOrder): \Illuminate\Support\Collection
    {
        $managers = ProviderCompany::find($workOrder->provider_company_id)?->users()
            ->wherePivot('is_active', true)->get()
            ->filter(fn ($user) => in_array($user->pivot->role, ['owner', 'administrator'], true))
            ?? collect();
        $assigned = WorkOrderAssignment::withoutGlobalScopes()
            ->where('work_order_id', $workOrder->getKey())
            ->whereIn('status', WorkOrderAssignment::ACTIVE_STATUSES)
            ->with(['membership' => fn ($membership) => $membership->withoutGlobalScopes()->with('user')])->get()
            ->filter(fn ($assignment) => $assignment->membership?->is_active)
            ->pluck('membership.user')->filter();

        return $managers->concat($assigned)->unique('id')->values();
    }

    private function notifyProviderReviewRecipients(\Illuminate\Support\Collection $recipients, WorkOrder $workOrder, string $title): void
    {
        $recipients->each->notify(new MarketplaceNotification([
            'title' => $title, 'work_order_id' => $workOrder->getKey(),
        ]));
    }
}
