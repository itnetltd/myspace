<?php

namespace App\Policies;

use App\Models\User;
use App\Support\CurrentProviderCompany;
use App\Support\ProviderAccess;
use Illuminate\Database\Eloquent\Model;

class ProviderOwnedPolicy
{
    protected const ROLES = ProviderAccess::MANAGE_COMPANY_ROLES;

    public function viewAny(User $user): bool
    {
        return (bool) app(CurrentProviderCompany::class)->forUser($user);
    }

    public function view(User $user, Model $record): bool
    {
        $company = app(CurrentProviderCompany::class)->forUser($user);

        return $company && (int) $record->provider_company_id === (int) $company->getKey();
    }

    public function create(User $user): bool
    {
        $company = app(CurrentProviderCompany::class)->forUser($user);

        return $company && app(ProviderAccess::class)->hasRole($user, $company, static::ROLES);
    }

    public function update(User $user, Model $record): bool
    {
        return $this->view($user, $record)
            && app(ProviderAccess::class)->hasRole($user, $record->provider_company_id, static::ROLES);
    }

    public function delete(User $user, Model $record): bool
    {
        return $this->update($user, $record);
    }
}
