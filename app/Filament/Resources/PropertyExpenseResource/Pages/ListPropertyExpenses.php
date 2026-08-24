<?php

namespace App\Filament\Resources\PropertyExpenseResource\Pages;

use App\Filament\Resources\PropertyExpenseResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListPropertyExpenses extends ListRecords
{
    protected static string $resource = PropertyExpenseResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }

    protected function getTableQuery(): ?Builder
    {
        return parent::getTableQuery()?->when(
            PropertyExpenseResource::maintenanceOnlyUser(),
            fn (Builder $query) => $query->where('category', 'maintenance'),
        );
    }
}
