<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PropertyOwner extends Model
{
    use BelongsToAccount;

    public const TYPE_INDIVIDUAL = 'individual';

    public const TYPE_COMPANY = 'company';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'account_id',
        'type',
        'name',
        'phone',
        'email',
        'national_id',
        'tin',
        'registration_number',
        'address',
        'bank_name',
        'bank_account_name',
        'bank_account_number',
        'mobile_money_number',
        'notes',
        'status',
    ];

    protected $attributes = [
        'type' => self::TYPE_INDIVIDUAL,
        'status' => self::STATUS_ACTIVE,
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }

    public function managementAgreements(): HasMany
    {
        return $this->hasMany(ManagementAgreement::class);
    }
}
