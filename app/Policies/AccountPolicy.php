<?php

namespace App\Policies;

use App\Models\Account;
use App\Models\User;
use App\Support\AccountAccess;

class AccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->accounts()->wherePivot('is_active', true)->exists();
    }

    public function view(User $user, Account $account): bool
    {
        return app(AccountAccess::class)->canView($user, $account);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Account $account): bool
    {
        return app(AccountAccess::class)->canAdminister($user, $account);
    }

    public function delete(User $user, Account $account): bool
    {
        return false;
    }
}
