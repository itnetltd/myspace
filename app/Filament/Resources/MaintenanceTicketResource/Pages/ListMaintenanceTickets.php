<?php

namespace App\Filament\Resources\MaintenanceTicketResource\Pages;

use App\Filament\Resources\MaintenanceTicketResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMaintenanceTickets extends ListRecords
{
    protected static string $resource = MaintenanceTicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
