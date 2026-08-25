<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('registration_number')->nullable();
            $table->string('tin')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('address')->nullable();
            $table->string('district')->nullable();
            $table->string('country')->default('Rwanda');
            $table->string('logo_path')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('verified_at')->nullable();
            $table->text('verification_notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'name']);
        });

        Schema::create('provider_capabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_company_id')->constrained()->cascadeOnDelete();
            $table->string('capability');
            $table->timestamps();
            $table->unique(['provider_company_id', 'capability']);
        });

        Schema::create('provider_company_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('viewer');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['provider_company_id', 'user_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('current_provider_company_id')->nullable()->constrained('provider_companies')->nullOnDelete();
        });

        Schema::create('provider_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_company_id')->constrained()->cascadeOnDelete();
            $table->string('service_type');
            $table->string('category');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('service_area')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['provider_company_id', 'service_type', 'is_active']);
        });

        Schema::create('supplier_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('sku')->nullable();
            $table->string('category')->nullable();
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->text('description')->nullable();
            $table->decimal('unit_price', 14, 2);
            $table->string('currency', 3)->default('RWF');
            $table->string('stock_status')->default('unknown');
            $table->decimal('stock_quantity', 12, 3)->nullable();
            $table->unsignedInteger('warranty_months')->nullable();
            $table->unsignedInteger('estimated_delivery_days')->nullable();
            $table->string('image_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['provider_company_id', 'sku']);
            $table->index(['provider_company_id', 'category', 'is_active']);
        });

        Schema::create('asset_item_supplier_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_product_id')->constrained()->cascadeOnDelete();
            $table->string('match_type');
            $table->text('notes')->nullable();
            $table->foreignId('matched_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['asset_item_id', 'supplier_product_id']);
        });

        Schema::create('marketplace_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->unsignedSmallInteger('year');
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();
            $table->unique(['type', 'year']);
        });

        Schema::create('service_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->foreignId('property_owner_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lease_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('maintenance_ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('inspection_id')->nullable()->constrained()->nullOnDelete();
            $table->string('request_number')->unique();
            $table->string('request_type');
            $table->string('title');
            $table->text('description');
            $table->string('priority')->default('normal');
            $table->string('status')->default('draft');
            $table->date('required_by')->nullable();
            $table->boolean('owner_approval_required')->default(false);
            $table->timestamp('owner_approved_at')->nullable();
            $table->foreignId('owner_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('owner_approval_reference')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('accepted_quotation_id')->nullable()->unique();
            $table->timestamps();
            $table->index(['account_id', 'status', 'request_type']);
            $table->index(['maintenance_ticket_id', 'status']);
        });

        Schema::create('service_request_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_item_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description');
            $table->decimal('quantity', 12, 3)->default(1);
            $table->string('unit')->nullable();
            $table->string('requested_brand')->nullable();
            $table->string('requested_model')->nullable();
            $table->text('specification')->nullable();
            $table->string('photo_path')->nullable();
            $table->boolean('allow_alternative')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('provider_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('provider_company_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('invited');
            $table->timestamp('invited_at');
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['service_request_id', 'provider_company_id']);
        });

        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('provider_company_id')->constrained()->restrictOnDelete();
            $table->string('quotation_number')->unique();
            $table->string('status')->default('draft');
            $table->string('currency', 3)->default('RWF');
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('delivery_amount', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->date('valid_until')->nullable();
            $table->date('estimated_start_date')->nullable();
            $table->date('estimated_completion_date')->nullable();
            $table->text('warranty_notes')->nullable();
            $table->text('terms')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['service_request_id', 'provider_company_id']);
            $table->index(['provider_company_id', 'status']);
        });

        Schema::table('service_requests', function (Blueprint $table) {
            $table->foreign('accepted_quotation_id')->references('id')->on('quotations')->nullOnDelete();
        });

        Schema::create('quotation_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_request_line_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_product_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description');
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_price', 14, 2);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2);
            $table->boolean('is_alternative')->default(false);
            $table->text('alternative_reason')->nullable();
            $table->string('availability_status')->nullable();
            $table->unsignedInteger('delivery_days')->nullable();
            $table->unsignedInteger('warranty_months')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_request_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('quotation_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('provider_company_id')->constrained()->restrictOnDelete();
            $table->string('work_order_number')->unique();
            $table->string('status')->default('pending');
            $table->timestamp('scheduled_start')->nullable();
            $table->timestamp('scheduled_completion')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('completion_notes')->nullable();
            $table->json('completion_evidence')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('provider_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_request_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('quotation_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('provider_company_id')->constrained()->restrictOnDelete();
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->foreignId('property_owner_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('invoice_number')->unique();
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->string('currency', 3)->default('RWF');
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->string('status')->default('draft');
            $table->string('document_path')->nullable();
            $table->text('notes')->nullable();
            $table->text('variation_reason')->nullable();
            $table->timestamp('variation_approved_at')->nullable();
            $table->foreignId('variation_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('property_expense_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['account_id', 'status']);
            $table->index(['provider_company_id', 'status']);
        });

        Schema::create('provider_invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quotation_line_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description');
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_price', 14, 2);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2);
            $table->timestamps();
        });

        Schema::table('property_expenses', function (Blueprint $table) {
            $table->foreignId('provider_invoice_id')->nullable()->unique()->constrained()->nullOnDelete();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::table('property_expenses', fn (Blueprint $table) => $table->dropConstrainedForeignId('provider_invoice_id'));
        Schema::dropIfExists('provider_invoice_lines');
        Schema::dropIfExists('provider_invoices');
        Schema::dropIfExists('work_orders');
        Schema::dropIfExists('quotation_lines');
        Schema::table('service_requests', fn (Blueprint $table) => $table->dropForeign(['accepted_quotation_id']));
        Schema::dropIfExists('quotations');
        Schema::dropIfExists('provider_invitations');
        Schema::dropIfExists('service_request_lines');
        Schema::dropIfExists('service_requests');
        Schema::dropIfExists('marketplace_sequences');
        Schema::dropIfExists('asset_item_supplier_product');
        Schema::dropIfExists('supplier_products');
        Schema::dropIfExists('provider_services');
        Schema::table('users', fn (Blueprint $table) => $table->dropConstrainedForeignId('current_provider_company_id'));
        Schema::dropIfExists('provider_company_memberships');
        Schema::dropIfExists('provider_capabilities');
        Schema::dropIfExists('provider_companies');
    }
};
