<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class ProviderCompany extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_REJECTED = 'rejected';

    public const CAPABILITIES = ['supplier', 'maintenance', 'inspection'];

    protected $fillable = [
        'name', 'slug', 'registration_number', 'tin', 'phone', 'email', 'website',
        'address', 'district', 'country', 'logo_path', 'status', 'verified_at', 'verification_notes',
    ];

    protected $casts = ['verified_at' => 'datetime'];

    protected $attributes = ['status' => self::STATUS_PENDING, 'country' => 'Rwanda'];

    protected static function booted(): void
    {
        static::saving(function (self $company) {
            if ($company->verified_at && $company->status !== self::STATUS_ACTIVE) {
                throw ValidationException::withMessages(['verified_at' => 'Only an active provider can be recorded as verified.']);
            }
        });
    }

    public function capabilities(): HasMany
    {
        return $this->hasMany(ProviderCapability::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(ProviderCompanyMembership::class);
    }

    public function staffInvitations(): HasMany
    {
        return $this->hasMany(ProviderStaffInvitation::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'provider_company_memberships')->withPivot(['role', 'is_active'])->withTimestamps();
    }

    public function services(): HasMany
    {
        return $this->hasMany(ProviderService::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(SupplierProduct::class);
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
