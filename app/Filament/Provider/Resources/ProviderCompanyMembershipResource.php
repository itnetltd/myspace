<?php

namespace App\Filament\Provider\Resources;

use App\Filament\Provider\Resources\ProviderCompanyMembershipResource\Pages;
use App\Models\ProviderCompanyMembership;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProviderCompanyMembershipResource extends Resource
{
    protected static ?string $model = ProviderCompanyMembership::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Staff';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('user_id')->relationship('user', 'email')->searchable()->preload()->required(),
            Forms\Components\Select::make('role')->options(array_combine(ProviderCompanyMembership::ROLES, ProviderCompanyMembership::ROLES))->required(),
            Forms\Components\Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('user.name'), Tables\Columns\TextColumn::make('user.email'),
            Tables\Columns\TextColumn::make('role')->badge(), Tables\Columns\IconColumn::make('is_active')->boolean(),
        ])->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListProviderCompanyMemberships::route('/'), 'create' => Pages\CreateProviderCompanyMembership::route('/create'), 'edit' => Pages\EditProviderCompanyMembership::route('/{record}/edit')];
    }
}
