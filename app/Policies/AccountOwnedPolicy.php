<?php

namespace App\Policies;

use App\Models\User;
use App\Support\AccountAccess;
use App\Support\CurrentAccount;
use Illuminate\Database\Eloquent\Model;

abstract class AccountOwnedPolicy
{
    protected const VIEW_CAPABILITY = '';

    protected const MANAGE_CAPABILITY = '';

    public function viewAny(User $user): bool
    {
        $account = app(CurrentAccount::class)->forUser($user);

        return $account
            && app(AccountAccess::class)->can($user, $account, static::VIEW_CAPABILITY);
    }

    public function view(User $user, Model $record): bool
    {
        return $this->belongsToCurrentAccount($user, $record)
            && app(AccountAccess::class)->can($user, $record->account_id, static::VIEW_CAPABILITY);
    }

    public function create(User $user): bool
    {
        $account = app(CurrentAccount::class)->forUser($user);

        return $account
            && app(AccountAccess::class)->can($user, $account, static::MANAGE_CAPABILITY);
    }

    public function update(User $user, Model $record): bool
    {
        return $this->belongsToCurrentAccount($user, $record)
            && app(AccountAccess::class)->can($user, $record->account_id, static::MANAGE_CAPABILITY);
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

    protected function hasCapabilityForRecord(User $user, Model $record, string $capability): bool
    {
        return $this->belongsToCurrentAccount($user, $record)
            && app(AccountAccess::class)->can($user, $record->account_id, $capability);
    }
}
