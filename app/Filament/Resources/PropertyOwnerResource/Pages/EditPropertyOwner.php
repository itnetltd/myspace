<?php

namespace App\Filament\Resources\PropertyOwnerResource\Pages;

use App\Filament\Resources\PropertyOwnerResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPropertyOwner extends EditRecord
{
    protected static string $resource = PropertyOwnerResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
