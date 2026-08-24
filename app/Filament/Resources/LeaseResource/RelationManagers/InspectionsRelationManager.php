<?php

namespace App\Filament\Resources\LeaseResource\RelationManagers;

use App\Models\Inspection;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class InspectionsRelationManager extends RelationManager
{
    protected static string $relationship = 'inspections';
    protected static ?string $title = 'Inspections';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('type')->badge()->sortable(),
                Tables\Columns\TextColumn::make('inspected_on')->date()->sortable(),
                Tables\Columns\TextColumn::make('inspected_by')->toggleable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Tables\Actions\Action::make('open')
                    ->label('Open')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Inspection $record) => route('filament.admin.resources.inspections.edit', $record))
                    ->openUrlInNewTab(),
            ]);
    }
}