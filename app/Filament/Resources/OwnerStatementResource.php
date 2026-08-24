<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OwnerStatementResource\Pages;
use App\Models\OwnerStatement;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OwnerStatementResource extends Resource
{
    protected static ?string $model = OwnerStatement::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?string $navigationGroup = 'Owner Financials';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('statement_number')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('propertyOwner.name')->label('Owner')->searchable(),
                Tables\Columns\TextColumn::make('period_start')->date(),
                Tables\Columns\TextColumn::make('period_end')->date(),
                Tables\Columns\TextColumn::make('closing_balance')->money(fn ($record) => $record->currency),
                Tables\Columns\TextColumn::make('status')->badge(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('pdf')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (OwnerStatement $record) => route('reports.owner-statement', $record))
                    ->openUrlInNewTab(),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Statement')->schema([
                Infolists\Components\TextEntry::make('statement_number'),
                Infolists\Components\TextEntry::make('propertyOwner.name')->label('Owner'),
                Infolists\Components\TextEntry::make('period_start')->date(),
                Infolists\Components\TextEntry::make('period_end')->date(),
                Infolists\Components\TextEntry::make('status')->badge(),
            ])->columns(3),
            Infolists\Components\Section::make('Summary')->schema([
                Infolists\Components\TextEntry::make('opening_balance')->money(fn ($record) => $record->currency),
                Infolists\Components\TextEntry::make('rent_collected')->money(fn ($record) => $record->currency),
                Infolists\Components\TextEntry::make('late_fees_collected')->money(fn ($record) => $record->currency),
                Infolists\Components\TextEntry::make('expenses')->money(fn ($record) => $record->currency),
                Infolists\Components\TextEntry::make('management_fees')->money(fn ($record) => $record->currency),
                Infolists\Components\TextEntry::make('owner_disbursements')->money(fn ($record) => $record->currency),
                Infolists\Components\TextEntry::make('net_activity')->money(fn ($record) => $record->currency),
                Infolists\Components\TextEntry::make('closing_balance')->money(fn ($record) => $record->currency),
            ])->columns(4),
            Infolists\Components\RepeatableEntry::make('lines')->schema([
                Infolists\Components\TextEntry::make('occurred_on')->date(),
                Infolists\Components\TextEntry::make('description'),
                Infolists\Components\TextEntry::make('credit')->money(fn ($record) => $record->statement->currency),
                Infolists\Components\TextEntry::make('debit')->money(fn ($record) => $record->statement->currency),
            ])->columns(4),
        ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOwnerStatements::route('/'),
            'view' => Pages\ViewOwnerStatement::route('/{record}'),
        ];
    }
}
