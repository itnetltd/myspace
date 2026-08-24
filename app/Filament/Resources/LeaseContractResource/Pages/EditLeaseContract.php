<?php

namespace App\Filament\Resources\LeaseContractResource\Pages;

use App\Filament\Resources\LeaseContractResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLeaseContract extends EditRecord
{
    protected static string $resource = LeaseContractResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
