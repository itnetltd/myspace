<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('inspections', function (Blueprint $table) {
            $table->id();

            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();

            // Often linked to a lease (move-in/move-out), but can be null for routine checks
            $table->foreignId('lease_id')->nullable()->constrained('leases')->nullOnDelete();

            $table->string('type'); 
            // move_in | move_out | routine | maintenance

            $table->date('inspected_on');
            $table->string('inspected_by')->nullable(); // name or staff user display
            $table->text('general_notes')->nullable();

            // Optional: overall status summary
            $table->string('summary_status')->nullable(); 
            // e.g., Clean, Minor Issues, Major Issues

            $table->timestamps();

            $table->index(['unit_id', 'type', 'inspected_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspections');
    }
};