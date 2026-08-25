<?php

namespace App\Filament\Provider\Resources\ProviderCompanyMembershipResource\Pages;

use App\Filament\Provider\Resources\ProviderCompanyMembershipResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProviderCompanyMemberships extends ListRecords
{
    protected static string $resource = ProviderCompanyMembershipResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
