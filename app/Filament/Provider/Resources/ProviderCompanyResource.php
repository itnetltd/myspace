<?php

namespace App\Filament\Provider\Resources;

use App\Filament\Provider\Resources\ProviderCompanyResource\Pages;
use App\Models\ProviderCompany;
use App\Support\CurrentProviderCompany;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProviderCompanyResource extends Resource
{
    protected static ?string $model = ProviderCompany::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office';

    protected static ?string $navigationLabel = 'Company Profile';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->required(), Forms\Components\TextInput::make('slug')->disabled(),
            Forms\Components\TextInput::make('registration_number'), Forms\Components\TextInput::make('tin'),
            Forms\Components\TextInput::make('phone'), Forms\Components\TextInput::make('email')->email(),
            Forms\Components\TextInput::make('website')->url(), Forms\Components\TextInput::make('address'),
            Forms\Components\TextInput::make('district'), Forms\Components\TextInput::make('country')->default('Rwanda'),
            Forms\Components\FileUpload::make('logo_path')->directory('private/provider-logos'),
            Forms\Components\Placeholder::make('verification')->content(fn (?ProviderCompany $record) => $record?->verified_at
                ? 'Verified on '.$record->verified_at->toDateString() : 'Not legally verified'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name'), Tables\Columns\TextColumn::make('status')->badge(),
            Tables\Columns\IconColumn::make('verified_at')->boolean()->label('Verified'),
        ])->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereKey(app(CurrentProviderCompany::class)->id());
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListProviderCompanies::route('/'), 'edit' => Pages\EditProviderCompany::route('/{record}/edit')];
    }
}
