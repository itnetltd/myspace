<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait BelongsToAccount
{
    protected static function bootBelongsToAccount(): void
    {
        static::addGlobalScope('account', function (Builder $query) {
            $accountId = auth()->user()?->current_account_id;

            if ($accountId) {
                $query->where($query->getModel()->getTable().'.account_id', $accountId);
            }
        });

        static::creating(function ($model) {
            if (empty($model->account_id) && auth()->check()) {
                $model->account_id = auth()->user()->current_account_id;
            }
        });
    }

    public function account()
    {
        return $this->belongsTo(\App\Models\Account::class);
    }
}