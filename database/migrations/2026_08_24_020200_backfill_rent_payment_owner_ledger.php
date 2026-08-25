<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $toMinor = static function ($amount): int {
            $normalized = str_replace([',', ' '], '', (string) $amount);
            [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
            $fraction = substr(str_pad($fraction, 2, '0'), 0, 2);

            return ((int) $whole * 100) + (int) $fraction;
        };
        $fromMinor = static fn (int $minor): string => intdiv($minor, 100).'.'.str_pad(
            (string) ($minor % 100),
            2,
            '0',
            STR_PAD_LEFT,
        );

        DB::table('rent_invoices')
            ->join('leases', 'leases.id', '=', 'rent_invoices.lease_id')
            ->join('units', 'units.id', '=', 'leases.unit_id')
            ->join('properties', 'properties.id', '=', 'units.property_id')
            ->join('accounts', 'accounts.id', '=', 'rent_invoices.account_id')
            ->orderBy('rent_invoices.id')
            ->select([
                'rent_invoices.id', 'rent_invoices.account_id', 'rent_invoices.amount_due',
                'rent_invoices.late_fee', 'leases.id as lease_id', 'units.id as unit_id',
                'properties.id as property_id', 'properties.property_owner_id', 'accounts.currency',
            ])
            ->each(function ($invoice) use ($toMinor, $fromMinor) {
                $principalRemaining = $toMinor($invoice->amount_due);
                $lateFeeRemaining = $toMinor($invoice->late_fee);
                $payments = DB::table('rent_payments')
                    ->where('rent_invoice_id', $invoice->id)
                    ->orderBy('paid_on')
                    ->orderBy('id')
                    ->get();

                foreach ($payments as $payment) {
                    $postedAt = $payment->created_at ?? now();
                    $remaining = $toMinor($payment->amount);
                    $principal = min($remaining, max(0, $principalRemaining));
                    $remaining -= $principal;
                    $principalRemaining -= $principal;
                    $lateFee = min($remaining, max(0, $lateFeeRemaining));
                    $remaining -= $lateFee;
                    $lateFeeRemaining -= $lateFee;

                    foreach ([
                        'principal' => [$principal, 'P', 'rent_income', 'Rent collected'],
                        'late_fee' => [$lateFee, 'L', 'late_fee_income', 'Late fee collected'],
                    ] as $sourceKey => [$minor, $suffix, $entryType, $description]) {
                        if ($minor === 0) {
                            continue;
                        }

                        DB::table('owner_ledger_entries')->updateOrInsert(
                            [
                                'account_id' => $invoice->account_id,
                                'source_type' => 'rent_payment',
                                'source_id' => $payment->id,
                                'source_key' => $sourceKey,
                            ],
                            [
                                'property_owner_id' => $invoice->property_owner_id,
                                'property_id' => $invoice->property_id,
                                'unit_id' => $invoice->unit_id,
                                'lease_id' => $invoice->lease_id,
                                'entry_number' => 'LE-RP-'.$payment->id.'-'.$suffix,
                                'entry_type' => $entryType,
                                'direction' => 'credit',
                                'amount' => $fromMinor($minor),
                                'currency' => $invoice->currency,
                                'occurred_on' => $payment->paid_on,
                                'description' => $description.' for invoice #'.$invoice->id,
                                'metadata' => json_encode([
                                    'rent_invoice_id' => $invoice->id,
                                    'payment_amount' => $payment->amount,
                                    'unallocated_amount' => $fromMinor($remaining),
                                ], JSON_THROW_ON_ERROR),
                                'created_by' => null,
                                'posted_at' => $postedAt,
                                'created_at' => $postedAt,
                                'updated_at' => $payment->updated_at ?? $postedAt,
                            ],
                        );
                    }
                }
            });
    }

    public function down(): void
    {
        // Intentionally non-destructive: financial entries may have been created after this backfill ran.
    }
};
