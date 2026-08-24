<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->foreignId('property_owner_id')->constrained()->restrictOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lease_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('maintenance_ticket_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('expense_number');
            $table->string('category');
            $table->string('vendor_name')->nullable();
            $table->text('description');
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3);
            $table->date('occurred_on');
            $table->string('status')->default('draft');
            $table->string('reference')->nullable();
            $table->string('document_path')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('owner_approval_required')->default(false);
            $table->timestamp('owner_approved_at')->nullable();
            $table->foreignId('owner_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['account_id', 'expense_number']);
            $table->index(['account_id', 'property_owner_id', 'occurred_on']);
            $table->index(['source_type', 'source_id']);
        });

        Schema::create('owner_disbursements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->foreignId('property_owner_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3);
            $table->date('paid_on');
            $table->string('method')->nullable();
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['account_id', 'property_owner_id', 'paid_on']);
        });

        Schema::create('owner_statements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->foreignId('property_owner_id')->constrained()->restrictOnDelete();
            $table->string('statement_number');
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status')->default('draft');
            $table->string('currency', 3);
            $table->decimal('opening_balance', 14, 2)->default(0);
            $table->decimal('rent_collected', 14, 2)->default(0);
            $table->decimal('late_fees_collected', 14, 2)->default(0);
            $table->decimal('other_income', 14, 2)->default(0);
            $table->decimal('expenses', 14, 2)->default(0);
            $table->decimal('management_fees', 14, 2)->default(0);
            $table->decimal('owner_disbursements', 14, 2)->default(0);
            $table->decimal('net_activity', 14, 2)->default(0);
            $table->decimal('closing_balance', 14, 2)->default(0);
            $table->timestamp('generated_at');
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['account_id', 'statement_number']);
            $table->unique(['account_id', 'property_owner_id', 'period_start', 'period_end'], 'owner_statement_period_unique');
            $table->index(['account_id', 'status', 'period_end']);
        });

        Schema::create('owner_statement_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_statement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('line_type');
            $table->text('description');
            $table->decimal('credit', 14, 2)->default(0);
            $table->decimal('debit', 14, 2)->default(0);
            $table->date('occurred_on');
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['owner_statement_id', 'occurred_on']);
            $table->index(['source_type', 'source_id']);
        });

        Schema::create('owner_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->foreignId('property_owner_id')->constrained()->restrictOnDelete();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lease_id')->nullable()->constrained()->nullOnDelete();
            $table->string('entry_number');
            $table->string('entry_type');
            $table->string('direction');
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3);
            $table->date('occurred_on');
            $table->text('description');
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('source_key')->nullable();
            $table->foreignId('owner_statement_id')->nullable()->constrained()->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at');
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();

            $table->unique(['account_id', 'entry_number']);
            $table->unique(['account_id', 'source_type', 'source_id', 'source_key'], 'owner_ledger_source_unique');
            $table->index(['account_id', 'property_owner_id', 'occurred_on']);
            $table->index(['property_id', 'occurred_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('owner_ledger_entries');
        Schema::dropIfExists('owner_statement_lines');
        Schema::dropIfExists('owner_statements');
        Schema::dropIfExists('owner_disbursements');
        Schema::dropIfExists('property_expenses');
    }
};
