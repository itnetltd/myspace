<?php

namespace App\Filament\Resources\ManagementAgreementResource\Pages;

use App\Filament\Resources\ManagementAgreementResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListManagementAgreements extends ListRecords
{
    protected static string $resource = ManagementAgreementResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
