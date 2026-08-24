<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PropertyOwnerResource\Pages;
use App\Models\PropertyOwner;
use App\Support\CurrentAccount;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PropertyOwnerResource extends Resource
{
    protected static ?string $model = PropertyOwner::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'MySpaces Estate';

    public static function getNavigationLabel(): string
    {
        return static::isIndividualAccount() ? 'Owner Profile' : 'Property Owners / Clients';
    }

    public static function getModelLabel(): string
    {
        return static::isIndividualAccount() ? 'owner profile' : 'property owner';
    }

    public static function getPluralModelLabel(): string
    {
        return static::isIndividualAccount() ? 'owner profile' : 'property owners';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Owner Details')->schema([
                Forms\Components\Select::make('type')->options([
                    PropertyOwner::TYPE_INDIVIDUAL => 'Individual',
                    PropertyOwner::TYPE_COMPANY => 'Company',
                ])->required(),
                Forms\Components\TextInput::make('name')->required()->maxLength(255),
                Forms\Components\TextInput::make('phone')->tel()->maxLength(255),
                Forms\Components\TextInput::make('email')->email()->maxLength(255),
                Forms\Components\TextInput::make('national_id')->label('National ID')->maxLength(255),
                Forms\Components\TextInput::make('tin')->label('TIN')->maxLength(255),
                Forms\Components\TextInput::make('registration_number')->maxLength(255),
                Forms\Components\Textarea::make('address')->columnSpanFull(),
                Forms\Components\Select::make('status')->options([
                    PropertyOwner::STATUS_ACTIVE => 'Active',
                    PropertyOwner::STATUS_INACTIVE => 'Inactive',
                ])->default(PropertyOwner::STATUS_ACTIVE)->required(),
            ])->columns(2),
            Forms\Components\Section::make('Payment Details')->schema([
                Forms\Components\TextInput::make('bank_name')->maxLength(255),
                Forms\Components\TextInput::make('bank_account_name')->maxLength(255),
                Forms\Components\TextInput::make('bank_account_number')->maxLength(255),
                Forms\Components\TextInput::make('mobile_money_number')->maxLength(255),
                Forms\Components\Textarea::make('notes')->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('type')->badge(),
                Tables\Columns\TextColumn::make('phone')->searchable(),
                Tables\Columns\TextColumn::make('email')->searchable(),
                Tables\Columns\TextColumn::make('properties_count')->counts('properties')->label('Properties'),
                Tables\Columns\TextColumn::make('status')->badge(),
            ])
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([Tables\Actions\DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPropertyOwners::route('/'),
            'create' => Pages\CreatePropertyOwner::route('/create'),
            'edit' => Pages\EditPropertyOwner::route('/{record}/edit'),
        ];
    }

    private static function isIndividualAccount(): bool
    {
        return (bool) app(CurrentAccount::class)->account()?->isIndividualLandlord();
    }
}
