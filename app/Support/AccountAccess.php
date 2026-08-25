<?php

namespace App\Support;

use App\Models\Account;
use App\Models\User;

class AccountAccess
{
    public const VIEW_ACCOUNT = 'account.view';

    public const MANAGE_ACCOUNT = 'account.manage';

    public const MANAGE_STAFF = 'staff.manage';

    public const VIEW_PROPERTIES = 'properties.view';

    public const MANAGE_PROPERTIES = 'properties.manage';

    public const VIEW_OWNERS = 'owners.view';

    public const MANAGE_OWNERS = 'owners.manage';

    public const VIEW_UNITS = 'units.view';

    public const MANAGE_UNITS = 'units.manage';

    public const VIEW_TENANTS = 'tenants.view';

    public const MANAGE_TENANTS = 'tenants.manage';

    public const VIEW_LEASES = 'leases.view';

    public const MANAGE_LEASES = 'leases.manage';

    public const VIEW_INVOICES = 'invoices.view';

    public const MANAGE_INVOICES = 'invoices.manage';

    public const VIEW_PAYMENTS = 'payments.view';

    public const MANAGE_PAYMENTS = 'payments.manage';

    public const VIEW_FINANCIAL_STATEMENTS = 'financial-statements.view';

    public const VIEW_MAINTENANCE = 'maintenance.view';

    public const MANAGE_MAINTENANCE = 'maintenance.manage';

    public const VIEW_INSPECTIONS = 'inspections.view';

    public const MANAGE_INSPECTIONS = 'inspections.manage';

    public const VIEW_ASSETS = 'assets.view';

    public const MANAGE_ASSETS = 'assets.manage';

    public const VIEW_CONTRACTS = 'contracts.view';

    public const MANAGE_CONTRACTS = 'contracts.manage';

    public const VIEW_AGREEMENTS = 'agreements.view';

    public const MANAGE_AGREEMENTS = 'agreements.manage';

    public const VIEW_SETTINGS = 'settings.view';

    public const MANAGE_SETTINGS = 'settings.manage';

    public const VIEW_EXPENSES = 'expenses.view';

    public const MANAGE_EXPENSES = 'expenses.manage';

    public const POST_EXPENSES = 'expenses.post';

    public const INITIATE_MAINTENANCE_EXPENSE = 'expenses.initiate-maintenance';

    public const VIEW_OWNER_LEDGER = 'owner-ledger.view';

    public const ADJUST_OWNER_LEDGER = 'owner-ledger.adjust';

    public const VIEW_OWNER_STATEMENTS = 'owner-statements.view';

    public const MANAGE_OWNER_STATEMENTS = 'owner-statements.manage';

    public const FINALIZE_OWNER_STATEMENTS = 'owner-statements.finalize';

    public const VIEW_OWNER_DISBURSEMENTS = 'owner-disbursements.view';

    public const MANAGE_OWNER_DISBURSEMENTS = 'owner-disbursements.manage';

    public const VIEW_MARKETPLACE = 'marketplace.view';

    public const MANAGE_MARKETPLACE = 'marketplace.manage';

    public const ACCEPT_MARKETPLACE_QUOTES = 'marketplace.quotes.accept';

    public const RECORD_MARKETPLACE_OWNER_APPROVAL = 'marketplace.owner-approval.record';

    public const APPROVE_PROVIDER_INVOICES = 'marketplace.invoices.approve';

    private const VIEW_CAPABILITIES = [
        self::VIEW_ACCOUNT, self::VIEW_PROPERTIES, self::VIEW_OWNERS, self::VIEW_UNITS,
        self::VIEW_TENANTS, self::VIEW_LEASES, self::VIEW_INVOICES, self::VIEW_PAYMENTS,
        self::VIEW_FINANCIAL_STATEMENTS, self::VIEW_MAINTENANCE, self::VIEW_INSPECTIONS, self::VIEW_ASSETS,
        self::VIEW_CONTRACTS, self::VIEW_AGREEMENTS, self::VIEW_SETTINGS,
        self::VIEW_EXPENSES, self::VIEW_OWNER_LEDGER, self::VIEW_OWNER_STATEMENTS,
        self::VIEW_OWNER_DISBURSEMENTS,
        self::VIEW_MARKETPLACE,
    ];

    private const ROLE_CAPABILITIES = [
        Account::ROLE_OWNER => [
            ...self::VIEW_CAPABILITIES,
            self::MANAGE_ACCOUNT, self::MANAGE_STAFF, self::MANAGE_PROPERTIES,
            self::MANAGE_OWNERS, self::MANAGE_UNITS, self::MANAGE_TENANTS,
            self::MANAGE_LEASES, self::MANAGE_INVOICES, self::MANAGE_PAYMENTS,
            self::MANAGE_MAINTENANCE, self::MANAGE_INSPECTIONS, self::MANAGE_ASSETS,
            self::MANAGE_CONTRACTS, self::MANAGE_AGREEMENTS, self::MANAGE_SETTINGS,
            self::MANAGE_EXPENSES, self::POST_EXPENSES, self::ADJUST_OWNER_LEDGER,
            self::MANAGE_OWNER_STATEMENTS, self::FINALIZE_OWNER_STATEMENTS,
            self::MANAGE_OWNER_DISBURSEMENTS,
            self::MANAGE_MARKETPLACE, self::ACCEPT_MARKETPLACE_QUOTES,
            self::RECORD_MARKETPLACE_OWNER_APPROVAL, self::APPROVE_PROVIDER_INVOICES,
        ],
        Account::ROLE_ADMINISTRATOR => [
            ...self::VIEW_CAPABILITIES,
            self::MANAGE_ACCOUNT, self::MANAGE_STAFF, self::MANAGE_PROPERTIES,
            self::MANAGE_OWNERS, self::MANAGE_UNITS, self::MANAGE_TENANTS,
            self::MANAGE_LEASES, self::MANAGE_INVOICES, self::MANAGE_PAYMENTS,
            self::MANAGE_MAINTENANCE, self::MANAGE_INSPECTIONS, self::MANAGE_ASSETS,
            self::MANAGE_CONTRACTS, self::MANAGE_AGREEMENTS, self::MANAGE_SETTINGS,
            self::MANAGE_EXPENSES, self::POST_EXPENSES, self::ADJUST_OWNER_LEDGER,
            self::MANAGE_OWNER_STATEMENTS, self::FINALIZE_OWNER_STATEMENTS,
            self::MANAGE_OWNER_DISBURSEMENTS,
            self::MANAGE_MARKETPLACE, self::ACCEPT_MARKETPLACE_QUOTES,
            self::RECORD_MARKETPLACE_OWNER_APPROVAL, self::APPROVE_PROVIDER_INVOICES,
        ],
        Account::ROLE_PROPERTY_MANAGER => [
            self::VIEW_ACCOUNT, self::VIEW_PROPERTIES, self::VIEW_OWNERS, self::VIEW_UNITS,
            self::VIEW_TENANTS, self::VIEW_LEASES, self::VIEW_INVOICES, self::VIEW_PAYMENTS,
            self::VIEW_FINANCIAL_STATEMENTS, self::VIEW_MAINTENANCE, self::VIEW_INSPECTIONS, self::VIEW_ASSETS,
            self::VIEW_CONTRACTS, self::VIEW_AGREEMENTS, self::VIEW_SETTINGS,
            self::VIEW_EXPENSES, self::VIEW_OWNER_LEDGER, self::VIEW_OWNER_STATEMENTS,
            self::VIEW_OWNER_DISBURSEMENTS,
            self::VIEW_MARKETPLACE,
            self::MANAGE_PROPERTIES, self::MANAGE_OWNERS, self::MANAGE_UNITS,
            self::MANAGE_TENANTS, self::MANAGE_LEASES, self::MANAGE_MAINTENANCE,
            self::MANAGE_INSPECTIONS, self::MANAGE_ASSETS, self::MANAGE_CONTRACTS,
            self::MANAGE_EXPENSES,
            self::MANAGE_MARKETPLACE, self::ACCEPT_MARKETPLACE_QUOTES,
            self::RECORD_MARKETPLACE_OWNER_APPROVAL,
        ],
        Account::ROLE_ACCOUNTANT => [
            self::VIEW_ACCOUNT, self::VIEW_PROPERTIES, self::VIEW_UNITS, self::VIEW_TENANTS,
            self::VIEW_LEASES, self::VIEW_INVOICES, self::VIEW_PAYMENTS, self::VIEW_CONTRACTS,
            self::VIEW_AGREEMENTS, self::VIEW_FINANCIAL_STATEMENTS,
            self::VIEW_MARKETPLACE,
            self::MANAGE_INVOICES, self::MANAGE_PAYMENTS,
            self::VIEW_EXPENSES, self::MANAGE_EXPENSES, self::POST_EXPENSES,
            self::VIEW_OWNER_LEDGER, self::ADJUST_OWNER_LEDGER,
            self::VIEW_OWNER_STATEMENTS, self::MANAGE_OWNER_STATEMENTS,
            self::FINALIZE_OWNER_STATEMENTS, self::VIEW_OWNER_DISBURSEMENTS,
            self::MANAGE_OWNER_DISBURSEMENTS,
            self::APPROVE_PROVIDER_INVOICES,
        ],
        Account::ROLE_MAINTENANCE => [
            self::VIEW_ACCOUNT, self::VIEW_PROPERTIES, self::VIEW_UNITS, self::VIEW_TENANTS,
            self::VIEW_LEASES, self::VIEW_MAINTENANCE, self::VIEW_ASSETS,
            self::VIEW_EXPENSES, self::MANAGE_MAINTENANCE, self::INITIATE_MAINTENANCE_EXPENSE,
            self::VIEW_MARKETPLACE, self::MANAGE_MARKETPLACE,
        ],
        Account::ROLE_VIEWER => [...self::VIEW_CAPABILITIES],
    ];

    public function role(User $user, Account|int $account): ?string
    {
        $accountId = $account instanceof Account ? $account->getKey() : $account;

        return $user->accounts()
            ->wherePivot('is_active', true)
            ->whereKey($accountId)
            ->value('account_user.role');
    }

    public function can(User $user, Account|int $account, string $capability): bool
    {
        $role = $this->role($user, $account);

        return $role !== null
            && in_array($capability, self::ROLE_CAPABILITIES[$role] ?? [], true);
    }

    public function canView(User $user, Account|int $account): bool
    {
        return $this->can($user, $account, self::VIEW_ACCOUNT);
    }

    public function canAdminister(User $user, Account|int $account): bool
    {
        return $this->can($user, $account, self::MANAGE_ACCOUNT);
    }
}
