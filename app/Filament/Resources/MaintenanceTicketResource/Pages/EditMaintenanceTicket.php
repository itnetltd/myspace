<?php

namespace App\Filament\Resources\MaintenanceTicketResource\Pages;

use App\Filament\Resources\MaintenanceTicketResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMaintenanceTicket extends EditRecord
{
    protected static string $resource = MaintenanceTicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
