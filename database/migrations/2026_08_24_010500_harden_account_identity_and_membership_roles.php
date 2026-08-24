<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('accounts')->where('type', 'landlord')->update([
            'type' => 'individual_landlord',
        ]);

        DB::table('accounts')->orderBy('id')->each(function ($account) {
            $name = filled($account->name) ? $account->name : 'Account '.$account->id;
            $slug = filled($account->slug) ? $account->slug : $this->uniqueSlug($name, $account->id);

            DB::table('accounts')->where('id', $account->id)->update([
                'name' => $name,
                'slug' => $slug,
            ]);
        });

        DB::table('account_user')
            ->whereNull('role')
            ->orWhere('role', '')
            ->orWhere('role', 'staff')
            ->update(['role' => 'viewer']);

        Schema::table('accounts', function (Blueprint $table) {
            $table->string('name')->nullable(false)->change();
            $table->string('slug')->nullable(false)->change();
            $table->string('type')->default('individual_landlord')->change();
        });

        Schema::table('account_user', function (Blueprint $table) {
            $table->string('role')->default('viewer')->change();
        });
    }

    public function down(): void
    {
        Schema::table('account_user', function (Blueprint $table) {
            $table->string('role')->default('staff')->change();
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->string('name')->nullable()->change();
            $table->string('slug')->nullable()->change();
            $table->string('type')->default('landlord')->change();
        });
    }

    private function uniqueSlug(string $name, int $accountId): string
    {
        $base = Str::slug($name) ?: 'account-'.$accountId;
        $slug = $base;
        $suffix = 1;

        while (DB::table('accounts')->where('slug', $slug)->where('id', '!=', $accountId)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
};
