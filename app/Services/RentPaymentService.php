<?php

namespace App\Services;

use App\Models\RentPayment;
use Illuminate\Support\Facades\DB;

class RentPaymentService
{
    public function create(array $attributes): RentPayment
    {
        return DB::transaction(fn () => RentPayment::create($attributes));
    }

    public function update(RentPayment $payment, array $attributes): RentPayment
    {
        return DB::transaction(function () use ($payment, $attributes) {
            $payment->fill($attributes)->save();

            return $payment->refresh();
        });
    }
}
