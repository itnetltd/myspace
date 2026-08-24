<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Support\AccountAccess;
use App\Support\CurrentAccount;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class DeductionPolicySettings extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationGroup = 'MySpaces Estate';

    protected static ?string $navigationLabel = 'Deduction Policy';

    protected static ?string $title = 'Deduction Policy Settings';

    protected static string $view = 'filament.pages.deduction-policy-settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();
        $account = app(CurrentAccount::class)->account();

        return $user && $account
            && app(AccountAccess::class)->can($user, $account, AccountAccess::MANAGE_SETTINGS);
    }

    public function mount(): void
    {
        $this->form->fill([
            'missing_rate_percent' => (float) Setting::get('deduction.missing_rate', '1.00') * 100,
            'damaged_rate_percent' => (float) Setting::get('deduction.damaged_rate', '0.30') * 100,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Default Deduction Policy')
                    ->description('These rates are used for suggested deductions in Move-Out reports. Manual overrides still apply per line.')
                    ->schema([
                        TextInput::make('missing_rate_percent')
                            ->label('Missing Item Rate (%)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(200)
                            ->required()
                            ->helperText('Example: 100 means charge 100% of replacement value for missing items.'),

                        TextInput::make('damaged_rate_percent')
                            ->label('Damaged Item Rate (%)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(200)
                            ->required()
                            ->helperText('Example: 30 means charge 30% of replacement value for damaged items.'),
                    ])->columns(2),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $state = $this->form->getState();

        $missingRate = ((float) ($state['missing_rate_percent'] ?? 100)) / 100;
        $damagedRate = ((float) ($state['damaged_rate_percent'] ?? 30)) / 100;

        Setting::set('deduction.missing_rate', number_format($missingRate, 2, '.', ''));
        Setting::set('deduction.damaged_rate', number_format($damagedRate, 2, '.', ''));

        Notification::make()
            ->title('Deduction policy saved')
            ->success()
            ->send();
    }
}
