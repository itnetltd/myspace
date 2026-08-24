<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// adjust if your Setting model is in a different namespace

class RentInvoice extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'account_id',
        'lease_id', 'period_start', 'period_end', 'due_date',
        'amount_due', 'amount_paid',
        'late_fee', 'total_due',
        'status', 'notes',
    ];

    /**
     * IMPORTANT: money should be DECIMAL, not float (prevents precision issues).
     */
    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'due_date' => 'date',
        'amount_due' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'late_fee' => 'decimal:2',
        'total_due' => 'decimal:2',
    ];

    protected function accountParentMap(): array
    {
        return ['lease_id' => Lease::class];
    }

    /**
     * Money sanitizers: handle "1,000,000", "1 000 000", "1,000,000 RWF", etc.
     * This protects amount_due correctness even if UI sends formatted numbers.
     */
    public function setAmountDueAttribute($value): void
    {
        $this->attributes['amount_due'] = $this->sanitizeMoney($value);
    }

    public function setAmountPaidAttribute($value): void
    {
        $this->attributes['amount_paid'] = $this->sanitizeMoney($value);
    }

    public function setLateFeeAttribute($value): void
    {
        $this->attributes['late_fee'] = $this->sanitizeMoney($value);
    }

    public function setTotalDueAttribute($value): void
    {
        $this->attributes['total_due'] = $this->sanitizeMoney($value);
    }

    private function sanitizeMoney($value): string
    {
        if ($value === null) {
            return '0';
        }

        $clean = preg_replace('/[^\d.]/', '', (string) $value);

        return $clean === '' ? '0' : $clean;
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(RentPayment::class);
    }

    public function getDisplayNameAttribute(): string
    {
        $lease = $this->lease;

        $unit = $lease?->unit?->unit_code
            ?? $lease?->unit?->name
            ?? 'Unit';

        $tenant = $lease?->tenant?->full_name
            ?? $lease?->tenant_name
            ?? 'Tenant';

        $period = $this->period_start
            ? $this->period_start->format('M Y')
            : 'Period';

        return "{$unit} — {$tenant} — {$period}";
    }

    public function getBalanceAttribute(): float
    {
        $total = $this->total_due !== null
            ? (float) $this->total_due
            : (float) $this->amount_due;

        return max(0, $total - (float) $this->amount_paid);
    }

    public function calculateLateFee(): float
    {
        if (! $this->due_date) {
            return 0.0;
        }

        $enabled = (int) Setting::get('rent.late_fee_enabled', 1) === 1;
        if (! $enabled) {
            return 0.0;
        }

        $graceDays = (int) Setting::get('rent.late_fee_grace_days', 0);
        $lateAfter = Carbon::parse($this->due_date)->addDays($graceDays)->startOfDay();

        if (now()->startOfDay()->lte($lateAfter)) {
            return 0.0;
        }

        $type = (string) Setting::get('rent.late_fee_type', 'fixed');
        $value = (float) Setting::get('rent.late_fee_value', 0);

        $baseDue = (float) $this->amount_due;

        if ($type === 'percent') {
            return round($baseDue * ($value / 100), 2);
        }

        return round($value, 2);
    }

    /**
     * Recalculate payment totals + late fee + status.
     * Uses saveQuietly() to avoid event loops.
     */
    public function refreshPaymentTotals(): void
    {
        $paid = (float) $this->payments()->sum('amount');
        $baseDue = (float) $this->amount_due;

        $lateFee = $this->calculateLateFee();
        $totalDue = $baseDue + $lateFee;

        if ($paid <= 0) {
            $status = 'unpaid';
        } elseif ($paid < $totalDue) {
            $status = 'partial';
        } else {
            $status = 'paid';
        }

        if ($status !== 'paid' && $this->due_date && now()->toDateString() > $this->due_date->toDateString()) {
            $status = 'overdue';
        }

        $this->amount_paid = $paid;
        $this->late_fee = $lateFee;
        $this->total_due = $totalDue;
        $this->status = $status;

        $this->saveQuietly();
    }
}
