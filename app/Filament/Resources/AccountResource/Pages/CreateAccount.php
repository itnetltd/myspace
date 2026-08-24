<?php

namespace App\Filament\Resources\AccountResource\Pages;

use App\Filament\Resources\AccountResource;
use App\Models\Account;
use App\Support\CurrentAccount;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateAccount extends CreateRecord
{
    protected static string $resource = AccountResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data) {
            $data['slug'] = $data['slug'] ?: $this->uniqueSlug($data['name']);
            $data['created_by'] = auth()->id();

            $account = Account::create($data);
            $account->users()->attach(auth()->id(), [
                'role' => Account::ROLE_OWNER,
                'is_active' => true,
            ]);
            app(CurrentAccount::class)->switch(auth()->user(), $account->getKey());

            return $account;
        });
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'account';
        $slug = $base;
        $suffix = 1;

        while (Account::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
