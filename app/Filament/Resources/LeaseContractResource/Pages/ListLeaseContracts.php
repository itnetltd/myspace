<?php

namespace App\Filament\Resources\LeaseContractResource\Pages;

use App\Filament\Resources\LeaseContractResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLeaseContracts extends ListRecords
{
    protected static string $resource = LeaseContractResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
