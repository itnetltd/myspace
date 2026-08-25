<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class WorkOrderCompletionSubmission extends Model
{
    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REVISION_REQUESTED = 'revision_requested';

    protected $fillable = [
        'work_order_id', 'submission_number', 'summary', 'provider_notes', 'status',
        'submitted_by', 'submitted_at', 'reviewed_by', 'reviewed_at', 'review_notes',
    ];

    protected $casts = ['submitted_at' => 'datetime', 'reviewed_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(function (self $submission) {
            if ($submission->getOriginal('status') !== self::STATUS_SUBMITTED) {
                throw ValidationException::withMessages(['submission' => 'Reviewed completion submissions are immutable.']);
            }

            $allowed = ['status', 'reviewed_by', 'reviewed_at', 'review_notes', 'updated_at'];
            if (array_diff(array_keys($submission->getDirty()), $allowed) !== []) {
                throw ValidationException::withMessages(['submission' => 'Submitted completion content is immutable.']);
            }
        });
        static::deleting(fn () => throw ValidationException::withMessages([
            'submission' => 'Completion submissions are immutable audit records.',
        ]));
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(WorkOrderEvidence::class, 'completion_submission_id');
    }
}
