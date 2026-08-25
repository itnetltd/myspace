<?php

namespace App\Filament\Provider\Resources;

use App\Filament\Provider\Resources\SupplierProductResource\Pages;
use App\Models\SupplierProduct;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SupplierProductResource extends Resource
{
    protected static ?string $model = SupplierProduct::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationLabel = 'Products';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->required(), Forms\Components\TextInput::make('sku'),
            Forms\Components\TextInput::make('category'), Forms\Components\TextInput::make('brand'),
            Forms\Components\TextInput::make('model'), Forms\Components\Textarea::make('description')->columnSpanFull(),
            Forms\Components\TextInput::make('unit_price')->numeric()->required(),
            Forms\Components\TextInput::make('currency')->default('RWF')->required()->maxLength(3),
            Forms\Components\Select::make('stock_status')->options(array_combine(SupplierProduct::STOCK_STATUSES, SupplierProduct::STOCK_STATUSES))->default('unknown')->required(),
            Forms\Components\TextInput::make('stock_quantity')->numeric(),
            Forms\Components\TextInput::make('warranty_months')->numeric(),
            Forms\Components\TextInput::make('estimated_delivery_days')->numeric(),
            Forms\Components\FileUpload::make('image_path')->directory('private/provider-products'),
            Forms\Components\Toggle::make('is_active')->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->searchable(), Tables\Columns\TextColumn::make('sku'),
            Tables\Columns\TextColumn::make('unit_price')->money(fn ($record) => $record->currency),
            Tables\Columns\TextColumn::make('stock_status')->badge(), Tables\Columns\IconColumn::make('is_active')->boolean(),
        ])->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListSupplierProducts::route('/'), 'create' => Pages\CreateSupplierProduct::route('/create'), 'edit' => Pages\EditSupplierProduct::route('/{record}/edit')];
    }
}
