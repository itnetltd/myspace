<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ManagementAgreementResource\Pages;
use App\Models\ManagementAgreement;
use App\Models\PropertyOwner;
use App\Support\CurrentAccount;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class ManagementAgreementResource extends Resource
{
    protected static ?string $model = ManagementAgreement::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-check';

    protected static ?string $navigationGroup = 'MySpaces Estate';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Agreement')->schema([
                Forms\Components\Select::make('property_owner_id')
                    ->relationship(
                        'propertyOwner',
                        'name',
                        modifyQueryUsing: fn (Builder $query) => $query->where('status', PropertyOwner::STATUS_ACTIVE),
                    )
                    ->searchable()->preload()->required()->live(),
                Forms\Components\Select::make('property_id')
                    ->relationship(
                        'property',
                        'name',
                        modifyQueryUsing: fn (Builder $query, Forms\Get $get) => $query
                            ->when($get('property_owner_id'), fn (Builder $query, $ownerId) => $query
                                ->where('property_owner_id', $ownerId)),
                    )
                    ->searchable()->preload(),
                Forms\Components\TextInput::make('reference_number')->required()->maxLength(255),
                Forms\Components\DatePicker::make('start_date')->required(),
                Forms\Components\DatePicker::make('end_date')->afterOrEqual('start_date'),
                Forms\Components\Select::make('management_fee_type')->options([
                    ManagementAgreement::FEE_PERCENTAGE => 'Percentage',
                    ManagementAgreement::FEE_FIXED => 'Fixed',
                    ManagementAgreement::FEE_PERCENTAGE_PLUS_FIXED => 'Percentage plus fixed',
                ])->required()->live(),
                Forms\Components\TextInput::make('management_fee_percentage')
                    ->label('Management Percentage (%)')
                    ->numeric()->minValue(0)->maxValue(100)
                    ->required(fn (Forms\Get $get) => in_array($get('management_fee_type'), [
                        ManagementAgreement::FEE_PERCENTAGE,
                        ManagementAgreement::FEE_PERCENTAGE_PLUS_FIXED,
                    ], true))
                    ->visible(fn (Forms\Get $get) => in_array($get('management_fee_type'), [
                        ManagementAgreement::FEE_PERCENTAGE,
                        ManagementAgreement::FEE_PERCENTAGE_PLUS_FIXED,
                    ], true)),
                Forms\Components\TextInput::make('management_fee_fixed_amount')
                    ->label('Fixed Monthly Fee (RWF)')
                    ->numeric()->minValue(0)
                    ->required(fn (Forms\Get $get) => in_array($get('management_fee_type'), [
                        ManagementAgreement::FEE_FIXED,
                        ManagementAgreement::FEE_PERCENTAGE_PLUS_FIXED,
                    ], true))
                    ->visible(fn (Forms\Get $get) => in_array($get('management_fee_type'), [
                        ManagementAgreement::FEE_FIXED,
                        ManagementAgreement::FEE_PERCENTAGE_PLUS_FIXED,
                    ], true)),
                Forms\Components\Placeholder::make('fee_migration_warning')
                    ->content('Legacy percentage-plus-fixed value requires human review; its fixed component was not invented.')
                    ->visible(fn (?ManagementAgreement $record) => (bool) $record?->fee_migration_review_required)
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('maintenance_approval_limit')->numeric()->minValue(0),
                Forms\Components\Select::make('status')->options([
                    ManagementAgreement::STATUS_DRAFT => 'Draft',
                    ManagementAgreement::STATUS_ACTIVE => 'Active',
                    ManagementAgreement::STATUS_EXPIRED => 'Expired',
                    ManagementAgreement::STATUS_TERMINATED => 'Terminated',
                ])->default(ManagementAgreement::STATUS_DRAFT)->required(),
                Forms\Components\Toggle::make('rent_collection_enabled')->default(true),
                Forms\Components\Toggle::make('deposit_management_enabled')->default(false),
                Forms\Components\FileUpload::make('agreement_document_path')->directory('management-agreements'),
                Forms\Components\Textarea::make('notes')->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference_number')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('propertyOwner.name')->label('Owner')->searchable(),
                Tables\Columns\TextColumn::make('property.name')->label('Property')->searchable(),
                Tables\Columns\TextColumn::make('management_fee_type')->badge(),
                Tables\Columns\TextColumn::make('management_fee_percentage')->suffix('%'),
                Tables\Columns\TextColumn::make('management_fee_fixed_amount')->money('RWF'),
                Tables\Columns\TextColumn::make('start_date')->date(),
                Tables\Columns\TextColumn::make('end_date')->date(),
                Tables\Columns\TextColumn::make('status')->badge(),
            ])
            ->actions([
                Tables\Actions\Action::make('confirmFeeComponents')
                    ->label('Confirm Fee Components')
                    ->icon('heroicon-o-check-circle')
                    ->requiresConfirmation()
                    ->modalDescription('Confirm that both fee fields accurately reflect the signed agreement.')
                    ->visible(fn (ManagementAgreement $record) => $record->fee_migration_review_required
                        && Gate::allows('update', $record))
                    ->action(function (ManagementAgreement $record) {
                        Gate::authorize('update', $record);
                        $record->forceFill(['fee_migration_review_required' => false])->save();
                    }),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([Tables\Actions\DeleteBulkAction::make()]);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) app(CurrentAccount::class)->account()?->isPropertyManagementCompany();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListManagementAgreements::route('/'),
            'create' => Pages\CreateManagementAgreement::route('/create'),
            'edit' => Pages\EditManagementAgreement::route('/{record}/edit'),
        ];
    }
}
