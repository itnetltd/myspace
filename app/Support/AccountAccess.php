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

    private const VIEW_CAPABILITIES = [
        self::VIEW_ACCOUNT, self::VIEW_PROPERTIES, self::VIEW_OWNERS, self::VIEW_UNITS,
        self::VIEW_TENANTS, self::VIEW_LEASES, self::VIEW_INVOICES, self::VIEW_PAYMENTS,
        self::VIEW_FINANCIAL_STATEMENTS, self::VIEW_MAINTENANCE, self::VIEW_INSPECTIONS, self::VIEW_ASSETS,
        self::VIEW_CONTRACTS, self::VIEW_AGREEMENTS, self::VIEW_SETTINGS,
    ];

    private const ROLE_CAPABILITIES = [
        Account::ROLE_OWNER => [
            ...self::VIEW_CAPABILITIES,
            self::MANAGE_ACCOUNT, self::MANAGE_STAFF, self::MANAGE_PROPERTIES,
            self::MANAGE_OWNERS, self::MANAGE_UNITS, self::MANAGE_TENANTS,
            self::MANAGE_LEASES, self::MANAGE_INVOICES, self::MANAGE_PAYMENTS,
            self::MANAGE_MAINTENANCE, self::MANAGE_INSPECTIONS, self::MANAGE_ASSETS,
            self::MANAGE_CONTRACTS, self::MANAGE_AGREEMENTS, self::MANAGE_SETTINGS,
        ],
        Account::ROLE_ADMINISTRATOR => [
            ...self::VIEW_CAPABILITIES,
            self::MANAGE_ACCOUNT, self::MANAGE_STAFF, self::MANAGE_PROPERTIES,
            self::MANAGE_OWNERS, self::MANAGE_UNITS, self::MANAGE_TENANTS,
            self::MANAGE_LEASES, self::MANAGE_INVOICES, self::MANAGE_PAYMENTS,
            self::MANAGE_MAINTENANCE, self::MANAGE_INSPECTIONS, self::MANAGE_ASSETS,
            self::MANAGE_CONTRACTS, self::MANAGE_AGREEMENTS, self::MANAGE_SETTINGS,
        ],
        Account::ROLE_PROPERTY_MANAGER => [
            self::VIEW_ACCOUNT, self::VIEW_PROPERTIES, self::VIEW_OWNERS, self::VIEW_UNITS,
            self::VIEW_TENANTS, self::VIEW_LEASES, self::VIEW_INVOICES, self::VIEW_PAYMENTS,
            self::VIEW_FINANCIAL_STATEMENTS, self::VIEW_MAINTENANCE, self::VIEW_INSPECTIONS, self::VIEW_ASSETS,
            self::VIEW_CONTRACTS, self::VIEW_AGREEMENTS, self::VIEW_SETTINGS,
            self::MANAGE_PROPERTIES, self::MANAGE_OWNERS, self::MANAGE_UNITS,
            self::MANAGE_TENANTS, self::MANAGE_LEASES, self::MANAGE_MAINTENANCE,
            self::MANAGE_INSPECTIONS, self::MANAGE_ASSETS, self::MANAGE_CONTRACTS,
        ],
        Account::ROLE_ACCOUNTANT => [
            self::VIEW_ACCOUNT, self::VIEW_PROPERTIES, self::VIEW_UNITS, self::VIEW_TENANTS,
            self::VIEW_LEASES, self::VIEW_INVOICES, self::VIEW_PAYMENTS, self::VIEW_CONTRACTS,
            self::VIEW_AGREEMENTS, self::VIEW_FINANCIAL_STATEMENTS,
            self::MANAGE_INVOICES, self::MANAGE_PAYMENTS,
        ],
        Account::ROLE_MAINTENANCE => [
            self::VIEW_ACCOUNT, self::VIEW_PROPERTIES, self::VIEW_UNITS, self::VIEW_TENANTS,
            self::VIEW_LEASES, self::VIEW_MAINTENANCE, self::VIEW_ASSETS,
            self::MANAGE_MAINTENANCE,
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
