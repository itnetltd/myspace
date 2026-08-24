<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AccountResource\Pages;
use App\Models\Account;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AccountResource extends Resource
{
    protected static ?string $model = Account::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationGroup = 'Workspace';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Organization')
                ->schema([
                    Forms\Components\TextInput::make('name')->required()->maxLength(255),
                    Forms\Components\TextInput::make('slug')->maxLength(255)->unique(ignoreRecord: true),
                    Forms\Components\Select::make('type')->options([
                        Account::TYPE_INDIVIDUAL_LANDLORD => 'Individual Landlord',
                        Account::TYPE_PROPERTY_MANAGEMENT_COMPANY => 'Property Management Company',
                    ])->required(),
                    Forms\Components\Select::make('status')->options([
                        Account::STATUS_ACTIVE => 'Active',
                        Account::STATUS_SUSPENDED => 'Suspended',
                        Account::STATUS_CLOSED => 'Closed',
                    ])->default(Account::STATUS_ACTIVE)->required(),
                    Forms\Components\TextInput::make('phone')->tel()->maxLength(255),
                    Forms\Components\TextInput::make('email')->email()->maxLength(255),
                    Forms\Components\TextInput::make('tin')->label('TIN')->maxLength(255),
                    Forms\Components\TextInput::make('registration_number')->maxLength(255),
                    Forms\Components\Textarea::make('address')->columnSpanFull(),
                    Forms\Components\FileUpload::make('logo_path')->image()->directory('account-logos'),
                    Forms\Components\TextInput::make('currency')->default('RWF')->required()->maxLength(3),
                    Forms\Components\TextInput::make('timezone')->default('Africa/Kigali')->required(),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('type')->badge(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('currency'),
            ])
            ->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('users', fn (Builder $query) => $query
                ->whereKey(auth()->id())
                ->where('account_user.is_active', true));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAccounts::route('/'),
            'create' => Pages\CreateAccount::route('/create'),
            'edit' => Pages\EditAccount::route('/{record}/edit'),
        ];
    }
}
