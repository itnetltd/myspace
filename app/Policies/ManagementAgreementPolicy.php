<?php

namespace App\Policies;

use App\Models\User;
use App\Support\AccountAccess;
use App\Support\CurrentAccount;
use Illuminate\Database\Eloquent\Model;

class ManagementAgreementPolicy extends AccountOwnedPolicy
{
    protected const VIEW_CAPABILITY = AccountAccess::VIEW_AGREEMENTS;

    protected const MANAGE_CAPABILITY = AccountAccess::MANAGE_AGREEMENTS;

    public function viewAny(User $user): bool
    {
        return (bool) app(CurrentAccount::class)->forUser($user)?->isPropertyManagementCompany()
            && parent::viewAny($user);
    }

    public function view(User $user, Model $record): bool
    {
        return $record->account?->isPropertyManagementCompany()
            && parent::view($user, $record);
    }

    public function create(User $user): bool
    {
        return (bool) app(CurrentAccount::class)->forUser($user)?->isPropertyManagementCompany()
            && parent::create($user);
    }

    public function update(User $user, Model $record): bool
    {
        return $record->account?->isPropertyManagementCompany()
            && parent::update($user, $record);
    }
}
