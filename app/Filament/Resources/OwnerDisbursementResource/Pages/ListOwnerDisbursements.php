<?php

namespace App\Filament\Resources\OwnerDisbursementResource\Pages;

use App\Filament\Resources\OwnerDisbursementResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOwnerDisbursements extends ListRecords
{
    protected static string $resource = OwnerDisbursementResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
