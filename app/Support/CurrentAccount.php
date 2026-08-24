<?php

namespace App\Support;

use App\Models\Account;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;

class CurrentAccount
{
    private ?Account $resolved = null;

    private ?int $resolvedForUserId = null;

    public function account(): ?Account
    {
        $user = Auth::user();

        return $user instanceof User ? $this->forUser($user) : null;
    }

    public function id(): ?int
    {
        return $this->account()?->getKey();
    }

    public function forUser(User $user): ?Account
    {
        if ($this->resolvedForUserId === $user->getKey() && $this->resolved) {
            return $this->resolved;
        }

        $query = $user->accounts()
            ->wherePivot('is_active', true)
            ->where('accounts.status', Account::STATUS_ACTIVE);

        $account = $user->current_account_id
            ? (clone $query)->whereKey($user->current_account_id)->first()
            : null;

        $account ??= $query->orderBy('accounts.id')->first();

        if ($account && (int) $user->current_account_id !== (int) $account->getKey()) {
            $user->forceFill(['current_account_id' => $account->getKey()])->saveQuietly();
        }

        $this->resolvedForUserId = $user->getKey();
        $this->resolved = $account;

        return $account;
    }

    public function switch(User $user, int $accountId): Account
    {
        $account = $user->accounts()
            ->wherePivot('is_active', true)
            ->where('accounts.status', Account::STATUS_ACTIVE)
            ->whereKey($accountId)
            ->first();

        if (! $account) {
            throw new AuthorizationException('You do not belong to that account.');
        }

        $user->forceFill(['current_account_id' => $account->getKey()])->save();
        $this->resolvedForUserId = $user->getKey();
        $this->resolved = $account;

        return $account;
    }

    public function forget(): void
    {
        $this->resolvedForUserId = null;
        $this->resolved = null;
    }
}
