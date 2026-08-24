<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OwnerDisbursementResource\Pages;
use App\Models\OwnerDisbursement;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OwnerDisbursementResource extends Resource
{
    protected static ?string $model = OwnerDisbursement::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Owner Financials';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('property_owner_id')
                ->relationship('propertyOwner', 'name')->searchable()->preload()->required(),
            Forms\Components\TextInput::make('amount')->numeric()->minValue(0.01)->prefix('RWF')->required(),
            Forms\Components\DatePicker::make('paid_on')->default(now())->required(),
            Forms\Components\Select::make('method')->options(OwnerDisbursement::METHODS),
            Forms\Components\TextInput::make('reference')->maxLength(255),
            Forms\Components\Textarea::make('notes')->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('propertyOwner.name')->label('Owner')->searchable(),
            Tables\Columns\TextColumn::make('amount')->money(fn ($record) => $record->currency)->sortable(),
            Tables\Columns\TextColumn::make('paid_on')->date()->sortable(),
            Tables\Columns\TextColumn::make('method')->badge(),
            Tables\Columns\TextColumn::make('reference')->searchable(),
            Tables\Columns\TextColumn::make('creator.name')->label('Recorded By'),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOwnerDisbursements::route('/'),
            'create' => Pages\CreateOwnerDisbursement::route('/create'),
        ];
    }
}
