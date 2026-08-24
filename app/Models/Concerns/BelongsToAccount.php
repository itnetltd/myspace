<?php

namespace App\Models\Concerns;

use App\Models\Account;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

trait BelongsToAccount
{
    protected static function bootBelongsToAccount(): void
    {
        static::addGlobalScope('account', function (Builder $query) {
            $accountId = auth()->user()?->current_account_id;

            if ($accountId && static::hasAccountColumn($query->getModel())) {
                $query->where($query->getModel()->getTable().'.account_id', $accountId);
            }
        });

        static::creating(function ($model) {
            if (
                empty($model->account_id)
                && auth()->check()
                && static::hasAccountColumn($model)
            ) {
                $model->account_id = auth()->user()->current_account_id;
            }
        });
    }

    protected static function hasAccountColumn(Model $model): bool
    {
        return Schema::connection($model->getConnectionName())
            ->hasColumn($model->getTable(), 'account_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
