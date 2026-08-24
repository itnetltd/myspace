<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('asset_items', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., Fridge, Bed, TV
            $table->string('category')->nullable(); // Furniture, Appliance, Electronics...
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->string('serial_number')->nullable()->index();
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_cost', 14, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['name', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_items');
    }
};