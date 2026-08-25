<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class WorkOrderActivity extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'work_order_id', 'activity_type', 'actor_user_id', 'provider_company_id',
        'description', 'metadata', 'occurred_at',
    ];

    protected $casts = ['metadata' => 'array', 'occurred_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Work-order activities are append-only.'));
        static::deleting(fn () => throw new LogicException('Work-order activities are append-only.'));
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
