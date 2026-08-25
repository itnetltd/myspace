<?php

namespace App\Services;

use App\Models\MaintenanceTicket;
use App\Models\ProviderCompany;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderEvidence;
use App\Notifications\MarketplaceNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkOrderService
{
    public function __construct(
        private readonly WorkOrderAccessService $access,
        private readonly WorkOrderActivityService $activities,
    ) {}

    public function transition(WorkOrder $workOrder, string $status, array $attributes, User $user): WorkOrder
    {
        return match ($status) {
            WorkOrder::STATUS_IN_PROGRESS => $this->start($workOrder, $user),
            WorkOrder::STATUS_COMPLETED => $this->completeLegacy($workOrder, $attributes, $user),
            WorkOrder::STATUS_CANCELLED => $this->cancel($workOrder, $user),
            WorkOrder::STATUS_SCHEDULED => throw ValidationException::withMessages([
                'appointment' => 'Use the appointment proposal and Account confirmation workflow to schedule work.',
            ]),
            default => throw ValidationException::withMessages(['status' => 'This work-order transition is not allowed.']),
        };
    }

    public function start(WorkOrder $workOrder, User $user): WorkOrder
    {
        $this->access->authorizeProviderOperation($user, $workOrder);

        return DB::transaction(function () use ($workOrder, $user) {
            ProviderCompany::lockForUpdate()->findOrFail($workOrder->provider_company_id);
            $workOrder = WorkOrder::withoutGlobalScopes()->lockForUpdate()->findOrFail($workOrder->getKey());
            $this->access->authorizeProviderOperation($user, $workOrder);
            if (! in_array($workOrder->status, [
                WorkOrder::STATUS_PENDING, WorkOrder::STATUS_SCHEDULED, WorkOrder::STATUS_REVISION_REQUESTED,
            ], true)) {
                throw ValidationException::withMessages(['status' => 'Work cannot be started in its current state.']);
            }

            $workOrder->forceFill([
                'status' => WorkOrder::STATUS_IN_PROGRESS,
                'started_at' => $workOrder->started_at ?: now(),
            ])->save();
            $request = ServiceRequest::withoutGlobalScopes()->lockForUpdate()->findOrFail($workOrder->service_request_id);
            $request->forceFill(['status' => ServiceRequest::STATUS_IN_PROGRESS])->saveQuietly();
            if ($request->request_type === ServiceRequest::TYPE_MAINTENANCE && $request->maintenance_ticket_id) {
                MaintenanceTicket::withoutGlobalScopes()->whereKey($request->maintenance_ticket_id)
                    ->whereIn('status', [MaintenanceTicket::STATUS_OPEN, MaintenanceTicket::STATUS_IN_PROGRESS])
                    ->update(['status' => MaintenanceTicket::STATUS_IN_PROGRESS]);
            }
            $this->activities->record($workOrder, 'work_started', 'Provider work started.', $user);
            $request->creator?->notify(new MarketplaceNotification([
                'title' => 'Work started', 'work_order_id' => $workOrder->getKey(),
                'service_request_id' => $request->getKey(),
            ]));

            return $workOrder->refresh();
        });
    }

    public function addProgress(WorkOrder $workOrder, string $note, array $evidence, User $user): WorkOrder
    {
        $this->access->authorizeProviderOperation($user, $workOrder);
        if (blank($note)) {
            throw ValidationException::withMessages(['note' => 'A progress note is required.']);
        }

        return DB::transaction(function () use ($workOrder, $note, $evidence, $user) {
            $workOrder = WorkOrder::withoutGlobalScopes()->lockForUpdate()->findOrFail($workOrder->getKey());
            $this->access->authorizeProviderOperation($user, $workOrder);
            if ($workOrder->status !== WorkOrder::STATUS_IN_PROGRESS) {
                throw ValidationException::withMessages(['status' => 'Progress can only be recorded while work is in progress.']);
            }
            $evidenceIds = $this->storeEvidence($workOrder, null, $evidence, $user);
            $this->activities->record($workOrder, 'progress_update', $note, $user, ['evidence_ids' => $evidenceIds]);

            return $workOrder->refresh();
        });
    }

    public function storeEvidence(WorkOrder $workOrder, ?int $submissionId, array $evidence, User $user): array
    {
        $ids = [];
        foreach ($evidence as $item) {
            $record = WorkOrderEvidence::create([
                'work_order_id' => $workOrder->getKey(), 'completion_submission_id' => $submissionId,
                'evidence_type' => $item['evidence_type'] ?? 'other',
                'file_path' => $item['file_path'] ?? null, 'text_value' => $item['text_value'] ?? null,
                'metadata' => $item['metadata'] ?? null, 'uploaded_by' => $user->getKey(),
            ]);
            $ids[] = $record->getKey();
        }

        return $ids;
    }

    private function completeLegacy(WorkOrder $workOrder, array $attributes, User $user): WorkOrder
    {
        $this->access->authorizeProviderOperation($user, $workOrder);

        return DB::transaction(function () use ($workOrder, $attributes, $user) {
            $workOrder = WorkOrder::withoutGlobalScopes()->lockForUpdate()->findOrFail($workOrder->getKey());
            if ($workOrder->completion_review_required) {
                throw ValidationException::withMessages([
                    'completion' => 'Submit completion evidence for Account review; providers cannot directly complete new work orders.',
                ]);
            }
            if ($workOrder->status !== WorkOrder::STATUS_IN_PROGRESS) {
                throw ValidationException::withMessages(['status' => 'Only in-progress legacy work orders may be completed directly.']);
            }
            $workOrder->forceFill([
                'status' => WorkOrder::STATUS_COMPLETED, 'completed_at' => $attributes['completed_at'] ?? now(),
                'completion_notes' => $attributes['completion_notes'] ?? null,
                'completion_evidence' => $attributes['completion_evidence'] ?? null,
            ])->save();
            ServiceRequest::withoutGlobalScopes()->whereKey($workOrder->service_request_id)
                ->update(['status' => ServiceRequest::STATUS_COMPLETED]);
            $this->activities->record($workOrder, 'legacy_work_completed', 'Legacy work order completed.', $user);

            return $workOrder->refresh();
        });
    }

    private function cancel(WorkOrder $workOrder, User $user): WorkOrder
    {
        $this->access->authorizeProviderManager($user, $workOrder);

        return DB::transaction(function () use ($workOrder, $user) {
            $workOrder = WorkOrder::withoutGlobalScopes()->lockForUpdate()->findOrFail($workOrder->getKey());
            if (in_array($workOrder->status, [WorkOrder::STATUS_COMPLETED, WorkOrder::STATUS_CANCELLED], true)) {
                throw ValidationException::withMessages(['status' => 'This work order can no longer be cancelled.']);
            }
            $workOrder->forceFill(['status' => WorkOrder::STATUS_CANCELLED])->save();
            ServiceRequest::withoutGlobalScopes()->whereKey($workOrder->service_request_id)
                ->update(['status' => ServiceRequest::STATUS_CANCELLED]);
            $this->activities->record($workOrder, 'work_order_cancelled', 'Provider cancelled the work order.', $user);

            return $workOrder->refresh();
        });
    }
}
