<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Inspection extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'account_id',
        'unit_id',
        'lease_id',
        'type',
        'inspected_on',
        'inspected_by',
        'general_notes',
        'summary_status',
    ];

    protected $casts = [
        'inspected_on' => 'date',
    ];

    protected function accountParentMap(): array
    {
        return [
            'unit_id' => Unit::class,
            'lease_id' => Lease::class,
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InspectionLine::class);
    }

    /**
     * Policy rates loaded from Settings page (admin-configurable).
     * Stored keys:
     * - deduction.missing_rate (e.g., 1.00)
     * - deduction.damaged_rate (e.g., 0.30)
     */
    public static function deductionPolicy(): array
    {
        // Defaults (same as your previous hardcoded values)
        $missing = (float) Setting::get('deduction.missing_rate', '1.00');
        $damaged = (float) Setting::get('deduction.damaged_rate', '0.30');

        // Safety: clamp to sensible bounds
        $missing = max(0, min($missing, 2.00)); // allow up to 200%
        $damaged = max(0, min($damaged, 2.00));

        return [
            'missingRate' => $missing,
            'damagedRate' => $damaged,
        ];
    }

    /**
     * Build a clean list of issues (missing/damaged) with suggested + applied deductions.
     * - Suggested uses replacement_value if available; otherwise purchase_cost
     * - Applied uses deduction_override if provided; otherwise suggested
     */
    public function reportIssues(): Collection
    {
        $this->loadMissing('lines.assetItem');

        $policy = self::deductionPolicy();
        $missingRate = (float) ($policy['missingRate'] ?? 1.0);
        $damagedRate = (float) ($policy['damagedRate'] ?? 0.30);

        return $this->lines
            ->map(function ($line) use ($missingRate, $damagedRate) {
                $asset = $line->assetItem;

                $assetName = $asset?->name ?? 'Unknown asset';
                $expected = (int) $line->expected_qty;
                $found = (int) $line->found_qty;

                $missingQty = max(0, $expected - $found);

                // Costs (0 if not provided)
                $purchaseCost = (float) ($asset?->purchase_cost ?? 0);
                $replacementValue = (float) ($asset?->replacement_value ?? 0);

                // Preferred unit value for deductions
                $unitValueUsed = $replacementValue > 0 ? $replacementValue : $purchaseCost;

                // Missing deduction
                $missingDeduction = $missingQty > 0
                    ? ($unitValueUsed * $missingRate * $missingQty)
                    : 0.0;

                // Damaged deduction (if flagged or condition says Damaged)
                $isDamaged = ($line->issue_type === 'damaged') || ($line->condition_status === 'Damaged');

                // If damaged, apply damage rate per item found (minimum 1)
                $damagedDeduction = $isDamaged
                    ? ($unitValueUsed * $damagedRate * max(1, $found))
                    : 0.0;

                $suggestedDeduction = (float) ($missingDeduction + $damagedDeduction);

                // Manual override (nullable)
                $override = $line->deduction_override;
                $overrideUsed = ! is_null($override);

                $appliedDeduction = $overrideUsed
                    ? (float) $override
                    : $suggestedDeduction;

                $issueLabel = [];
                if ($missingQty > 0) {
                    $issueLabel[] = "Missing ({$missingQty})";
                }
                if ($isDamaged) {
                    $issueLabel[] = 'Damaged';
                }

                return [
                    'asset' => $assetName,
                    'expected_qty' => $expected,
                    'found_qty' => $found,
                    'missing_qty' => $missingQty,
                    'condition_status' => $line->condition_status,
                    'issue_type' => $line->issue_type,

                    // Costs
                    'purchase_cost' => $purchaseCost,
                    'replacement_value' => $replacementValue,
                    'unit_value_used' => $unitValueUsed,

                    // Deductions
                    'suggested_deduction' => $suggestedDeduction,
                    'applied_deduction' => (float) $appliedDeduction,
                    'override_used' => $overrideUsed,
                    'deduction_reason' => $line->deduction_reason,

                    'issue_label' => implode(', ', $issueLabel) ?: '—',
                    'remarks' => $line->remarks,
                ];
            })
            ->filter(function ($row) {
                return ($row['missing_qty'] > 0)
                    || ($row['issue_type'] === 'damaged')
                    || ($row['condition_status'] === 'Damaged');
            })
            ->values();
    }

    public function suggestedTotalDeduction(): float
    {
        return (float) $this->reportIssues()->sum('suggested_deduction');
    }

    public function appliedTotalDeduction(): float
    {
        return (float) $this->reportIssues()->sum('applied_deduction');
    }

    /**
     * Reads deposit from the linked lease.
     * If your column is security_deposit, change deposit -> security_deposit.
     */
    public function leaseDeposit(): float
    {
        return (float) ($this->lease?->deposit ?? 0);
    }

    public function suggestedRefundAmount(): float
    {
        return max(0, $this->leaseDeposit() - $this->suggestedTotalDeduction());
    }

    public function appliedRefundAmount(): float
    {
        return max(0, $this->leaseDeposit() - $this->appliedTotalDeduction());
    }
}
