<?php

namespace App\Services;

use App\Models\ProviderCompany;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Models\WorkOrder;
use App\Notifications\MarketplaceNotification;
use App\Support\ProviderAccess;
use Illuminate\Validation\ValidationException;

class WorkOrderService
{
    public function transition(WorkOrder $workOrder, string $status, array $attributes, User $user): WorkOrder
    {
        $provider = ProviderCompany::findOrFail($workOrder->provider_company_id);
        if (! app(ProviderAccess::class)->hasRole($user, $provider, ProviderAccess::FULFILMENT_ROLES)) {
            abort(403);
        }

        $allowed = match ($workOrder->status) {
            WorkOrder::STATUS_PENDING => [WorkOrder::STATUS_SCHEDULED, WorkOrder::STATUS_IN_PROGRESS, WorkOrder::STATUS_CANCELLED],
            WorkOrder::STATUS_SCHEDULED => [WorkOrder::STATUS_IN_PROGRESS, WorkOrder::STATUS_CANCELLED],
            WorkOrder::STATUS_IN_PROGRESS => [WorkOrder::STATUS_COMPLETED, WorkOrder::STATUS_CANCELLED],
            default => [],
        };
        if (! in_array($status, $allowed, true)) {
            throw ValidationException::withMessages(['status' => 'This work-order transition is not allowed.']);
        }

        $workOrder->forceFill([...$attributes, 'status' => $status])->save();
        $requestStatus = match ($status) {
            WorkOrder::STATUS_IN_PROGRESS => ServiceRequest::STATUS_IN_PROGRESS,
            WorkOrder::STATUS_COMPLETED => ServiceRequest::STATUS_COMPLETED,
            WorkOrder::STATUS_CANCELLED => ServiceRequest::STATUS_CANCELLED,
            default => ServiceRequest::STATUS_QUOTE_ACCEPTED,
        };
        ServiceRequest::withoutGlobalScopes()->whereKey($workOrder->service_request_id)->update(['status' => $requestStatus]);
        $request = ServiceRequest::withoutGlobalScopes()->find($workOrder->service_request_id);
        if ($request && in_array($status, [WorkOrder::STATUS_SCHEDULED, WorkOrder::STATUS_COMPLETED], true)) {
            $request->creator?->notify(new MarketplaceNotification([
                'title' => $status === WorkOrder::STATUS_COMPLETED ? 'Work completed' : 'Work scheduled',
                'work_order_id' => $workOrder->getKey(), 'service_request_id' => $request->getKey(),
            ]));
        }

        return $workOrder->refresh();
    }
}
