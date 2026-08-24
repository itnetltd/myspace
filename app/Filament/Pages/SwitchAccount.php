<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class SwitchAccount extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.pages.switch-account';

    public static function canAccess(): bool
    {
        return false;
    }
}
