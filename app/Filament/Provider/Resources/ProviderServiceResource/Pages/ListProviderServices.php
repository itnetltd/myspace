<?php

namespace App\Filament\Provider\Resources\ProviderServiceResource\Pages;

use App\Filament\Provider\Resources\ProviderServiceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProviderServices extends ListRecords
{
    protected static string $resource = ProviderServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
