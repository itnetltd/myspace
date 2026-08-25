<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->foreignId('owner_approved_quotation_id')->nullable()->after('owner_approval_required')
                ->constrained('quotations')->nullOnDelete();
            $table->decimal('owner_approved_amount', 14, 2)->nullable()->after('owner_approved_quotation_id');
            $table->string('owner_approved_currency', 3)->nullable()->after('owner_approved_amount');
        });

        Schema::table('provider_invoices', function (Blueprint $table) {
            $table->decimal('delivery_amount', 14, 2)->default(0)->after('discount_amount');
        });

        Schema::create('provider_staff_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_company_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('role');
            $table->string('status')->default('pending');
            $table->string('token_hash', 64)->unique();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('invited_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['provider_company_id', 'email', 'status'], 'provider_staff_invitation_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_staff_invitations');

        Schema::table('provider_invoices', function (Blueprint $table) {
            $table->dropColumn('delivery_amount');
        });

        Schema::table('service_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_approved_quotation_id');
            $table->dropColumn(['owner_approved_amount', 'owner_approved_currency']);
        });
    }
};
