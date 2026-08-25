<?php

namespace App\Services;

use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderActivity;

class WorkOrderActivityService
{
    public function record(WorkOrder $workOrder, string $type, string $description, ?User $actor = null, array $metadata = []): WorkOrderActivity
    {
        return WorkOrderActivity::create([
            'work_order_id' => $workOrder->getKey(), 'activity_type' => $type,
            'actor_user_id' => $actor?->getKey(),
            'provider_company_id' => $actor && app(WorkOrderAccessService::class)->providerMembership($actor, $workOrder)
                ? $workOrder->provider_company_id : null,
            'description' => $description, 'metadata' => $metadata ?: null, 'occurred_at' => now(),
        ]);
    }
}
