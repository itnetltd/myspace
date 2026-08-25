<?php

namespace App\Filament\Resources\OwnerStatementResource\Pages;

use App\Filament\Resources\OwnerStatementResource;
use App\Models\PropertyOwner;
use App\Services\OwnerStatementService;
use App\Support\AccountAccess;
use App\Support\CurrentAccount;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\ListRecords;

class ListOwnerStatements extends ListRecords
{
    protected static string $resource = OwnerStatementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('generate')
                ->label('Generate Monthly Statement')
                ->icon('heroicon-o-document-plus')
                ->visible(fn () => $this->canManageStatements())
                ->form([
                    Forms\Components\Select::make('property_owner_id')
                        ->options(fn () => PropertyOwner::query()->orderBy('name')->pluck('name', 'id'))
                        ->default(fn () => app(CurrentAccount::class)->account()?->self_property_owner_id)
                        ->visible(fn () => app(CurrentAccount::class)->account()?->isPropertyManagementCompany())
                        ->required(),
                    Forms\Components\TextInput::make('statement_month')
                        ->label('Month')
                        ->type('month')
                        ->default(now()->format('Y-m'))
                        ->required(),
                ])
                ->action(function (array $data) {
                    $ownerId = app(CurrentAccount::class)->account()?->isIndividualLandlord()
                        ? app(CurrentAccount::class)->account()->self_property_owner_id
                        : $data['property_owner_id'];
                    $owner = PropertyOwner::query()->findOrFail($ownerId);
                    $statement = app(OwnerStatementService::class)->generateDraft(
                        $owner,
                        $data['statement_month'],
                        auth()->user(),
                    );
                    $this->redirect(OwnerStatementResource::getUrl('view', ['record' => $statement]));
                }),
        ];
    }

    private function canManageStatements(): bool
    {
        $user = auth()->user();
        $account = app(CurrentAccount::class)->account();

        return $user && $account
            && app(AccountAccess::class)->can($user, $account, AccountAccess::MANAGE_OWNER_STATEMENTS);
    }
}
