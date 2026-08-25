<?php

namespace App\Policies;

use App\Models\User;
use App\Support\AccountAccess;
use App\Support\CurrentAccount;
use Illuminate\Database\Eloquent\Model;

class MarketplaceAccountPolicy
{
    public function viewAny(User $user): bool
    {
        $account = app(CurrentAccount::class)->forUser($user);

        return $account && app(AccountAccess::class)->can($user, $account, AccountAccess::VIEW_MARKETPLACE);
    }

    public function view(User $user, Model $record): bool
    {
        $account = app(CurrentAccount::class)->forUser($user);

        return $account && (int) $record->account_id === (int) $account->getKey()
            && app(AccountAccess::class)->can($user, $account, AccountAccess::VIEW_MARKETPLACE);
    }

    public function create(User $user): bool
    {
        $account = app(CurrentAccount::class)->forUser($user);

        return $account && app(AccountAccess::class)->can($user, $account, AccountAccess::MANAGE_MARKETPLACE);
    }

    public function update(User $user, Model $record): bool
    {
        return $this->view($user, $record)
            && app(AccountAccess::class)->can($user, $record->account_id, AccountAccess::MANAGE_MARKETPLACE);
    }

    public function delete(User $user, Model $record): bool
    {
        return false;
    }
}
