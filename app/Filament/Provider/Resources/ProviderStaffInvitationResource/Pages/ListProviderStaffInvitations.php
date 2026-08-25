<?php

namespace App\Filament\Provider\Resources\ProviderStaffInvitationResource\Pages;

use App\Filament\Provider\Resources\ProviderStaffInvitationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProviderStaffInvitations extends ListRecords
{
    protected static string $resource = ProviderStaffInvitationResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
