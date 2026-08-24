<?php

namespace App\Services;

use App\Models\OwnerDisbursement;
use App\Models\OwnerLedgerEntry;

class OwnerDisbursementService
{
    public function __construct(private readonly OwnerLedgerService $ledger) {}

    public function post(OwnerDisbursement $disbursement): OwnerLedgerEntry
    {
        return $this->ledger->post(
            ['source_type' => 'owner_disbursement', 'source_id' => $disbursement->getKey(), 'source_key' => 'disbursement'],
            [
                'account_id' => $disbursement->account_id,
                'property_owner_id' => $disbursement->property_owner_id,
                'property_id' => null,
                'unit_id' => null,
                'lease_id' => null,
                'entry_number' => 'LE-OD-'.$disbursement->getKey(),
                'entry_type' => OwnerLedgerEntry::TYPE_OWNER_DISBURSEMENT,
                'direction' => OwnerLedgerEntry::DIRECTION_DEBIT,
                'amount' => $disbursement->amount,
                'currency' => $disbursement->currency,
                'occurred_on' => $disbursement->paid_on,
                'description' => 'Owner disbursement'.($disbursement->reference ? ' - '.$disbursement->reference : ''),
                'metadata' => ['method' => $disbursement->method, 'reference' => $disbursement->reference],
                'created_by' => $disbursement->created_by,
            ],
        );
    }
}
