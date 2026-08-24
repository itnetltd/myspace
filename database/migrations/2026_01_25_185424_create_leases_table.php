<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('leases', function (Blueprint $table) {
            $table->id();

            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();

            $table->date('start_date');
            $table->date('end_date')->nullable();

            $table->decimal('monthly_rent', 14, 2);
            $table->decimal('deposit', 14, 2)->default(0);

            $table->string('status')->default('active'); // active|ended
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['status', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leases');
    }
};