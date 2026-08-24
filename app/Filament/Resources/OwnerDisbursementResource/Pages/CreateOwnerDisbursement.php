<?php

namespace App\Filament\Resources\OwnerDisbursementResource\Pages;

use App\Filament\Resources\OwnerDisbursementResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateOwnerDisbursement extends CreateRecord
{
    protected static string $resource = OwnerDisbursementResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(fn () => static::getModel()::create($data));
    }
}
