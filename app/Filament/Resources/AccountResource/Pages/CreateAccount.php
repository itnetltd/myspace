<?php

namespace App\Filament\Resources\AccountResource\Pages;

use App\Filament\Resources\AccountResource;
use App\Models\Account;
use App\Services\AccountOnboarding;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CreateAccount extends CreateRecord
{
    protected static string $resource = AccountResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $data['slug'] = $data['slug'] ?: $this->uniqueSlug($data['name']);

        return app(AccountOnboarding::class)->create($data, auth()->user());
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
