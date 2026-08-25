<?php

namespace App\Filament\Provider\Resources\ProviderInvoiceResource\Pages;

use App\Filament\Provider\Resources\ProviderInvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProviderInvoices extends ListRecords
{
    protected static string $resource = ProviderInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
