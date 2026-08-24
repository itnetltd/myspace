<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PropertyExpenseResource\Pages;
use App\Models\PropertyExpense;
use App\Support\AccountAccess;
use App\Support\CurrentAccount;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class PropertyExpenseResource extends Resource
{
    protected static ?string $model = PropertyExpense::class;

    protected static ?string $navigationIcon = 'heroicon-o-receipt-refund';

    protected static ?string $navigationGroup = 'Owner Financials';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Expense')->schema([
                Forms\Components\Select::make('property_owner_id')
                    ->relationship('propertyOwner', 'name')
                    ->searchable()->preload()->required()->live(),
                Forms\Components\Select::make('property_id')
                    ->relationship(
                        'property',
                        'name',
                        modifyQueryUsing: fn (Builder $query, Forms\Get $get) => $query
                            ->when($get('property_owner_id'), fn (Builder $query, $ownerId) => $query
                                ->where('property_owner_id', $ownerId)),
                    )
                    ->searchable()->preload()->required()->live(),
                Forms\Components\Select::make('unit_id')
                    ->relationship(
                        'unit',
                        'unit_code',
                        modifyQueryUsing: fn (Builder $query, Forms\Get $get) => $query
                            ->when($get('property_id'), fn (Builder $query, $propertyId) => $query
                                ->where('property_id', $propertyId)),
                    )
                    ->searchable()->preload()->live(),
                Forms\Components\Select::make('lease_id')
                    ->relationship(
                        'lease',
                        'id',
                        modifyQueryUsing: fn (Builder $query, Forms\Get $get) => $query
                            ->when($get('unit_id'), fn (Builder $query, $unitId) => $query->where('unit_id', $unitId)),
                    )
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->display_name)
                    ->searchable()->preload(),
                Forms\Components\Select::make('maintenance_ticket_id')
                    ->relationship('maintenanceTicket', 'ticket_no')
                    ->searchable()->preload(),
                Forms\Components\Select::make('category')
                    ->options(fn () => static::maintenanceOnlyUser()
                        ? ['maintenance' => 'Maintenance']
                        : PropertyExpense::CATEGORIES)
                    ->required(),
                Forms\Components\TextInput::make('vendor_name')->maxLength(255),
                Forms\Components\Textarea::make('description')->required()->columnSpanFull(),
                Forms\Components\TextInput::make('amount')->numeric()->minValue(0)->prefix('RWF')->required(),
                Forms\Components\DatePicker::make('occurred_on')->default(now())->required(),
                Forms\Components\TextInput::make('reference')->maxLength(255),
                Forms\Components\FileUpload::make('document_path')->directory('property-expenses'),
                Forms\Components\Textarea::make('notes')->columnSpanFull(),
                Forms\Components\Placeholder::make('status_display')
                    ->label('Status')
                    ->content(fn (?PropertyExpense $record) => $record?->status ?? 'Determined on save'),
                Forms\Components\Toggle::make('owner_approval_required')->disabled()->dehydrated(false),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('expense_number')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('propertyOwner.name')->label('Owner')->searchable(),
                Tables\Columns\TextColumn::make('property.name')->searchable(),
                Tables\Columns\TextColumn::make('category')->badge(),
                Tables\Columns\TextColumn::make('amount')->money(fn ($record) => $record->currency)->sortable(),
                Tables\Columns\TextColumn::make('occurred_on')->date()->sortable(),
                Tables\Columns\TextColumn::make('status')->badge(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('recordOwnerApproval')
                    ->label('Record Owner Approval')
                    ->icon('heroicon-o-user-check')
                    ->form([Forms\Components\Textarea::make('note')->required()])
                    ->visible(fn (PropertyExpense $record) => $record->owner_approval_required
                        && ! $record->owner_approved_at && Gate::allows('approve', $record))
                    ->action(function (PropertyExpense $record, array $data) {
                        Gate::authorize('approve', $record);
                        app(\App\Services\PropertyExpenseService::class)
                            ->recordOwnerApproval($record, auth()->user(), $data['note']);
                    }),
                Tables\Actions\Action::make('approve')
                    ->icon('heroicon-o-check-badge')
                    ->visible(fn (PropertyExpense $record) => ! $record->approved_at && Gate::allows('approve', $record))
                    ->requiresConfirmation()
                    ->action(function (PropertyExpense $record) {
                        Gate::authorize('approve', $record);
                        app(\App\Services\PropertyExpenseService::class)->approve($record, auth()->user());
                    }),
                Tables\Actions\Action::make('post')
                    ->icon('heroicon-o-lock-closed')
                    ->visible(fn (PropertyExpense $record) => $record->status !== PropertyExpense::STATUS_POSTED
                        && $record->status !== PropertyExpense::STATUS_VOID && Gate::allows('post', $record))
                    ->requiresConfirmation()
                    ->action(function (PropertyExpense $record) {
                        Gate::authorize('post', $record);
                        app(\App\Services\PropertyExpenseService::class)->post($record, auth()->user());
                    }),
                Tables\Actions\Action::make('void')
                    ->color('danger')->icon('heroicon-o-no-symbol')
                    ->form([Forms\Components\Textarea::make('reason')->required()])
                    ->visible(fn (PropertyExpense $record) => $record->status === PropertyExpense::STATUS_POSTED
                        && Gate::allows('void', $record))
                    ->action(function (PropertyExpense $record, array $data) {
                        Gate::authorize('void', $record);
                        app(\App\Services\PropertyExpenseService::class)
                            ->void($record, auth()->user(), $data['reason']);
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPropertyExpenses::route('/'),
            'create' => Pages\CreatePropertyExpense::route('/create'),
            'edit' => Pages\EditPropertyExpense::route('/{record}/edit'),
        ];
    }

    public static function maintenanceOnlyUser(): bool
    {
        $user = auth()->user();
        $account = app(CurrentAccount::class)->account();

        return $user && $account
            && app(AccountAccess::class)->can($user, $account, AccountAccess::INITIATE_MAINTENANCE_EXPENSE)
            && ! app(AccountAccess::class)->can($user, $account, AccountAccess::MANAGE_EXPENSES);
    }
}
