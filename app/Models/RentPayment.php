<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RentPayment extends Model
{
    protected $fillable = ['rent_invoice_id','paid_on','amount','method','reference','notes'];

    protected $casts = ['paid_on' => 'date'];

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