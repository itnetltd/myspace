<?php

namespace App\Support;

use App\Models\ProviderCompany;
use App\Models\ProviderCompanyMembership;
use App\Models\User;

class ProviderAccess
{
    public const MANAGE_COMPANY_ROLES = ['owner', 'administrator'];

    public const QUOTE_ROLES = ['owner', 'administrator', 'sales'];

    public const FULFILMENT_ROLES = ['owner', 'administrator', 'technician', 'inspector'];

    public const INVOICE_ROLES = ['owner', 'administrator', 'accountant', 'sales'];

    public function role(User $user, ProviderCompany|int $company): ?string
    {
        $companyId = $company instanceof ProviderCompany ? $company->getKey() : $company;

        return ProviderCompanyMembership::withoutGlobalScopes()
            ->where('provider_company_id', $companyId)
            ->where('user_id', $user->getKey())
            ->where('is_active', true)
            ->value('role');
    }

    public function hasRole(User $user, ProviderCompany|int $company, array $roles): bool
    {
        return in_array($this->role($user, $company), $roles, true);
    }
}
