<?php

namespace App\Support;

use InvalidArgumentException;

final class Money
{
    public static function toMinor(string|int|float|null $amount): int
    {
        if ($amount === null || $amount === '') {
            return 0;
        }

        if (is_float($amount)) {
            $amount = number_format($amount, 2, '.', '');
        }

        $normalized = str_replace([',', ' '], '', (string) $amount);

        if (! preg_match('/^-?\d+(?:\.\d+)?$/', $normalized)) {
            throw new InvalidArgumentException("Invalid money amount: {$amount}");
        }

        $negative = str_starts_with($normalized, '-');
        $normalized = ltrim($normalized, '-');
        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
        $fraction = str_pad($fraction, 3, '0');
        $minor = ((int) $whole * 100) + (int) substr($fraction, 0, 2);

        if ((int) $fraction[2] >= 5) {
            $minor++;
        }

        return $negative ? -$minor : $minor;
    }

    public static function fromMinor(int $minor): string
    {
        $negative = $minor < 0;
        $minor = abs($minor);
        $amount = intdiv($minor, 100).'.'.str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT);

        return $negative ? '-'.$amount : $amount;
    }

    public static function percentage(int $amountMinor, string|int|float|null $percentage): int
    {
        $percentageUnits = self::decimalUnits($percentage, 4);
        $denominator = 100 * 10_000;

        $negative = $amountMinor < 0;
        $amountMinor = abs($amountMinor);
        $whole = intdiv($amountMinor, $denominator) * $percentageUnits;
        $remainder = ($amountMinor % $denominator) * $percentageUnits;
        $result = $whole + intdiv($remainder + intdiv($denominator, 2), $denominator);

        return $negative ? -$result : $result;
    }

    private static function decimalUnits(string|int|float|null $value, int $scale): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        if (is_float($value)) {
            $value = number_format($value, $scale, '.', '');
        }

        $normalized = str_replace([',', ' '], '', (string) $value);

        if (! preg_match('/^\d+(?:\.\d+)?$/', $normalized)) {
            throw new InvalidArgumentException("Invalid decimal value: {$value}");
        }

        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
        $fraction = substr(str_pad($fraction, $scale, '0'), 0, $scale);

        return ((int) $whole * (10 ** $scale)) + (int) $fraction;
    }
}
