<?php

namespace App\Models\Concerns;

use App\Models\Account;
use App\Models\Scopes\AccountScope;
use App\Support\CurrentAccount;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

trait BelongsToAccount
{
    protected static function bootBelongsToAccount(): void
    {
        static::addGlobalScope(new AccountScope);

        static::creating(function ($model) {
            $accountId = app(CurrentAccount::class)->id();

            if ($accountId && $model->account_id && (int) $model->account_id !== $accountId) {
                throw new AuthorizationException('The record belongs to another account.');
            }

            if ($accountId) {
                $model->account_id = $accountId;
            }

            static::validateAccountParents($model);
        });

        static::updating(function ($model) {
            $accountId = app(CurrentAccount::class)->id();

            if ($model->isDirty('account_id') || ($accountId && (int) $model->account_id !== $accountId)) {
                throw new AuthorizationException('A record cannot be moved to another account.');
            }

            static::validateAccountParents($model);
        });
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function scopeForAccount($query, Account|int $account)
    {
        $accountId = $account instanceof Account ? $account->getKey() : $account;

        return $query->withoutGlobalScope(AccountScope::class)->where('account_id', $accountId);
    }

    protected static function validateAccountParents($model): void
    {
        if (! method_exists($model, 'accountParentMap')) {
            return;
        }

        foreach ($model->accountParentMap() as $foreignKey => $relatedClass) {
            $relatedId = $model->{$foreignKey};

            if (! $relatedId) {
                continue;
            }

            $related = $relatedClass::withoutGlobalScopes()->find($relatedId);

            if (! $related || (int) $related->account_id !== (int) $model->account_id) {
                throw ValidationException::withMessages([
                    $foreignKey => 'The selected record belongs to another account.',
                ]);
            }
        }
    }
}
