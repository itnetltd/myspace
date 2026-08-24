<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class OwnerDisbursement extends Model
{
    use BelongsToAccount;

    public const METHODS = [
        'bank_transfer' => 'Bank Transfer',
        'mobile_money' => 'Mobile Money',
        'cash' => 'Cash',
        'other' => 'Other',
    ];

    protected $fillable = [
        'account_id', 'property_owner_id', 'amount', 'currency', 'paid_on',
        'method', 'reference', 'notes', 'created_by',
    ];

    protected $casts = ['amount' => 'decimal:2', 'paid_on' => 'date'];

    protected function accountParentMap(): array
    {
        return ['property_owner_id' => PropertyOwner::class];
    }

    protected static function booted(): void
    {
        static::saving(function (self $disbursement) {
            if (Money::toMinor($disbursement->amount) <= 0) {
                throw ValidationException::withMessages(['amount' => 'The disbursement amount must be greater than zero.']);
            }

            if ($disbursement->exists && $disbursement->isDirty()) {
                throw ValidationException::withMessages([
                    'record' => 'Recorded owner disbursements are immutable; use an adjustment for corrections.',
                ]);
            }
        });

        static::deleting(function () {
            throw ValidationException::withMessages([
                'record' => 'Recorded owner disbursements cannot be deleted; use an adjustment.',
            ]);
        });
    }

    public function propertyOwner(): BelongsTo
    {
        return $this->belongsTo(PropertyOwner::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
