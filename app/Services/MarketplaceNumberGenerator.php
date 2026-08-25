<?php

namespace App\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class MarketplaceNumberGenerator
{
    private const PREFIXES = [
        'service_request' => 'SR', 'quotation' => 'QT',
        'work_order' => 'WO', 'provider_invoice' => 'PI',
    ];

    public function next(string $type): string
    {
        abort_unless(isset(self::PREFIXES[$type]), 500, 'Unknown marketplace sequence.');
        $year = (int) now()->format('Y');

        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                $number = DB::transaction(function () use ($type, $year) {
                    $sequence = DB::table('marketplace_sequences')
                        ->where('type', $type)->where('year', $year)
                        ->lockForUpdate()->first();

                    if (! $sequence) {
                        DB::table('marketplace_sequences')->insert([
                            'type' => $type, 'year' => $year, 'last_number' => 1,
                            'created_at' => now(), 'updated_at' => now(),
                        ]);

                        return 1;
                    }

                    $next = (int) $sequence->last_number + 1;
                    DB::table('marketplace_sequences')->where('id', $sequence->id)->update([
                        'last_number' => $next, 'updated_at' => now(),
                    ]);

                    return $next;
                });

                return self::PREFIXES[$type].'-'.$year.'-'.str_pad((string) $number, 6, '0', STR_PAD_LEFT);
            } catch (QueryException $exception) {
                if ($attempt === 2) {
                    throw $exception;
                }
            }
        }

        throw new \RuntimeException('Unable to allocate marketplace number.');
    }
}
