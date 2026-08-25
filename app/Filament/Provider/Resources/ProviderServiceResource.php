<?php

namespace App\Filament\Provider\Resources;

use App\Filament\Provider\Resources\ProviderServiceResource\Pages;
use App\Models\ProviderService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProviderServiceResource extends Resource
{
    protected static ?string $model = ProviderService::class;

    protected static ?string $navigationIcon = 'heroicon-o-wrench';

    protected static ?string $navigationLabel = 'Services';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('service_type')->options(array_combine(ProviderService::TYPES, ProviderService::TYPES))->required(),
            Forms\Components\TextInput::make('category')->required(), Forms\Components\TextInput::make('name')->required(),
            Forms\Components\Textarea::make('description'), Forms\Components\TextInput::make('service_area'),
            Forms\Components\Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->searchable(), Tables\Columns\TextColumn::make('service_type')->badge(),
            Tables\Columns\TextColumn::make('category'), Tables\Columns\TextColumn::make('service_area'),
            Tables\Columns\IconColumn::make('is_active')->boolean(),
        ])->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListProviderServices::route('/'), 'create' => Pages\CreateProviderService::route('/create'), 'edit' => Pages\EditProviderService::route('/{record}/edit')];
    }
}
