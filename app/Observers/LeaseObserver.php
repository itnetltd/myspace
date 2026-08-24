<?php

namespace App\Observers;

use App\Models\Lease;
use App\Models\RentInvoice;
use App\Models\Setting;
use Carbon\Carbon;

class LeaseObserver
{
    public function updated(Lease $lease): void
    {
        // Trigger only when status changed to active
        if (! $lease->wasChanged('status')) {
            return;
        }

        if ($lease->status !== 'active') {
            return;
        }

        // Must have monthly rent + start date
        if (! $lease->monthly_rent || (float) $lease->monthly_rent <= 0 || ! $lease->start_date) {
            return;
        }

        $monthsAhead = (int) Setting::get('rent.invoice_months_ahead', 6);
        $dueDay      = (int) Setting::get('rent.due_day', 5);

        $monthsAhead = max(1, min($monthsAhead, 36));
        $dueDay = max(1, min($dueDay, 28));

        // Start generating from lease start month (or current/next month if later)
        $cursor = now()->startOfMonth();
        $leaseStartMonth = Carbon::parse($lease->start_date)->startOfMonth();
        if ($cursor->lt($leaseStartMonth)) {
            $cursor = $leaseStartMonth->copy();
        }

        // For "active" we usually want current month invoice if in that period,
        // but you can switch to next month by uncommenting:
        // $cursor = $cursor->addMonth();

        $leaseEndMonth = $lease->end_date ? Carbon::parse($lease->end_date)->endOfMonth() : null;

        for ($i = 0; $i < $monthsAhead; $i++) {
            $periodStart = $cursor->copy()->startOfMonth();
            $periodEnd   = $cursor->copy()->endOfMonth();

            if ($leaseEndMonth && $periodStart->gt($leaseEndMonth)) {
                break;
            }

            $dueDate = $cursor->copy()->day($dueDay);

            $exists = RentInvoice::query()
                ->where('lease_id', $lease->id)
                ->whereDate('period_start', $periodStart->toDateString())
                ->whereDate('period_end', $periodEnd->toDateString())
                ->exists();

            if (! $exists) {
                RentInvoice::create([
                    'lease_id' => $lease->id,
                    'period_start' => $periodStart->toDateString(),
                    'period_end' => $periodEnd->toDateString(),
                    'due_date' => $dueDate->toDateString(),
                    'amount_due' => (float) $lease->monthly_rent,
                    'amount_paid' => 0,
                    'status' => 'unpaid',
                    'notes' => 'Auto-generated on lease activation.',
                ]);
            }

            $cursor->addMonth();
        }
    }
}