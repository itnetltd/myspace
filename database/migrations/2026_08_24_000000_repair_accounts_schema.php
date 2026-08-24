<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('accounts', 'name')) {
                $table->string('name')->nullable();
            }
            if (! Schema::hasColumn('accounts', 'slug')) {
                $table->string('slug')->nullable()->unique();
            }
            if (! Schema::hasColumn('accounts', 'type')) {
                $table->string('type')->default('landlord');
            }
            if (! Schema::hasColumn('accounts', 'status')) {
                $table->string('status')->default('active');
            }
            if (! Schema::hasColumn('accounts', 'phone')) {
                $table->string('phone')->nullable();
            }
            if (! Schema::hasColumn('accounts', 'email')) {
                $table->string('email')->nullable();
            }
            if (! Schema::hasColumn('accounts', 'address')) {
                $table->string('address')->nullable();
            }
            if (! Schema::hasColumn('accounts', 'logo_path')) {
                $table->string('logo_path')->nullable();
            }
            if (! Schema::hasColumn('accounts', 'created_by')) {
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            }
        });

        if (! Schema::hasTable('account_user')) {
            Schema::create('account_user', function (Blueprint $table) {
                $table->id();
                $table->foreignId('account_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('role')->default('staff');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->unique(['account_id', 'user_id']);
                $table->index(['user_id', 'role']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('account_user');

        Schema::table('accounts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn([
                'name', 'slug', 'type', 'status', 'phone', 'email', 'address', 'logo_path',
            ]);
        });
    }
};
