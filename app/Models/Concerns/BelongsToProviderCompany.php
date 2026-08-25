<?php

namespace App\Models\Concerns;

use App\Models\ProviderCompany;
use App\Models\Scopes\ProviderCompanyScope;
use App\Support\CurrentProviderCompany;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToProviderCompany
{
    protected static function bootBelongsToProviderCompany(): void
    {
        static::addGlobalScope(new ProviderCompanyScope);

        static::creating(function ($model) {
            $providerCompanyId = app(CurrentProviderCompany::class)->id();

            if ($providerCompanyId && $model->provider_company_id && (int) $model->provider_company_id !== $providerCompanyId) {
                throw new AuthorizationException('The record belongs to another provider company.');
            }

            if ($providerCompanyId) {
                $model->provider_company_id = $providerCompanyId;
            }
        });

        static::updating(function ($model) {
            $providerCompanyId = app(CurrentProviderCompany::class)->id();

            if ($model->isDirty('provider_company_id') || ($providerCompanyId && (int) $model->provider_company_id !== $providerCompanyId)) {
                throw new AuthorizationException('A record cannot be moved to another provider company.');
            }
        });
    }

    public function providerCompany(): BelongsTo
    {
        return $this->belongsTo(ProviderCompany::class);
    }
}
