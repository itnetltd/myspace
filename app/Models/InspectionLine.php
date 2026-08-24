<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class InspectionLine extends Model
{
    protected $fillable = [
        'inspection_id',
        'asset_item_id',
        'expected_qty',
        'found_qty',
        'condition_status',
        'issue_type',
        'remarks',
        'evidence_photo_path',

        // NEW (Manual deduction override)
        'deduction_override',
        'deduction_reason',
    ];

    protected $casts = [
        'expected_qty' => 'integer',
        'found_qty' => 'integer',
        'deduction_override' => 'decimal:2',
    ];

    /**
     * Optional: keep consistent allowed values (useful later in validation/UI).
     */
    public const CONDITION_EXCELLENT = 'Excellent';

    public const CONDITION_GOOD = 'Good';

    public const CONDITION_FAIR = 'Fair';

    public const CONDITION_DAMAGED = 'Damaged';

    public const CONDITION_MISSING = 'Missing';

    public const ISSUE_NONE = 'none';

    public const ISSUE_DAMAGED = 'damaged';

    public const ISSUE_MISSING = 'missing';

    public const ISSUE_OTHER = 'other';

    protected static function booted(): void
    {
        static::saving(function (self $line) {
            $inspection = Inspection::withoutGlobalScopes()->find($line->inspection_id);
            $asset = AssetItem::withoutGlobalScopes()->find($line->asset_item_id);

            if (! $inspection || ! $asset || (int) $inspection->account_id !== (int) $asset->account_id) {
                throw ValidationException::withMessages([
                    'asset_item_id' => 'The selected asset belongs to another account.',
                ]);
            }

            // Normalize nulls to 0 to avoid comparison issues
            $expected = (int) ($line->expected_qty ?? 0);
            $found = (int) ($line->found_qty ?? 0);

            // If an override is set, ensure it is non-negative (defensive)
            if (! is_null($line->deduction_override)) {
                $line->deduction_override = max(0, (float) $line->deduction_override);

                // If user set an override, strongly recommend a reason (not hard-blocking)
                // You can enforce validation in Filament instead.
                if (blank($line->deduction_reason)) {
                    $line->deduction_reason = 'Manual override applied.';
                }
            }

            // Auto-detect missing when quantities mismatch (unless user explicitly set "other")
            // Keep your existing behavior exactly, but preserve "other" when explicitly chosen.
            if ($found < $expected) {
                if (($line->issue_type ?? self::ISSUE_NONE) !== self::ISSUE_OTHER) {
                    $line->issue_type = self::ISSUE_MISSING;

                    // Optional but recommended: align condition with missing
                    // Comment this out if you want to keep condition separate.
                    $line->condition_status = self::CONDITION_MISSING;
                }
            } else {
                // If previously marked missing but now corrected, reset to none (unless damaged/other)
                if (($line->issue_type ?? self::ISSUE_NONE) === self::ISSUE_MISSING) {
                    $line->issue_type = self::ISSUE_NONE;

                    // If we auto-set to Missing earlier, revert to Good (safe default)
                    if (($line->condition_status ?? self::CONDITION_GOOD) === self::CONDITION_MISSING) {
                        $line->condition_status = self::CONDITION_GOOD;
                    }
                }
            }
        });
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class);
    }

    public function assetItem(): BelongsTo
    {
        return $this->belongsTo(AssetItem::class);
    }

    /**
     * Helper: how many items are missing (0 if none).
     */
    public function missingQty(): int
    {
        $expected = (int) ($this->expected_qty ?? 0);
        $found = (int) ($this->found_qty ?? 0);

        return max(0, $expected - $found);
    }

    /**
     * Helper: quick flag for UI/table badges.
     */
    public function hasIssue(): bool
    {
        return in_array($this->issue_type, [self::ISSUE_MISSING, self::ISSUE_DAMAGED, self::ISSUE_OTHER], true);
    }

    /**
     * Helper: whether the manual override is being used.
     */
    public function isOverrideUsed(): bool
    {
        return ! is_null($this->deduction_override);
    }

    /**
     * Helper: safe accessor for override amount (0 if none).
     */
    public function overrideAmount(): float
    {
        return (float) ($this->deduction_override ?? 0);
    }
}
