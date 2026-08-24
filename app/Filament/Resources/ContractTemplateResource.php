<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContractTemplateResource\Pages;
use App\Models\ContractTemplate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ContractTemplateResource extends Resource
{
    protected static ?string $model = ContractTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Template Details')
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Select::make('language')
                            ->required()
                            ->options([
                                'en' => 'English',
                                'fr' => 'French',
                                'rw' => 'Kinyarwanda',
                            ])
                            ->searchable(),

                        Forms\Components\TextInput::make('version')
                            ->required()
                            ->default('1.0')
                            ->maxLength(20),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ]),

                Forms\Components\Section::make('Template Body (HTML)')
                    ->description('Use placeholders like {{tenant_full_name}}, {{unit_code}}, {{monthly_rent}}, etc.')
                    ->schema([
                        Forms\Components\Textarea::make('body_html')
                            ->label('HTML')
                            ->rows(18)
                            ->required()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('language')
                    ->label('Lang')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('version')
                    ->label('Version')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Active'),

                Tables\Filters\SelectFilter::make('language')
                    ->options([
                        'en' => 'English',
                        'fr' => 'French',
                        'rw' => 'Kinyarwanda',
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
            'index' => Pages\ListContractTemplates::route('/'),
            'create' => Pages\CreateContractTemplate::route('/create'),
            'edit' => Pages\EditContractTemplate::route('/{record}/edit'),
        ];
    }
}