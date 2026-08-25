<?php

namespace App\Services;

use App\Support\Money;
use Illuminate\Validation\ValidationException;

class QuotationCalculator
{
    public function calculate(array $lines, string|int|float|null $delivery = null): array
    {
        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => 'At least one quotation line is required.']);
        }

        $normalized = [];
        $subtotal = $tax = $discount = 0;

        foreach ($lines as $index => $line) {
            $quantityUnits = $this->quantityUnits($line['quantity'] ?? null);
            $unitPrice = Money::toMinor($line['unit_price'] ?? null);
            $lineTax = Money::toMinor($line['tax_amount'] ?? null);
            $lineDiscount = Money::toMinor($line['discount_amount'] ?? null);

            if ($quantityUnits <= 0 || $unitPrice < 0 || $lineTax < 0 || $lineDiscount < 0) {
                throw ValidationException::withMessages(["lines.{$index}" => 'Quantity and monetary values must be valid and non-negative.']);
            }

            $base = intdiv(($unitPrice * $quantityUnits) + 500, 1000);
            $lineTotal = $base + $lineTax - $lineDiscount;

            if ($lineTotal < 0) {
                throw ValidationException::withMessages(["lines.{$index}.discount_amount" => 'Discount cannot exceed the line value and tax.']);
            }

            $subtotal += $base;
            $tax += $lineTax;
            $discount += $lineDiscount;
            $normalized[] = [
                ...$line,
                'quantity' => number_format($quantityUnits / 1000, 3, '.', ''),
                'unit_price' => Money::fromMinor($unitPrice),
                'tax_amount' => Money::fromMinor($lineTax),
                'discount_amount' => Money::fromMinor($lineDiscount),
                'line_total' => Money::fromMinor($lineTotal),
            ];
        }

        $deliveryMinor = Money::toMinor($delivery);
        if ($deliveryMinor < 0) {
            throw ValidationException::withMessages(['delivery_amount' => 'Delivery amount cannot be negative.']);
        }

        return [
            'lines' => $normalized,
            'subtotal' => Money::fromMinor($subtotal),
            'tax_amount' => Money::fromMinor($tax),
            'discount_amount' => Money::fromMinor($discount),
            'delivery_amount' => Money::fromMinor($deliveryMinor),
            'total_amount' => Money::fromMinor($subtotal + $tax - $discount + $deliveryMinor),
        ];
    }

    private function quantityUnits(string|int|float|null $quantity): int
    {
        $normalized = str_replace([',', ' '], '', (string) $quantity);
        if (! preg_match('/^\d+(?:\.\d{1,3})?$/', $normalized)) {
            return -1;
        }
        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');

        return ((int) $whole * 1000) + (int) str_pad($fraction, 3, '0');
    }
}
