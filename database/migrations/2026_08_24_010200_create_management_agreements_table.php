<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('management_agreements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_owner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference_number');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('management_fee_type');
            $table->decimal('management_fee_value', 14, 2);
            $table->boolean('rent_collection_enabled')->default(true);
            $table->boolean('deposit_management_enabled')->default(false);
            $table->decimal('maintenance_approval_limit', 14, 2)->nullable();
            $table->string('status')->default('draft');
            $table->string('agreement_document_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['account_id', 'reference_number']);
            $table->index(['account_id', 'status']);
            $table->index(['property_owner_id', 'property_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('management_agreements');
    }
};
