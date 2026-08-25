<?php

namespace App\Models;

use App\Models\Concerns\BelongsToProviderCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrder extends Model
{
    use BelongsToProviderCompany;

    public const STATUS_PENDING = 'pending';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'service_request_id', 'quotation_id', 'provider_company_id', 'work_order_number',
        'status', 'scheduled_start', 'scheduled_completion', 'started_at', 'completed_at',
        'completion_notes', 'completion_evidence', 'created_by',
    ];

    protected $casts = [
        'scheduled_start' => 'datetime', 'scheduled_completion' => 'datetime',
        'started_at' => 'datetime', 'completed_at' => 'datetime', 'completion_evidence' => 'array',
    ];

    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }
}
