<?php

namespace App\Services;

use App\Models\ManagementAgreement;
use App\Models\OwnerLedgerEntry;
use App\Models\PropertyOwner;
use App\Support\Money;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class ManagementFeeService
{
    public function __construct(
        private readonly ManagementAgreementResolver $resolver,
        private readonly OwnerLedgerService $ledger,
    ) {}

    public function synchronize(PropertyOwner $owner, CarbonInterface|string $start, CarbonInterface|string $end): array
    {
        $start = Carbon::parse($start)->startOfDay();
        $end = Carbon::parse($end)->endOfDay();
        $account = $owner->account;

        if ($account->isIndividualLandlord()) {
            return [];
        }

        $agreementGroups = [];
        $warnings = [];

        foreach ($owner->properties()->orderBy('id')->get() as $property) {
            $agreement = $this->resolver->resolve($account, $owner, $property, $start, $end);

            if (! $agreement) {
                $warnings[] = "No active management agreement applies to {$property->name}; no fee was created.";

                continue;
            }

            if ($agreement->fee_migration_review_required) {
                throw ValidationException::withMessages([
                    'management_agreement' => "Agreement {$agreement->reference_number} has an ambiguous legacy percentage-plus-fixed fee and requires human review.",
                ]);
            }

            $principalMinor = 0;
            OwnerLedgerEntry::withoutGlobalScopes()
                ->where('account_id', $account->getKey())
                ->where('property_owner_id', $owner->getKey())
                ->where('property_id', $property->getKey())
                ->where('entry_type', OwnerLedgerEntry::TYPE_RENT_INCOME)
                ->whereDate('occurred_on', '>=', $start->toDateString())
                ->whereDate('occurred_on', '<=', $end->toDateString())
                ->orderBy('id')
                ->each(function (OwnerLedgerEntry $entry) use (&$principalMinor) {
                    $principalMinor += Money::toMinor($entry->amount);
                });

            $agreementGroups[$agreement->getKey()] ??= [
                'agreement' => $agreement,
                'principal_minor' => 0,
                'property_ids' => [],
            ];
            $agreementGroups[$agreement->getKey()]['principal_minor'] += $principalMinor;
            $agreementGroups[$agreement->getKey()]['property_ids'][] = $property->getKey();
        }

        foreach ($agreementGroups as $group) {
            /** @var ManagementAgreement $agreement */
            $agreement = $group['agreement'];
            $percentageMinor = in_array($agreement->management_fee_type, [
                ManagementAgreement::FEE_PERCENTAGE,
                ManagementAgreement::FEE_PERCENTAGE_PLUS_FIXED,
            ], true)
                ? Money::percentage($group['principal_minor'], $agreement->management_fee_percentage)
                : 0;
            $fixedMinor = in_array($agreement->management_fee_type, [
                ManagementAgreement::FEE_FIXED,
                ManagementAgreement::FEE_PERCENTAGE_PLUS_FIXED,
            ], true)
                ? Money::toMinor($agreement->management_fee_fixed_amount)
                : 0;
            $totalMinor = $percentageMinor + $fixedMinor;
            $sourceKey = 'fee:'.$start->toDateString().':'.$end->toDateString();

            if ($totalMinor === 0) {
                $this->ledger->removeUnlockedComponent($account->getKey(), 'management_agreement', $agreement->getKey(), $sourceKey);

                continue;
            }

            $this->ledger->post(
                ['source_type' => 'management_agreement', 'source_id' => $agreement->getKey(), 'source_key' => $sourceKey],
                [
                    'account_id' => $account->getKey(),
                    'property_owner_id' => $owner->getKey(),
                    'property_id' => $agreement->property_id,
                    'unit_id' => null,
                    'lease_id' => null,
                    'entry_number' => 'LE-MF-'.$agreement->getKey().'-'.$start->format('Ymd').'-'.$end->format('Ymd'),
                    'entry_type' => OwnerLedgerEntry::TYPE_MANAGEMENT_FEE,
                    'direction' => OwnerLedgerEntry::DIRECTION_DEBIT,
                    'amount' => Money::fromMinor($totalMinor),
                    'currency' => $account->currency,
                    'occurred_on' => $end->toDateString(),
                    'description' => 'Management fee for '.$start->format('F Y'),
                    'metadata' => [
                        'principal_collected' => Money::fromMinor($group['principal_minor']),
                        'percentage' => $agreement->management_fee_percentage,
                        'percentage_fee' => Money::fromMinor($percentageMinor),
                        'fixed_fee' => Money::fromMinor($fixedMinor),
                        'property_ids' => $group['property_ids'],
                    ],
                    'created_by' => auth()->id(),
                ],
            );
        }

        return $warnings;
    }
}
