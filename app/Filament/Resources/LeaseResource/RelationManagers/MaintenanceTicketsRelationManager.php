<?php

namespace App\Filament\Resources\LeaseResource\RelationManagers;

use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class MaintenanceTicketsRelationManager extends RelationManager
{
    protected static string $relationship = 'maintenanceTickets';
    protected static ?string $title = 'Maintenance Tickets';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->searchable(),
                Tables\Columns\TextColumn::make('category')->toggleable(),
                Tables\Columns\TextColumn::make('priority')->badge()->sortable(),
                Tables\Columns\TextColumn::make('status')->badge()->sortable(),
                Tables\Columns\TextColumn::make('reported_on')->date()->sortable(),
                Tables\Columns\TextColumn::make('resolved_on')->date()->toggleable(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('New Ticket')
                    ->form([
                        Forms\Components\TextInput::make('title')->required()->maxLength(255),
                        Forms\Components\TextInput::make('category')->placeholder('plumbing / electrical ...'),
                        Forms\Components\Select::make('priority')->options([
                            'low' => 'Low',
                            'medium' => 'Medium',
                            'high' => 'High',
                            'urgent' => 'Urgent',
                        ])->default('medium')->required(),
                        Forms\Components\Select::make('status')->options([
                            'open' => 'Open',
                            'in_progress' => 'In Progress',
                            'resolved' => 'Resolved',
                            'closed' => 'Closed',
                        ])->default('open')->required(),
                        Forms\Components\DatePicker::make('reported_on')->default(now())->required(),
                        Forms\Components\Textarea::make('description')->columnSpanFull(),
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}