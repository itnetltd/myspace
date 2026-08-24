<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('unit_assets', function (Blueprint $table) {
            $table->id();

            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->foreignId('asset_item_id')->constrained('asset_items')->cascadeOnDelete();

            $table->unsignedInteger('quantity')->default(1);

            // Current baseline condition for that unit asset
            $table->string('condition_status')->default('Good'); 
            // Excellent | Good | Fair | Damaged | Missing

            $table->text('notes')->nullable();

            // Optional: store photo evidence (path)
            $table->string('photo_path')->nullable();

            $table->timestamps();

            // One row per asset item per unit (clean inventory)
            $table->unique(['unit_id', 'asset_item_id']);
            $table->index(['unit_id', 'condition_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_assets');
    }
};