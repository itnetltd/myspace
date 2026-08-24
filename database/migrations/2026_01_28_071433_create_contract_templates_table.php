<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('contract_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');                 // e.g. Standard Residential Lease
            $table->string('language', 10);         // en, fr, rw
            $table->string('version')->default('1.0');
            $table->boolean('is_active')->default(true);

            $table->longText('body_html');          // HTML with placeholders
            $table->json('required_fields')->nullable(); // optional, for future validation

            $table->timestamps();

            $table->unique(['name','language','version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_templates');
    }
};