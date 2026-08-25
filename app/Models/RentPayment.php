<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use App\Services\FinancialPeriodGuard;
use App\Services\RentPaymentLedgerService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class RentPayment extends Model
{
    use BelongsToAccount;

    protected $fillable = ['account_id', 'rent_invoice_id', 'paid_on', 'amount', 'method', 'reference', 'notes'];

    protected $casts = ['paid_on' => 'date', 'amount' => 'decimal:2'];

    protected function accountParentMap(): array
    {
        return ['rent_invoice_id' => RentInvoice::class];
    }

    public function invoice()
    {
        return $this->belongsTo(RentInvoice::class, 'rent_invoice_id');
    }

    protected static function booted(): void
    {
        static::creating(function (RentPayment $payment) {
            if (! Schema::hasTable('owner_statements')) {
                return;
            }

            $invoice = RentInvoice::withoutGlobalScopes()
                ->with('lease.unit.property')
                ->find($payment->rent_invoice_id);

            if ($invoice?->lease?->unit?->property?->property_owner_id) {
                app(FinancialPeriodGuard::class)->ensureOpen(
                    (int) $payment->account_id,
                    (int) $invoice->lease->unit->property->property_owner_id,
                    $payment->paid_on,
                );
            }
        });

        static::updating(function (RentPayment $payment) {
            if ($payment->isDirty('rent_invoice_id')) {
                throw ValidationException::withMessages([
                    'rent_invoice_id' => 'A recorded payment cannot be moved to another invoice.',
                ]);
            }

            if ($payment->isDirty(['amount', 'paid_on']) && Schema::hasTable('owner_ledger_entries')) {
                $paymentIds = static::withoutGlobalScopes()
                    ->where('rent_invoice_id', $payment->rent_invoice_id)
                    ->pluck('id');
                $hasLockedAllocation = OwnerLedgerEntry::withoutGlobalScopes()
                    ->where('source_type', 'rent_payment')
                    ->whereIn('source_id', $paymentIds)
                    ->whereNotNull('locked_at')
                    ->exists();

                if ($hasLockedAllocation) {
                    throw ValidationException::withMessages([
                        'payment' => 'A finalized owner statement has locked this invoice allocation. Record an adjustment instead.',
                    ]);
                }

                if ($payment->isDirty('paid_on') && Schema::hasTable('owner_statements')) {
                    $invoice = RentInvoice::withoutGlobalScopes()
                        ->with('lease.unit.property')
                        ->find($payment->rent_invoice_id);

                    if ($invoice?->lease?->unit?->property?->property_owner_id) {
                        app(FinancialPeriodGuard::class)->ensureOpen(
                            (int) $payment->account_id,
                            (int) $invoice->lease->unit->property->property_owner_id,
                            $payment->paid_on,
                        );
                    }
                }
            }
        });

        static::saved(function (RentPayment $payment) {
            $payment->invoice?->refreshPaymentTotals();

            if (Schema::hasTable('owner_ledger_entries')) {
                app(RentPaymentLedgerService::class)->syncInvoice($payment->invoice);
            }
        });

        static::deleting(function () {
            throw ValidationException::withMessages([
                'payment' => 'Recorded payments cannot be deleted; create an adjustment for corrections.',
            ]);
        });
    }
}
