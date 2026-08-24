<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceTicket extends Model
{
    use BelongsToAccount;

    // Optional: standard statuses & priorities (helps consistency in UI)
    public const STATUS_OPEN = 'open';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_CLOSED = 'closed';

    public const PRIORITY_LOW = 'low';

    public const PRIORITY_MEDIUM = 'medium';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_URGENT = 'urgent';

    protected $fillable = [
        'account_id',
        'unit_id',
        'lease_id',
        'ticket_no',
        'title',
        'category',
        'priority',
        'status',
        'description',
        'reported_by',
        'reported_on',
        'resolved_on',
        'estimated_cost',
        'actual_cost',
        'photo_path',
        'internal_notes',
    ];

    protected $casts = [
        'reported_on' => 'date',
        'resolved_on' => 'date',
        'estimated_cost' => 'decimal:2',
        'actual_cost' => 'decimal:2',
    ];

    protected function accountParentMap(): array
    {
        return [
            'unit_id' => Unit::class,
            'lease_id' => Lease::class,
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    // Optional: quick helpers for filtering
    public function scopeOpen($query)
    {
        return $query->whereIn('status', [self::STATUS_OPEN, self::STATUS_IN_PROGRESS]);
    }

    public function scopeResolved($query)
    {
        return $query->whereIn('status', [self::STATUS_RESOLVED, self::STATUS_CLOSED]);
    }

    protected static function booted(): void
    {
        static::creating(function (MaintenanceTicket $ticket) {
            // Default reported_on
            if (empty($ticket->reported_on)) {
                $ticket->reported_on = now()->toDateString();
            }

            // Auto-generate ticket number (safer than max(id)+1)
            // Format: MT-YYYY-000001 (sequence increases)
            if (empty($ticket->ticket_no)) {
                $year = now()->format('Y');

                // Get last ticket_no for this year, then increment.
                $last = static::query()
                    ->where('ticket_no', 'like', "MT-{$year}-%")
                    ->orderByDesc('id')
                    ->value('ticket_no');

                $nextNumber = 1;
                if ($last) {
                    // last example: MT-2026-000123
                    $parts = explode('-', $last);
                    $lastSeq = (int) end($parts);
                    $nextNumber = $lastSeq + 1;
                }

                $ticket->ticket_no = 'MT-'.$year.'-'.str_pad((string) $nextNumber, 6, '0', STR_PAD_LEFT);
            }

            // Default status/priority if not set (doesn't override your form)
            if (empty($ticket->status)) {
                $ticket->status = self::STATUS_OPEN;
            }
            if (empty($ticket->priority)) {
                $ticket->priority = self::PRIORITY_MEDIUM;
            }
        });
    }
}
