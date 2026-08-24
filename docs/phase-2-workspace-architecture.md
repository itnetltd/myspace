# Phase 2 workspace architecture

## Account and membership

`Account` is the organization using MySpaces Estate. It is not necessarily the
legal owner of a property. Users join accounts through `account_user`; the pivot
role is deliberately account-specific because the same user may be an owner in
one account and a viewer in another.

Spatie Laravel Permission remains the place for future platform-wide abilities
(for example, platform support or system administration). Spatie teams are not
enabled, so Spatie roles must not be used as workspace roles. Workspace access is
currently decided by the active `account_user` membership and its role.

`CurrentAccount` resolves the active account from the authenticated user's active
memberships and rejects account switching without such a membership. The
`current.account` middleware requires that context for Filament and report routes.

## Owners and management companies

`PropertyOwner` is the legal/economic owner. Every owner belongs to the Account
that manages its portfolio. Every Property belongs to both an Account and a
PropertyOwner. The legacy `owner_name` and `owner_phone` columns are retained only
for safe rollback/reference; application relationships are now authoritative.

`ManagementAgreement` records a management company's agreement with an owner. A
nullable Property relationship allows a later portfolio-wide agreement without a
schema break. Policies and navigation restrict this module to Accounts of type
`property_management_company`.

## Account identifiers on domain tables

Direct `account_id` columns are used on records that are independently listed,
searched, route-bound, selected in Filament, or downloaded: properties, units,
tenants, leases, asset items, unit assets, inspections, invoices, payments,
maintenance tickets, contract templates, lease contracts, and settings. This
provides a uniform global scope and makes guessed IDs fail before record access.

`property_owners` and `management_agreements` also carry `account_id` because they
are workspace roots. `inspection_lines` does not duplicate it: lines are only
meaningful through an Inspection, and model validation requires their selected
asset and inspection to share an Account.

Database foreign keys and non-null constraints complement application checks.
Model parent maps reject cross-account foreign keys even if a browser submits a
forged relationship ID.

## Authorization layers

1. `CurrentAccount` validates active membership and switching.
2. `AccountScope` limits account-owned Eloquent queries, including route binding
   and Filament relationship searches.
3. Policies require both matching ownership and a permitted membership role.
4. Account-parent validation rejects cross-workspace relationships on writes.
5. Controllers explicitly authorize PDFs and contract generation.

Console processes are intentionally not globally scoped because migrations and
seeders must operate across accounts. Any future queued job must establish and
validate its Account explicitly before reading account-owned models.
