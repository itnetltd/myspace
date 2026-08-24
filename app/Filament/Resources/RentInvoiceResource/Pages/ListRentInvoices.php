<?php

namespace App\Filament\Resources\RentInvoiceResource\Pages;

use App\Filament\Resources\RentInvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRentInvoices extends ListRecords
{
    protected static string $resource = RentInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
