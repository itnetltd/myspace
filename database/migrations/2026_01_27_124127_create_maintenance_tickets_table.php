<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_tickets', function (Blueprint $table) {
            $table->id();

            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->foreignId('lease_id')->nullable()->constrained('leases')->nullOnDelete();

            $table->string('ticket_no')->unique(); // e.g. MT-2026-0001
            $table->string('title');
            $table->string('category')->nullable(); // plumbing, electrical, painting, internet...
            $table->enum('priority', ['low','medium','high','urgent'])->default('medium');
            $table->enum('status', ['open','in_progress','resolved','closed'])->default('open');

            $table->text('description')->nullable();

            $table->string('reported_by')->nullable(); // tenant name or staff
            $table->date('reported_on')->nullable();
            $table->date('resolved_on')->nullable();

            $table->decimal('estimated_cost', 14, 2)->nullable();
            $table->decimal('actual_cost', 14, 2)->nullable();

            $table->string('photo_path')->nullable(); // single image evidence
            $table->text('internal_notes')->nullable(); // visible only to staff

            $table->timestamps();

            $table->index(['unit_id','status']);
            $table->index(['lease_id','status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_tickets');
    }
};