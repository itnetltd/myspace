<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class OwnerStatementLine extends Model
{
    protected $fillable = [
        'owner_statement_id', 'property_id', 'unit_id', 'line_type', 'description',
        'credit', 'debit', 'occurred_on', 'source_type', 'source_id', 'metadata',
    ];

    protected $casts = [
        'credit' => 'decimal:2',
        'debit' => 'decimal:2',
        'occurred_on' => 'date',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $line) {
            $statement = OwnerStatement::withoutGlobalScopes()->find($line->owner_statement_id);

            if (! $statement || $statement->status === OwnerStatement::STATUS_FINALIZED) {
                throw ValidationException::withMessages([
                    'owner_statement_id' => 'Finalized statement lines are immutable.',
                ]);
            }
        });

        static::deleting(function (self $line) {
            if ($line->statement?->status === OwnerStatement::STATUS_FINALIZED) {
                throw ValidationException::withMessages([
                    'owner_statement_id' => 'Finalized statement lines cannot be deleted.',
                ]);
            }
        });
    }

    public function statement(): BelongsTo
    {
        return $this->belongsTo(OwnerStatement::class, 'owner_statement_id');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
