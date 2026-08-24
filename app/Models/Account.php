<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('slug')->unique(); // future-proof: public URL / workspace key
            $table->string('type')->default('landlord'); // landlord|agency|brokerage

            $table->string('status')->default('active'); // active|suspended|closed

            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('logo_path')->nullable();

            // audit trail
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['type', 'status']);
        });

        // user ↔ account (many-to-many) to support brokers handling multiple accounts
        Schema::create('account_user', function (Blueprint $table) {
            $table->id();

            $table->foreignId('account_id')
                ->constrained('accounts')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // Keep role as string for simplicity, but standardize values in code
            $table->string('role')->default('staff'); 
            // owner|manager|accountant|maintenance|broker|staff

            $table->boolean('is_active')->default(true); // deactivate a user in one account
            $table->timestamps();

            $table->unique(['account_id','user_id']);
            $table->index(['user_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_user');
        Schema::dropIfExists('accounts');
    }
};