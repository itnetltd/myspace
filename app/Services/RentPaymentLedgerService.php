<?php

namespace App\Services;

use App\Models\OwnerLedgerEntry;
use App\Models\RentInvoice;
use App\Support\Money;

class RentPaymentLedgerService
{
    public function __construct(
        private readonly PaymentAllocationService $allocator,
        private readonly OwnerLedgerService $ledger,
    ) {}

    public function syncInvoice(RentInvoice $invoice): void
    {
        $invoice = RentInvoice::withoutGlobalScopes()
            ->with(['lease.unit.property.propertyOwner', 'account'])
            ->findOrFail($invoice->getKey());

        $lease = $invoice->lease;
        $unit = $lease->unit;
        $property = $unit->property;
        $owner = $property->propertyOwner;

        foreach ($this->allocator->allocate($invoice) as $allocation) {
            $payment = $allocation['payment'];
            $base = [
                'account_id' => $invoice->account_id,
                'property_owner_id' => $owner->getKey(),
                'property_id' => $property->getKey(),
                'unit_id' => $unit->getKey(),
                'lease_id' => $lease->getKey(),
                'direction' => OwnerLedgerEntry::DIRECTION_CREDIT,
                'currency' => $invoice->account->currency,
                'occurred_on' => $payment->paid_on,
                'created_by' => null,
                'metadata' => ['rent_invoice_id' => $invoice->getKey(), 'payment_amount' => $payment->amount],
            ];

            $this->syncComponent($invoice->account_id, $payment->getKey(), 'principal', $allocation['principal_minor'], [
                ...$base,
                'entry_number' => 'LE-RP-'.$payment->getKey().'-P',
                'entry_type' => OwnerLedgerEntry::TYPE_RENT_INCOME,
                'description' => "Rent collected for invoice #{$invoice->getKey()}",
            ]);
            $this->syncComponent($invoice->account_id, $payment->getKey(), 'late_fee', $allocation['late_fee_minor'], [
                ...$base,
                'entry_number' => 'LE-RP-'.$payment->getKey().'-L',
                'entry_type' => OwnerLedgerEntry::TYPE_LATE_FEE_INCOME,
                'description' => "Late fee collected for invoice #{$invoice->getKey()}",
            ]);
            $this->syncComponent($invoice->account_id, $payment->getKey(), 'unallocated', $allocation['unallocated_minor'], [
                ...$base,
                'entry_number' => 'LE-RP-'.$payment->getKey().'-U',
                'entry_type' => OwnerLedgerEntry::TYPE_CREDIT_ADJUSTMENT,
                'description' => "Unallocated payment amount for invoice #{$invoice->getKey()}",
            ]);
        }
    }

    private function syncComponent(int $accountId, int $paymentId, string $key, int $minor, array $attributes): void
    {
        if ($minor === 0) {
            $this->ledger->removeUnlockedComponent($accountId, 'rent_payment', $paymentId, $key);

            return;
        }

        $this->ledger->post(
            ['source_type' => 'rent_payment', 'source_id' => $paymentId, 'source_key' => $key],
            [...$attributes, 'amount' => Money::fromMinor($minor)],
        );
    }
}
