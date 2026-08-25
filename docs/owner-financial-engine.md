# Owner financial engine

Phase 3A uses cash-basis accounting. Rent and late-fee income enters the owner ledger only when a payment is recorded. Payments are allocated in `paid_on`, then `id`, order: outstanding principal first, then outstanding late fees. Management percentage fees therefore use collected principal, never invoiced or expected rent.

Payment amounts beyond principal plus assessed late fees remain visible on the original payment as an unallocated amount. They are not owner income and do not enter the owner ledger. A future tenant-credit module may later formalize their treatment.

The owner ledger is the financial source of truth. Credits are rent, late fees, and credit adjustments. Debits are posted property expenses, management fees, owner disbursements, and debit adjustments. An owner balance is calculated as credits minus debits; no mutable balance is stored.

Fixed management fees apply once per applicable management agreement for each generated monthly period when the agreement overlaps that period. Phase 3A does not prorate fixed fees. Property-specific agreements take priority over portfolio agreements. Ambiguous overlaps block generation for human correction, and no fee is invented when an agreement is missing.

Draft statements can be regenerated. Finalization snapshots their lines and locks the included ledger entries. Historical corrections must use a controlled adjustment or expense reversal rather than changing finalized history. Recording an owner disbursement records an already-completed external payment; it does not initiate a bank, mobile-money, or card transfer.

Owner statements cover one calendar month. `statement_month` is stored as `YYYY-MM`, and the service derives the first and last calendar dates. A finalized owner month is closed: new or moved ledger activity cannot be dated inside it, and corrections must be posted as adjustments in an open month.

Legacy `percentage_plus_fixed` agreements retain the old value as the percentage component, use zero as the unknown fixed component, and remain blocked from fee generation until an authorized user confirms both components against the signed agreement.

The historical rent-ledger migration contains its own deterministic database backfill rather than calling current application services. Its rollback is intentionally non-destructive because later legitimate rent-payment ledger entries cannot be distinguished safely from the original backfill solely by `source_type`.
