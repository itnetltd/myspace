<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class WorkOrderEvidence extends Model
{
    public const TYPES = [
        'before_photo', 'after_photo', 'inspection_report', 'delivery_receipt',
        'delivery_photo', 'serial_number', 'model_received', 'other',
    ];

    protected $table = 'work_order_evidence';

    protected $fillable = [
        'work_order_id', 'completion_submission_id', 'evidence_type', 'file_path',
        'text_value', 'metadata', 'uploaded_by',
    ];

    protected $casts = ['metadata' => 'array'];

    protected static function booted(): void
    {
        static::saving(function (self $evidence) {
            if (! in_array($evidence->evidence_type, self::TYPES, true)
                || (blank($evidence->file_path) && blank($evidence->text_value))) {
                throw ValidationException::withMessages(['evidence' => 'Evidence needs a supported type and a private file or text value.']);
            }
        });
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function completionSubmission(): BelongsTo
    {
        return $this->belongsTo(WorkOrderCompletionSubmission::class, 'completion_submission_id');
    }
}
