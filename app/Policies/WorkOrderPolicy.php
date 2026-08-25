<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkOrder;
use App\Services\WorkOrderAccessService;
use App\Support\CurrentAccount;
use App\Support\CurrentProviderCompany;

class WorkOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return app(CurrentProviderCompany::class)->forUser($user) !== null
            || app(CurrentAccount::class)->forUser($user) !== null;
    }

    public function view(User $user, WorkOrder $workOrder): bool
    {
        return app(WorkOrderAccessService::class)->canView($user, $workOrder);
    }

    public function update(User $user, WorkOrder $workOrder): bool
    {
        try {
            app(WorkOrderAccessService::class)->authorizeProviderOperation($user, $workOrder);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function delete(User $user, WorkOrder $workOrder): bool
    {
        return false;
    }
}
