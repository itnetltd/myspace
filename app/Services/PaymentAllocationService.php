<?php

namespace App\Services;

use App\Models\RentInvoice;
use App\Support\Money;

class PaymentAllocationService
{
    public function allocate(RentInvoice $invoice): array
    {
        $principalRemaining = Money::toMinor($invoice->amount_due);
        $lateFeeRemaining = Money::toMinor($invoice->late_fee);
        $allocations = [];

        $payments = $invoice->payments()->orderBy('paid_on')->orderBy('id')->get();

        foreach ($payments as $payment) {
            $remaining = Money::toMinor($payment->amount);
            $principal = min($remaining, max(0, $principalRemaining));
            $remaining -= $principal;
            $principalRemaining -= $principal;

            $lateFee = min($remaining, max(0, $lateFeeRemaining));
            $remaining -= $lateFee;
            $lateFeeRemaining -= $lateFee;

            $allocations[$payment->getKey()] = [
                'payment' => $payment,
                'principal_minor' => $principal,
                'late_fee_minor' => $lateFee,
                'unallocated_minor' => $remaining,
            ];
        }

        return $allocations;
    }
}
