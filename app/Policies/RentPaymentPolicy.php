<?php

namespace App\Policies;

use App\Models\User;
use App\Support\AccountAccess;
use Illuminate\Database\Eloquent\Model;

class RentPaymentPolicy extends AccountOwnedPolicy
{
    protected const VIEW_CAPABILITY = AccountAccess::VIEW_PAYMENTS;

    protected const MANAGE_CAPABILITY = AccountAccess::MANAGE_PAYMENTS;

    public function delete(User $user, Model $record): bool
    {
        return false;
    }
}
