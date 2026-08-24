<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('inspection_lines', function (Blueprint $table) {
            $table->id();

            $table->foreignId('inspection_id')->constrained('inspections')->cascadeOnDelete();
            $table->foreignId('asset_item_id')->constrained('asset_items')->cascadeOnDelete();

            $table->unsignedInteger('expected_qty')->default(1);
            $table->unsignedInteger('found_qty')->default(1);

            $table->string('condition_status')->default('Good'); 
            // Excellent | Good | Fair | Damaged | Missing

            $table->string('issue_type')->default('none');
            // none | damaged | missing | other

            $table->text('remarks')->nullable();
            $table->string('evidence_photo_path')->nullable();

            $table->timestamps();

            $table->unique(['inspection_id', 'asset_item_id']);
            $table->index(['inspection_id', 'issue_type', 'condition_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_lines');
    }
};