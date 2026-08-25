<?php

namespace App\Services;

use App\Models\OwnerStatement;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class FinancialPeriodGuard
{
    public function ensureOpen(int $accountId, int $propertyOwnerId, CarbonInterface|string $occurredOn): void
    {
        $date = $occurredOn instanceof CarbonInterface
            ? $occurredOn->toDateString()
            : (string) $occurredOn;

        $isFinalized = OwnerStatement::withoutGlobalScopes()
            ->where('account_id', $accountId)
            ->where('property_owner_id', $propertyOwnerId)
            ->where('status', OwnerStatement::STATUS_FINALIZED)
            ->whereDate('period_start', '<=', $date)
            ->whereDate('period_end', '>=', $date)
            ->exists();

        if ($isFinalized) {
            throw ValidationException::withMessages([
                'occurred_on' => 'This owner financial period is finalized. Record an adjustment in an open period.',
            ]);
        }
    }
}
