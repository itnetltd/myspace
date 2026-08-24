<?php

namespace App\Filament\Resources\RentInvoiceResource\Pages;

use App\Filament\Resources\RentInvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRentInvoice extends EditRecord
{
    protected static string $resource = RentInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
