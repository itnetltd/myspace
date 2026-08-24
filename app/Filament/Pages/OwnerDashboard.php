<?php

namespace App\Filament\Pages;

use App\Models\PropertyOwner;
use App\Services\OwnerFinancialSummaryService;
use App\Support\AccountAccess;
use App\Support\CurrentAccount;
use Filament\Pages\Page;

class OwnerDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-pie';

    protected static ?string $navigationGroup = 'Owner Financials';

    protected static ?string $navigationLabel = 'Owner Dashboard';

    protected static string $view = 'filament.pages.owner-dashboard';

    public ?int $ownerId = null;

    public function mount(): void
    {
        $account = app(CurrentAccount::class)->account();
        $this->ownerId = $account?->isIndividualLandlord()
            ? $account->self_property_owner_id
            : PropertyOwner::query()->orderBy('name')->value('id');
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();
        $account = app(CurrentAccount::class)->account();

        return $user && $account
            && app(AccountAccess::class)->can($user, $account, AccountAccess::VIEW_OWNER_LEDGER);
    }

    protected function getViewData(): array
    {
        $account = app(CurrentAccount::class)->account();
        $owners = PropertyOwner::query()->orderBy('name')->get();
        $owner = $this->ownerId ? PropertyOwner::query()->find($this->ownerId) : null;

        return [
            'account' => $account,
            'owners' => $owners,
            'owner' => $owner,
            'summary' => $owner ? app(OwnerFinancialSummaryService::class)->forOwner($owner) : null,
        ];
    }
}
