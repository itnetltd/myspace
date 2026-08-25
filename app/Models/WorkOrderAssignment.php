<?php

namespace App\Models;

use App\Models\Concerns\BelongsToProviderCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrderAssignment extends Model
{
    use BelongsToProviderCompany;

    public const TYPES = ['technician', 'inspector', 'delivery', 'coordinator'];

    public const STATUS_ASSIGNED = 'assigned';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_DECLINED = 'declined';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const ACTIVE_STATUSES = [self::STATUS_ASSIGNED, self::STATUS_ACCEPTED];

    protected $fillable = [
        'work_order_id', 'provider_company_id', 'provider_company_membership_id',
        'assignment_type', 'is_primary', 'status', 'assigned_by', 'assigned_at',
        'accepted_at', 'declined_at', 'notes',
    ];

    protected $casts = [
        'is_primary' => 'boolean', 'assigned_at' => 'datetime',
        'accepted_at' => 'datetime', 'declined_at' => 'datetime',
    ];

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(ProviderCompanyMembership::class, 'provider_company_membership_id');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
