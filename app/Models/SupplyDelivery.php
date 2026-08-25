<?php

namespace App\Models;

use App\Models\Concerns\BelongsToProviderCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplyDelivery extends Model
{
    use BelongsToProviderCompany;

    public const STATUS_PREPARING = 'preparing';

    public const STATUS_READY = 'ready';

    public const STATUS_DISPATCHED = 'dispatched';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'work_order_id', 'provider_company_id', 'status', 'scheduled_for',
        'dispatched_at', 'delivered_at', 'delivery_reference', 'recipient_name',
        'assigned_membership_id', 'notes',
    ];

    protected $casts = [
        'scheduled_for' => 'datetime', 'dispatched_at' => 'datetime', 'delivered_at' => 'datetime',
    ];

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function assignedMembership(): BelongsTo
    {
        return $this->belongsTo(ProviderCompanyMembership::class, 'assigned_membership_id');
    }
}
