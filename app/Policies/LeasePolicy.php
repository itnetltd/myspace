<?php

namespace App\Policies;

use App\Models\User;
use App\Support\AccountAccess;
use Illuminate\Database\Eloquent\Model;

class LeasePolicy extends AccountOwnedPolicy
{
    protected const VIEW_CAPABILITY = AccountAccess::VIEW_LEASES;

    protected const MANAGE_CAPABILITY = AccountAccess::MANAGE_LEASES;

    public function viewStatement(User $user, Model $record): bool
    {
        return $this->hasCapabilityForRecord($user, $record, AccountAccess::VIEW_FINANCIAL_STATEMENTS);
    }
}
