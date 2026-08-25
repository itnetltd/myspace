<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $statements = DB::table('owner_statements')->orderBy('id')->get();
        $months = [];

        foreach ($statements as $statement) {
            $start = new DateTimeImmutable((string) $statement->period_start);
            $month = $start->format('Y-m');
            $canonicalStart = $start->modify('first day of this month')->format('Y-m-d');
            $canonicalEnd = $start->modify('last day of this month')->format('Y-m-d');
            $key = $statement->account_id.':'.$statement->property_owner_id.':'.$month;

            if (isset($months[$key])) {
                throw new RuntimeException(
                    "Owner statements {$months[$key]} and {$statement->id} occupy the same calendar month. Resolve them before migrating."
                );
            }

            $months[$key] = $statement->id;

            if ($statement->status === 'finalized'
                && (substr((string) $statement->period_start, 0, 10) !== $canonicalStart
                    || substr((string) $statement->period_end, 0, 10) !== $canonicalEnd)) {
                throw new RuntimeException(
                    "Finalized owner statement {$statement->id} has a non-monthly period and requires human review."
                );
            }
        }

        $lockedOverpayments = DB::table('owner_ledger_entries')
            ->where('source_type', 'rent_payment')
            ->where('source_key', 'unallocated')
            ->whereNotNull('locked_at')
            ->count();
        $finalizedOverpayments = DB::table('owner_ledger_entries')
            ->join('owner_statements', 'owner_statements.id', '=', 'owner_ledger_entries.owner_statement_id')
            ->where('owner_ledger_entries.source_type', 'rent_payment')
            ->where('owner_ledger_entries.source_key', 'unallocated')
            ->where('owner_statements.status', 'finalized')
            ->count();

        if ($lockedOverpayments > 0 || $finalizedOverpayments > 0) {
            throw new RuntimeException(
                'Finalized overpayment ledger entries require human correction; finalized history was not rewritten.'
            );
        }

        Schema::table('owner_statements', function (Blueprint $table) {
            $table->string('statement_month', 7)->default('1970-01');
        });

        foreach ($statements as $statement) {
            $start = new DateTimeImmutable((string) $statement->period_start);
            $month = $start->format('Y-m');
            $canonicalStart = $start->modify('first day of this month')->format('Y-m-d');
            $canonicalEnd = $start->modify('last day of this month')->format('Y-m-d');
            $wasNormalized = substr((string) $statement->period_start, 0, 10) !== $canonicalStart
                || substr((string) $statement->period_end, 0, 10) !== $canonicalEnd;

            if ($wasNormalized && $statement->status === 'draft') {
                DB::table('owner_statement_lines')->where('owner_statement_id', $statement->id)->delete();
                DB::table('owner_ledger_entries')
                    ->where('owner_statement_id', $statement->id)
                    ->whereNull('locked_at')
                    ->update(['owner_statement_id' => null]);
            }

            DB::table('owner_statements')->where('id', $statement->id)->update([
                'statement_month' => $month,
                'period_start' => $canonicalStart,
                'period_end' => $canonicalEnd,
                'notes' => $wasNormalized && $statement->status === 'draft'
                    ? trim(($statement->notes ? $statement->notes."\n" : '').'Period normalized to a calendar month; regenerate this draft.')
                    : $statement->notes,
            ]);
        }

        $unallocatedEntries = DB::table('owner_ledger_entries')
            ->where('source_type', 'rent_payment')
            ->where('source_key', 'unallocated')
            ->whereNull('locked_at')
            ->get();

        foreach ($unallocatedEntries as $entry) {
            if ($entry->owner_statement_id) {
                DB::table('owner_statement_lines')
                    ->where('owner_statement_id', $entry->owner_statement_id)
                    ->where('source_type', 'rent_payment')
                    ->where('source_id', $entry->source_id)
                    ->where('line_type', 'credit_adjustment')
                    ->delete();
                $amount = preg_replace('/[^0-9.]/', '', (string) $entry->amount);
                DB::table('owner_statements')->where('id', $entry->owner_statement_id)->update([
                    'other_income' => DB::raw('other_income - '.$amount),
                    'net_activity' => DB::raw('net_activity - '.$amount),
                    'closing_balance' => DB::raw('closing_balance - '.$amount),
                ]);
            }

            DB::table('owner_ledger_entries')->where('id', $entry->id)->delete();
        }

        Schema::table('owner_statements', function (Blueprint $table) {
            $table->unique(
                ['account_id', 'property_owner_id', 'statement_month'],
                'owner_statement_month_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('owner_statements', function (Blueprint $table) {
            $table->dropUnique('owner_statement_month_unique');
            $table->dropColumn('statement_month');
        });
    }
};
