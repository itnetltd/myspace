<?php

namespace App\Services;

use App\Models\Account;
use App\Models\ManagementAgreement;
use App\Models\Property;
use App\Models\PropertyOwner;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class ManagementAgreementResolver
{
    public function resolve(
        Account $account,
        PropertyOwner $owner,
        Property $property,
        CarbonInterface|string $periodStart,
        CarbonInterface|string $periodEnd,
    ): ?ManagementAgreement {
        if (! $account->isPropertyManagementCompany()) {
            return null;
        }

        $query = ManagementAgreement::withoutGlobalScopes()
            ->where('account_id', $account->getKey())
            ->where('property_owner_id', $owner->getKey())
            ->where('status', ManagementAgreement::STATUS_ACTIVE)
            ->whereDate('start_date', '<=', $periodEnd)
            ->where(function ($query) use ($periodStart) {
                $query->whereNull('end_date')->orWhereDate('end_date', '>=', $periodStart);
            });

        $propertySpecific = (clone $query)->where('property_id', $property->getKey())->get();

        if ($propertySpecific->count() > 1) {
            $this->ambiguous($owner, $property, 'property-specific');
        }

        if ($propertySpecific->isNotEmpty()) {
            return $propertySpecific->first();
        }

        $portfolio = $query->whereNull('property_id')->get();

        if ($portfolio->count() > 1) {
            $this->ambiguous($owner, $property, 'portfolio-level');
        }

        return $portfolio->first();
    }

    private function ambiguous(PropertyOwner $owner, Property $property, string $level): never
    {
        throw ValidationException::withMessages([
            'management_agreement' => "Multiple active {$level} agreements apply to {$owner->name} / {$property->name}. Resolve the overlap before posting financial activity.",
        ]);
    }
}
