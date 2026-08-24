<?php

namespace App\Policies;

use App\Models\User;
use App\Support\AccountAccess;
use App\Support\CurrentAccount;
use Illuminate\Database\Eloquent\Model;

class AccountOwnedPolicy
{
    public function viewAny(User $user): bool
    {
        $account = app(CurrentAccount::class)->forUser($user);

        return $account && app(AccountAccess::class)->canView($user, $account);
    }

    public function view(User $user, Model $record): bool
    {
        return $this->belongsToCurrentAccount($user, $record)
            && app(AccountAccess::class)->canView($user, $record->account_id);
    }

    public function create(User $user): bool
    {
        $account = app(CurrentAccount::class)->forUser($user);

        return $account && app(AccountAccess::class)->canWrite($user, $account);
    }

    public function update(User $user, Model $record): bool
    {
        return $this->belongsToCurrentAccount($user, $record)
            && app(AccountAccess::class)->canWrite($user, $record->account_id);
    }

    public function delete(User $user, Model $record): bool
    {
        return $this->update($user, $record);
    }

    public function deleteAny(User $user): bool
    {
        return $this->create($user);
    }

    public function restore(User $user, Model $record): bool
    {
        return $this->update($user, $record);
    }

    public function forceDelete(User $user, Model $record): bool
    {
        return $this->delete($user, $record);
    }

    protected function belongsToCurrentAccount(User $user, Model $record): bool
    {
        $account = app(CurrentAccount::class)->forUser($user);

        return $account && (int) $record->account_id === (int) $account->getKey();
    }
}
