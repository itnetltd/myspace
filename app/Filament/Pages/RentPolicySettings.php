<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Form;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use App\Models\Setting;

class RentPolicySettings extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationGroup = 'MySpaces Estate';
    protected static ?string $navigationLabel = 'Rent Policy';
    protected static ?string $title = 'Rent Policy Settings';

    protected static string $view = 'filament.pages.rent-policy-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'invoice_months_ahead' => (int) Setting::get('rent.invoice_months_ahead', 6),
            'due_day' => (int) Setting::get('rent.due_day', 5),

            'late_fee_enabled' => (bool) ((int) Setting::get('rent.late_fee_enabled', 1)),
            'late_fee_type' => Setting::get('rent.late_fee_type', 'fixed'),
            'late_fee_value' => (float) Setting::get('rent.late_fee_value', 5000),
            'late_fee_grace_days' => (int) Setting::get('rent.late_fee_grace_days', 3),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Invoice Generation')
                    ->schema([
                        TextInput::make('invoice_months_ahead')
                            ->numeric()->minValue(1)->maxValue(36)
                            ->required()
                            ->helperText('How many future months to auto-generate when lease becomes Active.'),

                        TextInput::make('due_day')
                            ->numeric()->minValue(1)->maxValue(28)
                            ->required()
                            ->helperText('Due day of month used for invoices (1-28).'),
                    ])->columns(2),

                Section::make('Late Fee Policy')
                    ->description('Late fee applies when invoice is overdue beyond grace days.')
                    ->schema([
                        Toggle::make('late_fee_enabled')->label('Enable Late Fees'),

                        Select::make('late_fee_type')
                            ->options([
                                'fixed' => 'Fixed Amount (RWF)',
                                'percent' => 'Percentage of Amount Due (%)',
                            ])
                            ->required(),

                        TextInput::make('late_fee_value')
                            ->numeric()
                            ->minValue(0)
                            ->required()
                            ->helperText('If fixed: RWF amount. If percent: percent number e.g. 5.'),

                        TextInput::make('late_fee_grace_days')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(60)
                            ->required(),
                    ])->columns(2),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $s = $this->form->getState();

        Setting::set('rent.invoice_months_ahead', (string) (int) ($s['invoice_months_ahead'] ?? 6));
        Setting::set('rent.due_day', (string) (int) ($s['due_day'] ?? 5));

        Setting::set('rent.late_fee_enabled', (string) ((int) !empty($s['late_fee_enabled'])));
        Setting::set('rent.late_fee_type', (string) ($s['late_fee_type'] ?? 'fixed'));
        Setting::set('rent.late_fee_value', (string) ($s['late_fee_value'] ?? 0));
        Setting::set('rent.late_fee_grace_days', (string) (int) ($s['late_fee_grace_days'] ?? 0));

        Notification::make()->title('Rent policy saved')->success()->send();
    }
}