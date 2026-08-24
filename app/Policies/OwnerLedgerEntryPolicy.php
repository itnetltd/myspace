<?php

namespace App\Policies;

use App\Models\User;
use App\Support\AccountAccess;
use App\Support\CurrentAccount;

class OwnerLedgerEntryPolicy extends AccountOwnedPolicy
{
    protected const VIEW_CAPABILITY = AccountAccess::VIEW_OWNER_LEDGER;

    protected const MANAGE_CAPABILITY = AccountAccess::ADJUST_OWNER_LEDGER;

    public function create(User $user): bool
    {
        return false;
    }

    public function adjust(User $user): bool
    {
        $account = app(CurrentAccount::class)->forUser($user);

        return $account && app(AccountAccess::class)->can($user, $account, AccountAccess::ADJUST_OWNER_LEDGER);
    }

    public function update(User $user, \Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public function delete(User $user, \Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }
}
