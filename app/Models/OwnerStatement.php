<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class OwnerStatement extends Model
{
    use BelongsToAccount;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_FINALIZED = 'finalized';

    protected $attributes = [
        'status' => self::STATUS_DRAFT,
    ];

    protected $fillable = [
        'account_id', 'property_owner_id', 'statement_number', 'statement_month', 'period_start',
        'period_end', 'status', 'currency', 'opening_balance', 'rent_collected',
        'late_fees_collected', 'other_income', 'expenses', 'management_fees',
        'owner_disbursements', 'net_activity', 'closing_balance', 'generated_at',
        'generated_by', 'finalized_at', 'finalized_by', 'notes',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'opening_balance' => 'decimal:2',
        'rent_collected' => 'decimal:2',
        'late_fees_collected' => 'decimal:2',
        'other_income' => 'decimal:2',
        'expenses' => 'decimal:2',
        'management_fees' => 'decimal:2',
        'owner_disbursements' => 'decimal:2',
        'net_activity' => 'decimal:2',
        'closing_balance' => 'decimal:2',
        'generated_at' => 'datetime',
        'finalized_at' => 'datetime',
    ];

    protected function accountParentMap(): array
    {
        return ['property_owner_id' => PropertyOwner::class];
    }

    protected static function booted(): void
    {
        static::saving(function (self $statement) {
            $month = Carbon::parse($statement->statement_month ?? $statement->period_start)->startOfMonth();

            if (! $statement->period_start
                || ! $statement->period_end
                || ! $statement->period_start->isSameDay($month)
                || ! $statement->period_end->isSameDay($month->copy()->endOfMonth())) {
                throw ValidationException::withMessages([
                    'statement_month' => 'Owner statements must cover exactly one calendar month.',
                ]);
            }

            $statement->statement_month = $month->format('Y-m');
        });

        static::updating(function (self $statement) {
            if ($statement->getOriginal('status') === self::STATUS_FINALIZED && $statement->isDirty()) {
                throw ValidationException::withMessages([
                    'status' => 'Finalized owner statements are immutable.',
                ]);
            }
        });

        static::deleting(function (self $statement) {
            if ($statement->status === self::STATUS_FINALIZED) {
                throw ValidationException::withMessages([
                    'status' => 'Finalized owner statements cannot be deleted.',
                ]);
            }
        });
    }

    public function propertyOwner(): BelongsTo
    {
        return $this->belongsTo(PropertyOwner::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(OwnerStatementLine::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(OwnerLedgerEntry::class);
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }
}
