<?php

namespace App\Policies;

use App\Models\SupplyDelivery;
use App\Models\User;
use App\Services\WorkOrderAccessService;
use App\Support\CurrentProviderCompany;
use App\Support\ProviderAccess;

class SupplyDeliveryPolicy
{
    private const VIEW_ROLES = ['owner', 'administrator', 'sales', 'technician', 'viewer'];

    private const OPERATE_ROLES = ['owner', 'administrator', 'sales', 'technician'];

    public function viewAny(User $user): bool
    {
        $company = app(CurrentProviderCompany::class)->forUser($user);

        return $company && app(ProviderAccess::class)->hasRole($user, $company, self::VIEW_ROLES);
    }

    public function view(User $user, SupplyDelivery $delivery): bool
    {
        return app(WorkOrderAccessService::class)->canView(
            $user, $delivery->workOrder()->withoutGlobalScopes()->firstOrFail(),
        );
    }

    public function create(User $user): bool
    {
        $company = app(CurrentProviderCompany::class)->forUser($user);

        return $company && app(ProviderAccess::class)->hasRole($user, $company, self::OPERATE_ROLES);
    }

    public function update(User $user, SupplyDelivery $delivery): bool
    {
        try {
            app(WorkOrderAccessService::class)->authorizeProviderOperation(
                $user, $delivery->workOrder()->withoutGlobalScopes()->firstOrFail(),
            );

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function delete(User $user, SupplyDelivery $delivery): bool
    {
        return false;
    }
}
