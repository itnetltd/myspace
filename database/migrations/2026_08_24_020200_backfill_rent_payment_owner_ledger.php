<?php

use App\Models\RentInvoice;
use App\Services\RentPaymentLedgerService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        RentInvoice::withoutGlobalScopes()->orderBy('id')->each(function (RentInvoice $invoice) {
            app(RentPaymentLedgerService::class)->syncInvoice($invoice);
        });
    }

    public function down(): void
    {
        DB::table('owner_ledger_entries')->where('source_type', 'rent_payment')->delete();
    }
};
