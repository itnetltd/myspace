<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OwnerLedgerEntryResource\Pages;
use App\Models\OwnerLedgerEntry;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OwnerLedgerEntryResource extends Resource
{
    protected static ?string $model = OwnerLedgerEntry::class;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationGroup = 'Owner Financials';

    protected static ?string $navigationLabel = 'Owner Ledger';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('entry_number')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('propertyOwner.name')->label('Owner')->searchable(),
                Tables\Columns\TextColumn::make('property.name')->searchable(),
                Tables\Columns\TextColumn::make('unit.unit_code')->label('Unit'),
                Tables\Columns\TextColumn::make('entry_type')->badge(),
                Tables\Columns\TextColumn::make('direction')->badge()
                    ->color(fn (string $state) => $state === OwnerLedgerEntry::DIRECTION_CREDIT ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('amount')->money(fn ($record) => $record->currency)->sortable(),
                Tables\Columns\TextColumn::make('occurred_on')->date()->sortable(),
                Tables\Columns\TextColumn::make('description')->limit(60),
                Tables\Columns\IconColumn::make('locked_at')->label('Locked')->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('property_owner_id')->relationship('propertyOwner', 'name')->searchable()->preload(),
                Tables\Filters\SelectFilter::make('property_id')->relationship('property', 'name')->searchable()->preload(),
                Tables\Filters\SelectFilter::make('direction')->options([
                    OwnerLedgerEntry::DIRECTION_CREDIT => 'Credit',
                    OwnerLedgerEntry::DIRECTION_DEBIT => 'Debit',
                ]),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListOwnerLedgerEntries::route('/')];
    }
}
