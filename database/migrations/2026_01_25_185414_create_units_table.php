<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();

            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();

            $table->string('unit_code'); // e.g., A1, A2, HOUSE-01
            $table->unsignedTinyInteger('bedrooms')->default(0);
            $table->unsignedTinyInteger('bathrooms')->default(0);
            $table->decimal('monthly_rent', 14, 2)->default(0);

            $table->string('status')->default('vacant'); // vacant|occupied
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(['property_id', 'unit_code']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};