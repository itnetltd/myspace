<?php

namespace App\Filament\Pages;

use App\Models\Account;
use App\Support\CurrentAccount;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class SwitchAccount extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.pages.switch-account';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'account_id' => app(CurrentAccount::class)->id(),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('account_id')
                    ->label('Workspace')
                    ->options(fn () => auth()->user()->accounts()
                        ->wherePivot('is_active', true)
                        ->where('accounts.status', Account::STATUS_ACTIVE)
                        ->orderBy('accounts.name')
                        ->pluck('accounts.name', 'accounts.id'))
                    ->required(),
            ])
            ->statePath('data');
    }

    public function switchAccount(): void
    {
        $state = $this->form->getState();
        app(CurrentAccount::class)->switch(auth()->user(), (int) $state['account_id']);

        Notification::make()->title('Workspace switched')->success()->send();
        $this->redirect(route('filament.admin.pages.dashboard'));
    }

    public static function canAccess(): bool
    {
        return auth()->check()
            && auth()->user()->accounts()->wherePivot('is_active', true)->exists();
    }
}
