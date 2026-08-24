<?php

namespace App\Filament\Resources\ManagementAgreementResource\Pages;

use App\Filament\Resources\ManagementAgreementResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditManagementAgreement extends EditRecord
{
    protected static string $resource = ManagementAgreementResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
