<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    public const TYPE_INDIVIDUAL_LANDLORD = 'individual_landlord';

    public const TYPE_PROPERTY_MANAGEMENT_COMPANY = 'property_management_company';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_CLOSED = 'closed';

    public const ROLE_OWNER = 'owner';

    public const ROLE_ADMINISTRATOR = 'administrator';

    public const ROLE_PROPERTY_MANAGER = 'property_manager';

    public const ROLE_ACCOUNTANT = 'accountant';

    public const ROLE_MAINTENANCE = 'maintenance';

    public const ROLE_VIEWER = 'viewer';

    protected $fillable = [
        'name',
        'slug',
        'type',
        'status',
        'phone',
        'email',
        'address',
        'tin',
        'registration_number',
        'logo_path',
        'currency',
        'timezone',
        'created_by',
    ];

    protected $attributes = [
        'status' => self::STATUS_ACTIVE,
        'currency' => 'RWF',
        'timezone' => 'Africa/Kigali',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['role', 'is_active'])
            ->withTimestamps();
    }

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    public function leases(): HasMany
    {
        return $this->hasMany(Lease::class);
    }

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }

    public function propertyOwners(): HasMany
    {
        return $this->hasMany(PropertyOwner::class);
    }

    public function managementAgreements(): HasMany
    {
        return $this->hasMany(ManagementAgreement::class);
    }

    public function contractTemplates(): HasMany
    {
        return $this->hasMany(ContractTemplate::class);
    }

    public function isIndividualLandlord(): bool
    {
        return $this->type === self::TYPE_INDIVIDUAL_LANDLORD;
    }

    public function isPropertyManagementCompany(): bool
    {
        return $this->type === self::TYPE_PROPERTY_MANAGEMENT_COMPANY;
    }
}
