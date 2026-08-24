<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lease extends Model
{
    use BelongsToAccount;

    /** --------------------
     * Status constants
     * ------------------- */
    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ENDED = 'ended';

    protected $fillable = [
        'account_id',
        'unit_id',
        'tenant_id',
        'start_date',
        'end_date',
        'monthly_rent',
        'deposit',
        'status',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'monthly_rent' => 'decimal:2',
        'deposit' => 'decimal:2',
    ];

    protected function accountParentMap(): array
    {
        return [
            'unit_id' => Unit::class,
            'tenant_id' => Tenant::class,
        ];
    }

    /** --------------------
     * Money sanitizers (FIX amount_due issues)
     * ------------------- */

    /**
     * Normalize values like "1,000,000", "1 000 000", "1,000,000 RWF" to "1000000"
     * so calculations and casts remain correct.
     */
    public function setMonthlyRentAttribute($value): void
    {
        $this->attributes['monthly_rent'] = $this->sanitizeMoney($value);
    }

    public function setDepositAttribute($value): void
    {
        $this->attributes['deposit'] = $this->sanitizeMoney($value);
    }

    private function sanitizeMoney($value): string
    {
        if ($value === null) {
            return '0';
        }

        // Keep digits + dot only. Remove commas, spaces, currency text, etc.
        $clean = preg_replace('/[^\d.]/', '', (string) $value);

        // Avoid empty string
        return $clean === '' ? '0' : $clean;
    }

    /**
     * Optional helper: always get rent as a numeric float for calculations.
     */
    public function monthlyRentValue(): float
    {
        return (float) preg_replace('/[^\d.]/', '', (string) $this->getRawOriginal('monthly_rent'));
    }

    /** --------------------
     * Relationships
     * ------------------- */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(Inspection::class);
    }

    public function rentInvoices(): HasMany
    {
        return $this->hasMany(RentInvoice::class);
    }

    public function maintenanceTickets(): HasMany
    {
        return $this->hasMany(MaintenanceTicket::class);
    }

    /** --------------------
     * Query scopes
     * ------------------- */
    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeEnded($query)
    {
        return $query->where('status', self::STATUS_ENDED);
    }

    /** --------------------
     * Inspection helpers
     * ------------------- */
    public function moveInInspection()
    {
        return $this->inspections()
            ->where('type', 'move_in')
            ->latest('inspected_on');
    }

    public function moveOutInspection()
    {
        return $this->inspections()
            ->where('type', 'move_out')
            ->latest('inspected_on');
    }

    public function hasMoveOutInspection(): bool
    {
        return $this->inspections()
            ->where('type', 'move_out')
            ->exists();
    }

    /** --------------------
     * Status helpers
     * ------------------- */
    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isEnded(): bool
    {
        return $this->status === self::STATUS_ENDED;
    }

    /** --------------------
     * Rent helpers (UPDATED for late fees)
     * ------------------- */
    public function totalRentDue(): float
    {
        $hasTotalDue = \Schema::hasColumn('rent_invoices', 'total_due');

        return (float) ($hasTotalDue
            ? $this->rentInvoices()->sum('total_due')
            : $this->rentInvoices()->sum('amount_due'));
    }

    public function totalRentPaid(): float
    {
        return (float) $this->rentInvoices()->sum('amount_paid');
    }

    public function rentBalance(): float
    {
        $due = $this->totalRentDue();
        $paid = $this->totalRentPaid();

        return max(0, $due - $paid);
    }

    public function totalLateFees(): float
    {
        $hasLateFee = \Schema::hasColumn('rent_invoices', 'late_fee');

        return (float) ($hasLateFee
            ? $this->rentInvoices()->sum('late_fee')
            : 0);
    }

    public function latestRentInvoice()
    {
        return $this->rentInvoices()->latest('period_start');
    }

    public function latestRentInvoiceRecord(): ?RentInvoice
    {
        return $this->latestRentInvoice()->first();
    }

    public function hasOverdueInvoices(): bool
    {
        return $this->rentInvoices()->where('status', 'overdue')->exists();
    }

    public function overdueInvoicesCount(): int
    {
        return (int) $this->rentInvoices()->where('status', 'overdue')->count();
    }

    public function contracts()
    {
        return $this->hasMany(\App\Models\LeaseContract::class);
    }
}
