<?php

namespace App\Filament\Resources\LeaseResource\RelationManagers;

use App\Models\RentInvoice;
use App\Models\RentPayment;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class RentInvoicesRelationManager extends RelationManager
{
    protected static string $relationship = 'rentInvoices';

    protected static ?string $title = 'Rent Invoices';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('period_start')->date()->label('From')->sortable(),
                Tables\Columns\TextColumn::make('period_end')->date()->label('To')->sortable(),
                Tables\Columns\TextColumn::make('due_date')->date()->sortable(),
                Tables\Columns\TextColumn::make('amount_due')->money('RWF')->sortable(),
                Tables\Columns\TextColumn::make('late_fee')->money('RWF')->toggleable(),
                Tables\Columns\TextColumn::make('total_due')->money('RWF')->sortable(),
                Tables\Columns\TextColumn::make('amount_paid')->money('RWF')->sortable(),
                Tables\Columns\TextColumn::make('status')->badge()->sortable(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('New Invoice')
                    ->form([
                        Forms\Components\DatePicker::make('period_start')->required(),
                        Forms\Components\DatePicker::make('period_end')->required(),
                        Forms\Components\DatePicker::make('due_date'),
                        Forms\Components\TextInput::make('amount_due')->numeric()->prefix('RWF')->required(),
                        Forms\Components\Textarea::make('notes')->columnSpanFull(),
                    ])
                    ->mutateFormDataUsing(function (array $data) {
                        // Defaults for new invoice
                        $data['amount_paid'] = 0;
                        $data['late_fee'] = $data['late_fee'] ?? 0;
                        $data['total_due'] = $data['total_due'] ?? (float) ($data['amount_due'] ?? 0);
                        $data['status'] = 'unpaid';

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('addPayment')
                    ->label('Add Payment')
                    ->icon('heroicon-o-banknotes')
                    ->url(fn (RentInvoice $record) => route('filament.admin.resources.rent-payments.create', [
                        'rent_invoice_id' => $record->id,
                    ]))
                    ->visible(fn () => Gate::allows('create', RentPayment::class))
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('recalc')
                    ->label('Recalc')
                    ->icon('heroicon-o-arrow-path')
                    ->action(function (RentInvoice $record) {
                        Gate::authorize('update', $record);
                        $record->refreshPaymentTotals();
                    }),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
