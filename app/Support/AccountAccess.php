<?php

namespace App\Support;

use App\Models\Account;
use App\Models\User;

class AccountAccess
{
    public function role(User $user, Account|int $account): ?string
    {
        $accountId = $account instanceof Account ? $account->getKey() : $account;

        return $user->accounts()
            ->wherePivot('is_active', true)
            ->whereKey($accountId)
            ->value('account_user.role');
    }

    public function canView(User $user, Account|int $account): bool
    {
        return $this->role($user, $account) !== null;
    }

    public function canWrite(User $user, Account|int $account): bool
    {
        return in_array($this->role($user, $account), [
            Account::ROLE_OWNER,
            Account::ROLE_ADMINISTRATOR,
            Account::ROLE_PROPERTY_MANAGER,
            Account::ROLE_ACCOUNTANT,
            Account::ROLE_MAINTENANCE,
        ], true);
    }

    public function canAdminister(User $user, Account|int $account): bool
    {
        return in_array($this->role($user, $account), [
            Account::ROLE_OWNER,
            Account::ROLE_ADMINISTRATOR,
        ], true);
    }
}
