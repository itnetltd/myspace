<?php

namespace App\Filament\Provider\Resources;

use App\Filament\Provider\Resources\ProviderStaffInvitationResource\Pages;
use App\Models\ProviderCompanyMembership;
use App\Models\ProviderStaffInvitation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProviderStaffInvitationResource extends Resource
{
    protected static ?string $model = ProviderStaffInvitation::class;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';

    protected static ?string $navigationGroup = 'Company';

    protected static ?string $navigationLabel = 'Staff Invitations';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('email')->email()->required()->maxLength(255)
                ->helperText('The invitation can only be accepted by an authenticated user with this exact email.'),
            Forms\Components\Select::make('role')
                ->options(array_combine(ProviderCompanyMembership::ROLES, ProviderCompanyMembership::ROLES))
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('email'),
            Tables\Columns\TextColumn::make('role')->badge(),
            Tables\Columns\TextColumn::make('status')->badge(),
            Tables\Columns\TextColumn::make('invited_at')->dateTime(),
            Tables\Columns\TextColumn::make('expires_at')->dateTime(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProviderStaffInvitations::route('/'),
            'create' => Pages\CreateProviderStaffInvitation::route('/create'),
        ];
    }
}
