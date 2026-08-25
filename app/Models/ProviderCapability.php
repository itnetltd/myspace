<?php

namespace App\Models;

use App\Models\Concerns\BelongsToProviderCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class ProviderCapability extends Model
{
    use BelongsToProviderCompany;

    protected $fillable = ['provider_company_id', 'capability'];

    protected static function booted(): void
    {
        static::saving(function (self $capability) {
            if (! in_array($capability->capability, ProviderCompany::CAPABILITIES, true)) {
                throw ValidationException::withMessages(['capability' => 'Unsupported provider capability.']);
            }
        });
    }
}
