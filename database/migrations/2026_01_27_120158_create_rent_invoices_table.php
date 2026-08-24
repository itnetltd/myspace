<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('rent_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lease_id')->constrained('leases')->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->date('due_date')->nullable();
            $table->decimal('amount_due', 14, 2);
            $table->decimal('amount_paid', 14, 2)->default(0);
            $table->string('status')->default('unpaid'); // unpaid|partial|paid|overdue
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['lease_id','period_start','period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rent_invoices');
    }
};