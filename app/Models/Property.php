<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Property extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'account_id', 'property_owner_id', 'name', 'type', 'address', 'sector', 'district', 'owner_name', 'owner_phone', 'notes',
    ];

    protected function accountParentMap(): array
    {
        return ['property_owner_id' => PropertyOwner::class];
    }

    public function propertyOwner(): BelongsTo
    {
        return $this->belongsTo(PropertyOwner::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    public function managementAgreements(): HasMany
    {
        return $this->hasMany(ManagementAgreement::class);
    }
}
