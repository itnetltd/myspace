<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use App\Support\Money;
use Carbon\Carbon;
use Carbon\CarbonInterface;
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

    public function calculateLateFee(): string
    {
        if (! $this->due_date) {
            return '0.00';
        }

        $enabled = (int) Setting::get('rent.late_fee_enabled', 1) === 1;
        if (! $enabled) {
            return '0.00';
        }

        $graceDays = (int) Setting::get('rent.late_fee_grace_days', 0);
        $lateAfter = Carbon::parse($this->due_date)->addDays($graceDays)->startOfDay();

        if (now()->startOfDay()->lte($lateAfter)) {
            return '0.00';
        }

        $baseDueMinor = Money::toMinor($this->amount_due);

        if ($this->principalPaidOnOrBefore($lateAfter) >= $baseDueMinor) {
            return '0.00';
        }

        $type = (string) Setting::get('rent.late_fee_type', 'fixed');
        $value = (string) Setting::get('rent.late_fee_value', '0');

        if ($type === 'percent') {
            return Money::fromMinor(Money::percentage($baseDueMinor, $value));
        }

        return Money::fromMinor(Money::toMinor($value));
    }

    public function principalPaidOnOrBefore(CarbonInterface|string $cutoff): int
    {
        $principalRemaining = Money::toMinor($this->amount_due);
        $principalPaid = 0;

        $this->payments()
            ->whereDate('paid_on', '<=', Carbon::parse($cutoff)->toDateString())
            ->orderBy('paid_on')
            ->orderBy('id')
            ->each(function (RentPayment $payment) use (&$principalRemaining, &$principalPaid) {
                $allocated = min(Money::toMinor($payment->amount), max(0, $principalRemaining));
                $principalPaid += $allocated;
                $principalRemaining -= $allocated;
            });

        return $principalPaid;
    }

    /**
     * Recalculate payment totals + late fee + status.
     * Uses saveQuietly() to avoid event loops.
     */
    public function refreshPaymentTotals(): void
    {
        $paidMinor = 0;
        $this->payments()->orderBy('paid_on')->orderBy('id')->each(
            function (RentPayment $payment) use (&$paidMinor) {
                $paidMinor += Money::toMinor($payment->amount);
            }
        );
        $baseDueMinor = Money::toMinor($this->amount_due);

        $lateFee = $this->calculateLateFee();
        $totalDueMinor = $baseDueMinor + Money::toMinor($lateFee);

        if ($paidMinor <= 0) {
            $status = 'unpaid';
        } elseif ($paidMinor < $totalDueMinor) {
            $status = 'partial';
        } else {
            $status = 'paid';
        }

        $lateAfter = $this->due_date
            ? $this->due_date->copy()->addDays((int) Setting::get('rent.late_fee_grace_days', 0))->startOfDay()
            : null;

        if ($status !== 'paid' && $lateAfter && now()->startOfDay()->gt($lateAfter)) {
            $status = 'overdue';
        }

        $this->amount_paid = Money::fromMinor($paidMinor);
        $this->late_fee = $lateFee;
        $this->total_due = Money::fromMinor($totalDueMinor);
        $this->status = $status;

        $this->saveQuietly();
    }
}
