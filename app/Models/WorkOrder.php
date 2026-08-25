<?php

namespace App\Models;

use App\Models\Concerns\BelongsToProviderCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkOrder extends Model
{
    use BelongsToProviderCompany;

    public const STATUS_PENDING = 'pending';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETION_SUBMITTED = 'completion_submitted';

    public const STATUS_REVISION_REQUESTED = 'revision_requested';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'service_request_id', 'quotation_id', 'provider_company_id', 'work_order_number',
        'status', 'scheduled_start', 'scheduled_completion', 'started_at', 'completed_at',
        'completion_review_required', 'accepted_completion_submission_id',
        'completion_notes', 'completion_evidence', 'created_by',
    ];

    protected $casts = [
        'scheduled_start' => 'datetime', 'scheduled_completion' => 'datetime',
        'started_at' => 'datetime', 'completed_at' => 'datetime', 'completion_evidence' => 'array',
        'completion_review_required' => 'boolean',
    ];

    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(WorkOrderAssignment::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(ServiceAppointment::class);
    }

    public function completionSubmissions(): HasMany
    {
        return $this->hasMany(WorkOrderCompletionSubmission::class);
    }

    public function acceptedCompletionSubmission(): BelongsTo
    {
        return $this->belongsTo(WorkOrderCompletionSubmission::class, 'accepted_completion_submission_id');
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(WorkOrderEvidence::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(SupplyDelivery::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(WorkOrderActivity::class)->orderBy('occurred_at')->orderBy('id');
    }
}
