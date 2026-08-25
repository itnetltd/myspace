<?php

namespace App\Policies;

use App\Models\ProviderCompany;
use App\Models\User;
use App\Support\CurrentProviderCompany;
use App\Support\ProviderAccess;

class ProviderCompanyPolicy
{
    public function viewAny(User $user): bool
    {
        return (bool) app(CurrentProviderCompany::class)->forUser($user);
    }

    public function view(User $user, ProviderCompany $company): bool
    {
        return app(ProviderAccess::class)->role($user, $company) !== null;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ProviderCompany $company): bool
    {
        return app(ProviderAccess::class)->hasRole($user, $company, ProviderAccess::MANAGE_COMPANY_ROLES);
    }

    public function delete(User $user, ProviderCompany $company): bool
    {
        return false;
    }
}
