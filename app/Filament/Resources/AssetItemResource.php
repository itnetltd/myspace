<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AssetItemResource\Pages;
use App\Models\AssetItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AssetItemResource extends Resource
{
    protected static ?string $model = AssetItem::class;
    protected static ?string $navigationIcon = 'heroicon-o-cube';
    protected static ?string $navigationGroup = 'MySpaces Estate';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Asset Item')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('category')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('brand')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('model')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('serial_number')
                        ->maxLength(255),

                    Forms\Components\DatePicker::make('purchase_date'),

                    Forms\Components\TextInput::make('purchase_cost')
                        ->numeric()
                        ->prefix('RWF'),

                    // ✅ New: Replacement Value (preferred for suggested deductions)
                    Forms\Components\TextInput::make('replacement_value')
                        ->numeric()
                        ->prefix('RWF')
                        ->helperText('Used for suggested deductions. If empty, system uses purchase cost.'),

                    Forms\Components\Textarea::make('notes')
                        ->columnSpanFull(),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('category')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('brand')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('model')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('serial_number')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('purchase_cost')
                    ->money('RWF')
                    ->toggleable(),

                // ✅ New: Replacement Value column
                Tables\Columns\TextColumn::make('replacement_value')
                    ->money('RWF')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAssetItems::route('/'),
            'create' => Pages\CreateAssetItem::route('/create'),
            'edit' => Pages\EditAssetItem::route('/{record}/edit'),
        ];
    }
}