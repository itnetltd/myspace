<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class ManagementAgreement extends Model
{
    use BelongsToAccount;

    public const FEE_PERCENTAGE = 'percentage';

    public const FEE_FIXED = 'fixed';

    public const FEE_PERCENTAGE_PLUS_FIXED = 'percentage_plus_fixed';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_TERMINATED = 'terminated';

    protected $fillable = [
        'account_id',
        'property_owner_id',
        'property_id',
        'reference_number',
        'start_date',
        'end_date',
        'management_fee_type',
        'management_fee_value',
        'rent_collection_enabled',
        'deposit_management_enabled',
        'maintenance_approval_limit',
        'status',
        'agreement_document_path',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'management_fee_value' => 'decimal:2',
        'rent_collection_enabled' => 'boolean',
        'deposit_management_enabled' => 'boolean',
        'maintenance_approval_limit' => 'decimal:2',
    ];

    protected function accountParentMap(): array
    {
        return [
            'property_owner_id' => PropertyOwner::class,
            'property_id' => Property::class,
        ];
    }

    protected static function booted(): void
    {
        $validateAgreement = function (self $agreement) {
            $account = Account::query()->find($agreement->account_id);

            if (! $account?->isPropertyManagementCompany()) {
                throw ValidationException::withMessages([
                    'account_id' => 'Management agreements are only available to property management companies.',
                ]);
            }

            if (! $agreement->property_id) {
                return;
            }

            $property = Property::withoutGlobalScopes()->find($agreement->property_id);

            if (! $property || (int) $property->property_owner_id !== (int) $agreement->property_owner_id) {
                throw ValidationException::withMessages([
                    'property_id' => 'The selected property does not belong to this owner.',
                ]);
            }
        };

        static::creating($validateAgreement);
        static::updating($validateAgreement);
    }

    public function propertyOwner(): BelongsTo
    {
        return $this->belongsTo(PropertyOwner::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
