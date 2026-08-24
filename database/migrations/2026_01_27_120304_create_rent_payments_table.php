<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('rent_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rent_invoice_id')->constrained('rent_invoices')->cascadeOnDelete();
            $table->date('paid_on')->required();
            $table->decimal('amount', 14, 2)->required();
            $table->string('method')->nullable(); // cash, bank, momo...
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rent_payments');
    }
};