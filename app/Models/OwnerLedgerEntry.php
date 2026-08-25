<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use App\Services\FinancialPeriodGuard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class OwnerLedgerEntry extends Model
{
    use BelongsToAccount;

    public const DIRECTION_CREDIT = 'credit';

    public const DIRECTION_DEBIT = 'debit';

    public const TYPE_RENT_INCOME = 'rent_income';

    public const TYPE_LATE_FEE_INCOME = 'late_fee_income';

    public const TYPE_PROPERTY_EXPENSE = 'property_expense';

    public const TYPE_MANAGEMENT_FEE = 'management_fee';

    public const TYPE_OWNER_DISBURSEMENT = 'owner_disbursement';

    public const TYPE_CREDIT_ADJUSTMENT = 'credit_adjustment';

    public const TYPE_DEBIT_ADJUSTMENT = 'debit_adjustment';

    protected $fillable = [
        'account_id', 'property_owner_id', 'property_id', 'unit_id', 'lease_id',
        'entry_number', 'entry_type', 'direction', 'amount', 'currency', 'occurred_on',
        'description', 'source_type', 'source_id', 'source_key', 'owner_statement_id',
        'metadata', 'created_by', 'posted_at', 'locked_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'occurred_on' => 'date',
        'metadata' => 'array',
        'posted_at' => 'datetime',
        'locked_at' => 'datetime',
    ];

    protected function accountParentMap(): array
    {
        return [
            'property_owner_id' => PropertyOwner::class,
            'property_id' => Property::class,
            'unit_id' => Unit::class,
            'lease_id' => Lease::class,
            'owner_statement_id' => OwnerStatement::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $entry) {
            app(FinancialPeriodGuard::class)->ensureOpen(
                (int) $entry->account_id,
                (int) $entry->property_owner_id,
                $entry->occurred_on,
            );
        });

        static::updating(function (self $entry) {
            if ($entry->getOriginal('locked_at') && $entry->isDirty()) {
                throw ValidationException::withMessages([
                    'ledger' => 'Ledger entries included in finalized statements are immutable.',
                ]);
            }

            if ($entry->isDirty(['account_id', 'property_owner_id', 'occurred_on'])) {
                app(FinancialPeriodGuard::class)->ensureOpen(
                    (int) $entry->account_id,
                    (int) $entry->property_owner_id,
                    $entry->occurred_on,
                );
            }
        });

        static::deleting(function (self $entry) {
            if ($entry->locked_at) {
                throw ValidationException::withMessages([
                    'ledger' => 'Ledger entries included in finalized statements cannot be deleted.',
                ]);
            }
        });
    }

    public function propertyOwner(): BelongsTo
    {
        return $this->belongsTo(PropertyOwner::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    public function statement(): BelongsTo
    {
        return $this->belongsTo(OwnerStatement::class, 'owner_statement_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
