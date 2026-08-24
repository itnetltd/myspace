<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->string('tin')->nullable();
            $table->string('registration_number')->nullable();
            $table->string('currency', 3)->default('RWF');
            $table->string('timezone')->default('Africa/Kigali');
        });

        Schema::create('property_owners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('national_id')->nullable();
            $table->string('tin')->nullable();
            $table->string('registration_number')->nullable();
            $table->string('address')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_account_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('mobile_money_number')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();

            $table->index(['account_id', 'status']);
            $table->index(['account_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_owners');

        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn(['tin', 'registration_number', 'currency', 'timezone']);
        });
    }
};
