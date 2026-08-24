<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('lease_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lease_id')->constrained('leases')->cascadeOnDelete();
            $table->foreignId('contract_template_id')->constrained('contract_templates')->restrictOnDelete();

            $table->string('language', 10); // en/fr/rw
            $table->string('status')->default('draft'); // draft|final|signed

            // Snapshot of the filled contract text at generation time
            $table->longText('rendered_html');

            // Optional signature images (later)
            $table->string('landlord_signature_path')->nullable();
            $table->string('tenant_signature_path')->nullable();
            $table->date('signed_on')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lease_contracts');
    }
};