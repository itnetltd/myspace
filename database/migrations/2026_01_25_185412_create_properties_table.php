<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->string('name');                // e.g., MySpaces Kicukiro House
            $table->string('type')->default('house'); // house|apartment|villa
            $table->string('address')->nullable();
            $table->string('sector')->nullable();
            $table->string('district')->nullable();
            $table->string('owner_name')->nullable();
            $table->string('owner_phone')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['type', 'district', 'sector']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};