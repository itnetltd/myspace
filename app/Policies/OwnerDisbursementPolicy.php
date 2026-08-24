<?php

namespace App\Policies;

use App\Models\User;
use App\Support\AccountAccess;
use Illuminate\Database\Eloquent\Model;

class OwnerDisbursementPolicy extends AccountOwnedPolicy
{
    protected const VIEW_CAPABILITY = AccountAccess::VIEW_OWNER_DISBURSEMENTS;

    protected const MANAGE_CAPABILITY = AccountAccess::MANAGE_OWNER_DISBURSEMENTS;

    public function update(User $user, Model $record): bool
    {
        return false;
    }

    public function delete(User $user, Model $record): bool
    {
        return false;
    }
}
