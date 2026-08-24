<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->foreignId('self_property_owner_id')
                ->nullable()
                ->unique()
                ->constrained('property_owners')
                ->nullOnDelete();
        });

        DB::transaction(function () {
            DB::table('accounts')
                ->where('type', 'individual_landlord')
                ->orderBy('id')
                ->each(function ($account) {
                    $ownerId = DB::table('property_owners')
                        ->where('account_id', $account->id)
                        ->orderBy('id')
                        ->value('id');

                    if (! $ownerId) {
                        $ownerId = DB::table('property_owners')->insertGetId([
                            'account_id' => $account->id,
                            'type' => 'individual',
                            'name' => $account->name,
                            'phone' => $account->phone,
                            'email' => $account->email,
                            'tin' => $account->tin,
                            'registration_number' => $account->registration_number,
                            'address' => $account->address,
                            'status' => 'active',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    DB::table('accounts')->where('id', $account->id)->update([
                        'self_property_owner_id' => $ownerId,
                    ]);
                });
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('self_property_owner_id');
        });
    }
};
