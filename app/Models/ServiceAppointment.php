<?php

namespace App\Models;

use App\Models\Concerns\BelongsToProviderCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceAppointment extends Model
{
    use BelongsToProviderCompany;

    public const STATUS_PROPOSED = 'proposed';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_RESCHEDULE_REQUESTED = 'reschedule_requested';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'work_order_id', 'provider_company_id', 'scheduled_start', 'scheduled_end',
        'status', 'location_notes', 'access_instructions', 'proposed_by', 'proposed_at',
        'confirmed_by', 'confirmed_at', 'cancelled_by', 'cancelled_at',
        'cancellation_reason', 'reschedule_notes',
    ];

    protected $casts = [
        'scheduled_start' => 'datetime', 'scheduled_end' => 'datetime',
        'proposed_at' => 'datetime', 'confirmed_at' => 'datetime', 'cancelled_at' => 'datetime',
    ];

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }
}
