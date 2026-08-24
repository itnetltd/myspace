<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RentInvoiceResource\Pages;
use App\Filament\Resources\RentInvoiceResource\RelationManagers\PaymentsRelationManager;
use App\Models\RentInvoice;
use App\Models\RentPayment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class RentInvoiceResource extends Resource
{
    protected static ?string $model = RentInvoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?string $navigationGroup = 'MySpaces Estate';

    protected static ?string $navigationLabel = 'Rent Invoices';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Invoice')
                ->schema([
                    Forms\Components\Select::make('lease_id')
                        ->relationship('lease', 'id')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->getOptionLabelFromRecordUsing(function ($record) {
                            $record->loadMissing(['unit', 'tenant']);
                            $unit = $record->unit?->unit_code ?? 'Unit';
                            $tenant = $record->tenant?->full_name ?? 'Tenant';

                            return "{$unit} — {$tenant} (Lease #{$record->id})";
                        }),

                    Forms\Components\DatePicker::make('period_start')
                        ->required()
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->reactive()
                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                            // if end date is before start date, clear it
                            $end = $get('period_end');
                            if ($state && $end && strtotime($end) < strtotime($state)) {
                                $set('period_end', null);
                            }
                        }),

                    Forms\Components\DatePicker::make('period_end')
                        ->required()
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->rule('after_or_equal:period_start')
                        ->reactive(),

                    Forms\Components\DatePicker::make('due_date')
                        ->required()
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->rule('after_or_equal:period_start')
                        ->rule('before_or_equal:period_end')
                        ->helperText('Must be within the invoice period.'),

                    Forms\Components\TextInput::make('amount_due')
                        ->numeric()
                        ->prefix('RWF')
                        ->required()
                        ->reactive()
                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                            // Recompute totals live in UI
                            $late = (float) ($get('late_fee') ?? 0);
                            $amount = (float) ($state ?? 0);
                            $set('total_due', $amount + $late);
                        }),

                    // computed late fee (read-only)
                    Forms\Components\TextInput::make('late_fee')
                        ->numeric()
                        ->prefix('RWF')
                        ->disabled()
                        ->dehydrated(false)
                        ->default(0)
                        ->helperText('Auto-calculated from Rent Policy (late fee settings).'),

                    // computed total due (amount_due + late_fee)
                    Forms\Components\TextInput::make('total_due')
                        ->numeric()
                        ->prefix('RWF')
                        ->disabled()
                        ->dehydrated(false)
                        ->default(0)
                        ->helperText('Auto-calculated: Amount + Late Fee.'),

                    // computed by system after payments; keep read-only
                    Forms\Components\TextInput::make('amount_paid')
                        ->numeric()
                        ->prefix('RWF')
                        ->disabled()
                        ->dehydrated(false)
                        ->default(0)
                        ->helperText('Auto-calculated from payments.'),

                    Forms\Components\Select::make('status')
                        ->options([
                            'unpaid' => 'Unpaid',
                            'partial' => 'Partial',
                            'paid' => 'Paid',
                            'overdue' => 'Overdue',
                        ])
                        ->disabled()
                        ->dehydrated(false)
                        ->default('unpaid')
                        ->helperText('Auto-updated based on payments + due date.'),

                    Forms\Components\Textarea::make('notes')->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('lease.unit.unit_code')
                    ->label('Unit')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('lease.tenant.full_name')
                    ->label('Tenant')
                    ->searchable(),

                Tables\Columns\TextColumn::make('lease_id')
                    ->label('Lease #')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('period_start')->date()->sortable(),
                Tables\Columns\TextColumn::make('period_end')->date()->sortable(),

                Tables\Columns\TextColumn::make('due_date')
                    ->date()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('amount_due')->money('RWF')->sortable(),

                Tables\Columns\TextColumn::make('late_fee')
                    ->label('Late Fee')
                    ->money('RWF')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('total_due')
                    ->label('Total Due')
                    ->money('RWF')
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount_paid')->money('RWF')->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'paid' => 'success',
                        'partial' => 'warning',
                        'overdue' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('recalculate')
                    ->label('Recalculate')
                    ->icon('heroicon-o-arrow-path')
                    ->action(function (RentInvoice $record) {
                        Gate::authorize('update', $record);
                        $record->refreshPaymentTotals();
                        Notification::make()->title('Invoice recalculated')->success()->send();
                    }),

                Tables\Actions\Action::make('addPayment')
                    ->label('Add Payment')
                    ->icon('heroicon-o-banknotes')
                    ->url(fn (RentInvoice $record) => route('filament.admin.resources.rent-payments.create', [
                        'rent_invoice_id' => $record->id,
                    ]))
                    ->visible(fn () => Gate::allows('create', RentPayment::class))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            PaymentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRentInvoices::route('/'),
            'create' => Pages\CreateRentInvoice::route('/create'),
            'edit' => Pages\EditRentInvoice::route('/{record}/edit'),
        ];
    }
}
