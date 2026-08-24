<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RentPaymentResource\Pages;
use App\Models\RentPayment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class RentPaymentResource extends Resource
{
    protected static ?string $model = RentPayment::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationGroup = 'MySpaces Estate';
    protected static ?string $navigationLabel = 'Rent Payments';

    public static function form(Form $form): Form
    {
        // If user comes from "Add Payment" action on an invoice
        $invoiceIdFromUrl = request()->get('rent_invoice_id');

        return $form
            ->schema([
                Forms\Components\Section::make('Payment')
                    ->schema([
                        Forms\Components\Select::make('rent_invoice_id')
                            ->label('Invoice')
                            // IMPORTANT: must be a real DB column, not an accessor
                            ->relationship('invoice', 'id')
                            ->searchable()
                            ->preload()
                            ->required()
                            // prefill from URL (e.g. ?rent_invoice_id=12)
                            ->default($invoiceIdFromUrl)
                            // if prefilled, lock the field to avoid mistakes
                            ->disabled(fn () => filled($invoiceIdFromUrl))
                            // make sure the value still saves even when disabled
                            ->dehydrated()
                            // show accessor label (computed), NOT a DB column
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->display_name),

                        Forms\Components\DatePicker::make('paid_on')
                            ->required()
                            ->default(now()),

                        Forms\Components\TextInput::make('amount')
                            ->numeric()
                            ->minValue(0)
                            ->prefix('RWF')
                            ->required(),

                        Forms\Components\Select::make('method')
                            ->options([
                                'cash' => 'Cash',
                                'bank' => 'Bank Transfer',
                                'momo' => 'Mobile Money',
                                'card' => 'Card',
                                'other' => 'Other',
                            ])
                            ->default('cash')
                            ->required(),

                        Forms\Components\TextInput::make('reference')
                            ->maxLength(255)
                            ->placeholder('Txn / Ref number (optional)'),

                        Forms\Components\Textarea::make('notes')
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // safer than invoice.display_name (prevents SQL trying to order/search by non-column)
                Tables\Columns\TextColumn::make('invoice.id')
                    ->label('Invoice')
                    ->formatStateUsing(fn ($state, $record) => $record->invoice?->display_name ?? '—')
                    ->wrap(),

                Tables\Columns\TextColumn::make('paid_on')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount')
                    ->money('RWF')
                    ->sortable(),

                Tables\Columns\TextColumn::make('method')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'cash' => 'Cash',
                        'bank' => 'Bank',
                        'momo' => 'MoMo',
                        'card' => 'Card',
                        'other' => 'Other',
                        default => $state ?? '—',
                    }),

                Tables\Columns\TextColumn::make('reference')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('method')
                    ->options([
                        'cash' => 'Cash',
                        'bank' => 'Bank Transfer',
                        'momo' => 'Mobile Money',
                        'card' => 'Card',
                        'other' => 'Other',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRentPayments::route('/'),
            'create' => Pages\CreateRentPayment::route('/create'),
            'edit' => Pages\EditRentPayment::route('/{record}/edit'),
        ];
    }
}