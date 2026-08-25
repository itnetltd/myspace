<?php

namespace App\Filament\Resources\RentInvoiceResource\RelationManagers;

use App\Models\RentPayment;
use App\Services\RentPaymentService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\DatePicker::make('paid_on')->required(),
            Forms\Components\TextInput::make('amount')->numeric()->prefix('RWF')->required(),
            Forms\Components\TextInput::make('method')->placeholder('cash / bank / momo'),
            Forms\Components\TextInput::make('reference')->placeholder('txn ref'),
            Forms\Components\Textarea::make('notes')->columnSpanFull(),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('paid_on')->date()->sortable(),
                Tables\Columns\TextColumn::make('amount')->money('RWF')->sortable(),
                Tables\Columns\TextColumn::make('method')->toggleable(),
                Tables\Columns\TextColumn::make('reference')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->using(function (array $data): Model {
                        $data['rent_invoice_id'] = $this->getOwnerRecord()->getKey();

                        return app(RentPaymentService::class)->create($data);
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->using(fn (RentPayment $record, array $data): Model => app(RentPaymentService::class)
                        ->update($record, $data)),
            ]);
    }
}
