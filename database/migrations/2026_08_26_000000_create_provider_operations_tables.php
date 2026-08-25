<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->boolean('completion_review_required')->default(true)->after('status');
        });

        DB::table('work_orders')->update(['completion_review_required' => false]);

        Schema::create('work_order_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('provider_company_id')->constrained()->restrictOnDelete();
            $table->foreignId('provider_company_membership_id')->constrained()->restrictOnDelete();
            $table->string('assignment_type');
            $table->boolean('is_primary')->default(false);
            $table->string('status')->default('assigned');
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['work_order_id', 'status']);
            $table->index(['provider_company_membership_id', 'status'], 'work_assignment_member_status');
        });

        Schema::create('service_appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('provider_company_id')->constrained()->restrictOnDelete();
            $table->timestamp('scheduled_start');
            $table->timestamp('scheduled_end');
            $table->string('status')->default('proposed');
            $table->text('location_notes')->nullable();
            $table->text('access_instructions')->nullable();
            $table->foreignId('proposed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('proposed_at');
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->text('reschedule_notes')->nullable();
            $table->timestamps();

            $table->index(['work_order_id', 'status']);
            $table->index(['provider_company_id', 'scheduled_start']);
        });

        Schema::create('work_order_completion_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('submission_number');
            $table->text('summary');
            $table->text('provider_notes')->nullable();
            $table->string('status')->default('submitted');
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();

            $table->unique(['work_order_id', 'submission_number'], 'work_submission_sequence');
            $table->index(['work_order_id', 'status']);
        });

        Schema::table('work_orders', function (Blueprint $table) {
            $table->foreignId('accepted_completion_submission_id')->nullable()->after('completion_review_required')
                ->constrained('work_order_completion_submissions')->nullOnDelete();
        });

        Schema::create('work_order_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('completion_submission_id')->nullable()
                ->constrained('work_order_completion_submissions')->cascadeOnDelete();
            $table->string('evidence_type');
            $table->string('file_path')->nullable();
            $table->text('text_value')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['work_order_id', 'evidence_type']);
        });

        Schema::create('supply_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('provider_company_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('preparing');
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->string('delivery_reference')->nullable();
            $table->string('recipient_name')->nullable();
            $table->foreignId('assigned_membership_id')->nullable()
                ->constrained('provider_company_memberships')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['work_order_id', 'status']);
            $table->index(['provider_company_id', 'status']);
        });

        Schema::create('work_order_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $table->string('activity_type');
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('provider_company_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description');
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['work_order_id', 'occurred_at']);
        });

        Schema::table('inspections', function (Blueprint $table) {
            $table->foreignId('external_work_order_id')->nullable()->unique()->constrained('work_orders')->nullOnDelete();
            $table->timestamp('external_completed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('inspections', function (Blueprint $table) {
            $table->dropConstrainedForeignId('external_work_order_id');
            $table->dropColumn('external_completed_at');
        });
        Schema::dropIfExists('work_order_activities');
        Schema::dropIfExists('supply_deliveries');
        Schema::dropIfExists('work_order_evidence');
        Schema::table('work_orders', fn (Blueprint $table) => $table->dropConstrainedForeignId('accepted_completion_submission_id'));
        Schema::dropIfExists('work_order_completion_submissions');
        Schema::dropIfExists('service_appointments');
        Schema::dropIfExists('work_order_assignments');
        Schema::table('work_orders', fn (Blueprint $table) => $table->dropColumn('completion_review_required'));
    }
};
