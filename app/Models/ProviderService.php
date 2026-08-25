<?php

namespace App\Models;

use App\Models\Concerns\BelongsToProviderCompany;
use App\Support\CurrentProviderCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class ProviderService extends Model
{
    use BelongsToProviderCompany;

    public const TYPES = ['maintenance', 'inspection', 'supply'];

    protected $fillable = ['provider_company_id', 'service_type', 'category', 'name', 'description', 'service_area', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    protected $attributes = ['is_active' => true];

    protected static function booted(): void
    {
        static::saving(function (self $service) {
            if (! in_array($service->service_type, self::TYPES, true)) {
                throw ValidationException::withMessages(['service_type' => 'Unsupported provider service type.']);
            }
            $capability = $service->service_type === 'supply' ? 'supplier' : $service->service_type;
            $providerCompanyId = $service->provider_company_id ?: app(CurrentProviderCompany::class)->id();
            $eligible = ProviderCapability::withoutGlobalScopes()
                ->where('provider_company_id', $providerCompanyId)
                ->where('capability', $capability)->exists();
            if (! $eligible) {
                throw ValidationException::withMessages(['service_type' => 'The provider company does not have this capability.']);
            }
        });
    }
}
