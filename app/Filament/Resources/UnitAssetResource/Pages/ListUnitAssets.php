<?php

namespace App\Filament\Resources\UnitAssetResource\Pages;

use App\Filament\Resources\UnitAssetResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListUnitAssets extends ListRecords
{
    protected static string $resource = UnitAssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
