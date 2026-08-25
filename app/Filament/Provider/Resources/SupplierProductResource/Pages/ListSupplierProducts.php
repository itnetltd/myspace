<?php

namespace App\Filament\Provider\Resources\SupplierProductResource\Pages;

use App\Filament\Provider\Resources\SupplierProductResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSupplierProducts extends ListRecords
{
    protected static string $resource = SupplierProductResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
