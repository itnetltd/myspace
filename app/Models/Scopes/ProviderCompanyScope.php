<?php

namespace App\Models\Scopes;

use App\Support\CurrentProviderCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class ProviderCompanyScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $providerCompanyId = app(CurrentProviderCompany::class)->id();

        if ($providerCompanyId) {
            $builder->where($model->qualifyColumn('provider_company_id'), $providerCompanyId);
        } elseif (Auth::check()) {
            $builder->whereRaw('1 = 0');
        }
    }
}
