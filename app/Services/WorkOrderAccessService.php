<?php

namespace App\Services;

use App\Models\Account;
use App\Models\ProviderCompany;
use App\Models\ProviderCompanyMembership;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderAssignment;
use App\Support\AccountAccess;
use App\Support\ProviderAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class WorkOrderAccessService
{
    public function providerMembership(User $user, WorkOrder $workOrder): ?ProviderCompanyMembership
    {
        return ProviderCompanyMembership::withoutGlobalScopes()
            ->where('provider_company_id', $workOrder->provider_company_id)
            ->where('user_id', $user->getKey())
            ->where('is_active', true)
            ->first();
    }

    public function authorizeProviderManager(User $user, WorkOrder $workOrder): ProviderCompanyMembership
    {
        $this->ensureActiveProvider($workOrder);
        $membership = $this->providerMembership($user, $workOrder);
        if (! $membership || ! in_array($membership->role, ProviderAccess::MANAGE_COMPANY_ROLES, true)) {
            throw new AuthorizationException('Only provider owners and administrators may manage this operation.');
        }

        return $membership;
    }

    public function authorizeProviderOperation(User $user, WorkOrder $workOrder): ProviderCompanyMembership
    {
        $this->ensureActiveProvider($workOrder);
        $membership = $this->providerMembership($user, $workOrder);
        if (! $membership) {
            throw new AuthorizationException('You are not an active member of this provider company.');
        }
        if (in_array($membership->role, ProviderAccess::MANAGE_COMPANY_ROLES, true)) {
            return $membership;
        }

        $request = ServiceRequest::withoutGlobalScopes()->findOrFail($workOrder->service_request_id);
        if (! in_array($membership->role, $this->eligibleRoles($request->request_type), true)
            || ! WorkOrderAssignment::withoutGlobalScopes()
                ->where('work_order_id', $workOrder->getKey())
                ->where('provider_company_membership_id', $membership->getKey())
                ->whereIn('status', WorkOrderAssignment::ACTIVE_STATUSES)
                ->exists()) {
            throw new AuthorizationException('This work order is not assigned to you for operational execution.');
        }

        return $membership;
    }

    public function authorizeAccount(User $user, WorkOrder $workOrder, string $capability): Account
    {
        $request = ServiceRequest::withoutGlobalScopes()->findOrFail($workOrder->service_request_id);
        $account = Account::findOrFail($request->account_id);
        if (! app(AccountAccess::class)->can($user, $account, $capability)) {
            throw new AuthorizationException('You are not authorized to perform this Account operation.');
        }

        return $account;
    }

    public function canView(User $user, WorkOrder $workOrder): bool
    {
        $request = ServiceRequest::withoutGlobalScopes()->find($workOrder->service_request_id);
        if ($request) {
            $account = Account::find($request->account_id);
            if ($account && app(AccountAccess::class)->can($user, $account, AccountAccess::VIEW_MARKETPLACE)) {
                return true;
            }
        }

        $membership = $this->providerMembership($user, $workOrder);
        if (! $membership) {
            return false;
        }
        if (in_array($membership->role, [...ProviderAccess::MANAGE_COMPANY_ROLES, 'viewer'], true)) {
            return true;
        }

        return WorkOrderAssignment::withoutGlobalScopes()
            ->where('work_order_id', $workOrder->getKey())
            ->where('provider_company_membership_id', $membership->getKey())
            ->whereNotIn('status', [WorkOrderAssignment::STATUS_CANCELLED, WorkOrderAssignment::STATUS_DECLINED])
            ->exists();
    }

    public function eligibleRoles(string $requestType): array
    {
        return match ($requestType) {
            ServiceRequest::TYPE_MAINTENANCE => ['technician', 'owner', 'administrator'],
            ServiceRequest::TYPE_INSPECTION => ['inspector', 'owner', 'administrator'],
            ServiceRequest::TYPE_PRODUCT_SUPPLY => ['sales', 'technician', 'owner', 'administrator'],
            default => [],
        };
    }

    private function ensureActiveProvider(WorkOrder $workOrder): void
    {
        if (! ProviderCompany::whereKey($workOrder->provider_company_id)
            ->where('status', ProviderCompany::STATUS_ACTIVE)->exists()) {
            throw ValidationException::withMessages(['provider' => 'Only active providers may perform work-order operations.']);
        }
    }
}
