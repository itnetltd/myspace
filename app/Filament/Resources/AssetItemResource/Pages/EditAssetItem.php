<?php

namespace App\Filament\Resources\AssetItemResource\Pages;

use App\Filament\Resources\AssetItemResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAssetItem extends EditRecord
{
    protected static string $resource = AssetItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
