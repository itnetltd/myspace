<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderInvitation extends Model
{
    public const STATUS_INVITED = 'invited';

    public const STATUS_VIEWED = 'viewed';

    public const STATUS_QUOTED = 'quoted';

    public const STATUS_DECLINED = 'declined';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'service_request_id', 'provider_company_id', 'status', 'invited_at',
        'viewed_at', 'responded_at', 'expires_at', 'invited_by',
    ];

    protected $casts = [
        'invited_at' => 'datetime', 'viewed_at' => 'datetime',
        'responded_at' => 'datetime', 'expires_at' => 'datetime',
    ];

    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    public function providerCompany(): BelongsTo
    {
        return $this->belongsTo(ProviderCompany::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }
}
