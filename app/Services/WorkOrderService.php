<?php

namespace App\Services;

use App\Models\ProviderCompany;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Models\WorkOrder;
use App\Notifications\MarketplaceNotification;
use App\Support\ProviderAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkOrderService
{
    public function transition(WorkOrder $workOrder, string $status, array $attributes, User $user): WorkOrder
    {
        $provider = ProviderCompany::findOrFail($workOrder->provider_company_id);
        if (! app(ProviderAccess::class)->hasRole($user, $provider, ProviderAccess::FULFILMENT_ROLES)) {
            abort(403);
        }

        return DB::transaction(function () use ($workOrder, $status, $attributes) {
            $provider = ProviderCompany::lockForUpdate()->findOrFail($workOrder->provider_company_id);
            if (! $provider->isActive()) {
                throw ValidationException::withMessages(['provider' => 'Only active providers may change work orders.']);
            }

            $workOrder = WorkOrder::withoutGlobalScopes()->lockForUpdate()->findOrFail($workOrder->getKey());
            $allowed = match ($workOrder->status) {
                WorkOrder::STATUS_PENDING => [WorkOrder::STATUS_SCHEDULED, WorkOrder::STATUS_IN_PROGRESS, WorkOrder::STATUS_CANCELLED],
                WorkOrder::STATUS_SCHEDULED => [WorkOrder::STATUS_IN_PROGRESS, WorkOrder::STATUS_CANCELLED],
                WorkOrder::STATUS_IN_PROGRESS => [WorkOrder::STATUS_COMPLETED, WorkOrder::STATUS_CANCELLED],
                default => [],
            };
            if (! in_array($status, $allowed, true)) {
                throw ValidationException::withMessages(['status' => 'This work-order transition is not allowed.']);
            }

            $transitionAttributes = match ($status) {
                WorkOrder::STATUS_SCHEDULED => array_intersect_key($attributes, array_flip(['scheduled_start', 'scheduled_completion'])),
                WorkOrder::STATUS_IN_PROGRESS => ['started_at' => $attributes['started_at'] ?? now()],
                WorkOrder::STATUS_COMPLETED => [
                    'completed_at' => $attributes['completed_at'] ?? now(),
                    ...array_intersect_key($attributes, array_flip(['completion_notes', 'completion_evidence'])),
                ],
                WorkOrder::STATUS_CANCELLED => [],
            };

            $workOrder->forceFill([...$transitionAttributes, 'status' => $status])->save();
            $requestStatus = match ($status) {
                WorkOrder::STATUS_IN_PROGRESS => ServiceRequest::STATUS_IN_PROGRESS,
                WorkOrder::STATUS_COMPLETED => ServiceRequest::STATUS_COMPLETED,
                WorkOrder::STATUS_CANCELLED => ServiceRequest::STATUS_CANCELLED,
                default => ServiceRequest::STATUS_QUOTE_ACCEPTED,
            };
            $request = ServiceRequest::withoutGlobalScopes()->lockForUpdate()->findOrFail($workOrder->service_request_id);
            $request->forceFill(['status' => $requestStatus])->saveQuietly();

            if (in_array($status, [WorkOrder::STATUS_SCHEDULED, WorkOrder::STATUS_COMPLETED], true)) {
                $request->creator?->notify(new MarketplaceNotification([
                    'title' => $status === WorkOrder::STATUS_COMPLETED ? 'Work completed' : 'Work scheduled',
                    'work_order_id' => $workOrder->getKey(), 'service_request_id' => $request->getKey(),
                ]));
            }

            return $workOrder->refresh();
        });
    }
}
