<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
// (These imports are optional; leaving them is fine)
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    use BelongsToAccount;

    // Status constants (prevents inconsistent strings everywhere)
    public const STATUS_VACANT = 'vacant';

    public const STATUS_OCCUPIED = 'occupied';

    public const STATUS_BLOCKED = 'blocked'; // optional (e.g., under renovation)

    protected $fillable = [
        'account_id',
        'property_id',
        'unit_code',
        'bedrooms',
        'bathrooms',
        'monthly_rent',
        'status',
        'notes',
    ];

    // Helpful casting (so calculations & sorting behave correctly)
    protected $casts = [
        'bedrooms' => 'integer',
        'bathrooms' => 'integer',
        'monthly_rent' => 'decimal:2',
    ];

    protected function accountParentMap(): array
    {
        return ['property_id' => Property::class];
    }

    /*
    |--------------------------------------------------------------------------
    | Model Events (Non-breaking additions)
    |--------------------------------------------------------------------------
    */
    protected static function booted(): void
    {
        // If status is not set during create, default to vacant.
        static::creating(function (self $unit) {
            if (empty($unit->status)) {
                $unit->status = self::STATUS_VACANT;
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships (UNCHANGED)
    |--------------------------------------------------------------------------
    */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function leases(): HasMany
    {
        return $this->hasMany(Lease::class);
    }

    public function unitAssets(): HasMany
    {
        return $this->hasMany(UnitAsset::class);
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(Inspection::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers (Non-breaking additions)
    |--------------------------------------------------------------------------
    */
    public function isVacant(): bool
    {
        return $this->status === self::STATUS_VACANT;
    }

    public function isOccupied(): bool
    {
        return $this->status === self::STATUS_OCCUPIED;
    }

    public function isBlocked(): bool
    {
        return $this->status === self::STATUS_BLOCKED;
    }

    public function markVacant(): bool
    {
        return $this->update(['status' => self::STATUS_VACANT]);
    }

    public function markOccupied(): bool
    {
        return $this->update(['status' => self::STATUS_OCCUPIED]);
    }

    public function markBlocked(): bool
    {
        return $this->update(['status' => self::STATUS_BLOCKED]);
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors (Non-breaking additions)
    |--------------------------------------------------------------------------
    */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_VACANT => 'Vacant',
            self::STATUS_OCCUPIED => 'Occupied',
            self::STATUS_BLOCKED => 'Blocked',
            default => ucfirst((string) $this->status),
        };
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes (Non-breaking additions)
    |--------------------------------------------------------------------------
    */
    public function scopeVacant($query)
    {
        return $query->where('status', self::STATUS_VACANT);
    }

    public function scopeOccupied($query)
    {
        return $query->where('status', self::STATUS_OCCUPIED);
    }

    public function scopeBlocked($query)
    {
        return $query->where('status', self::STATUS_BLOCKED);
    }
}
