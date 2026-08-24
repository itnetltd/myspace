<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;

class RentPayment extends Model
{
    use BelongsToAccount;

    protected $fillable = ['account_id', 'rent_invoice_id', 'paid_on', 'amount', 'method', 'reference', 'notes'];

    protected $casts = ['paid_on' => 'date'];

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
        static::saved(function (RentPayment $payment) {
            $payment->invoice?->refreshPaymentTotals();
        });

        static::deleted(function (RentPayment $payment) {
            $payment->invoice?->refreshPaymentTotals();
        });
    }
}
