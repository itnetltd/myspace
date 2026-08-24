<?php

namespace App\Models\Scopes;

use App\Support\CurrentAccount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class AccountScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $accountId = app(CurrentAccount::class)->id();

        if ($accountId) {
            $builder->where($model->qualifyColumn('account_id'), $accountId);

            return;
        }

        if (Auth::check()) {
            $builder->whereRaw('1 = 0');
        }
    }
}
