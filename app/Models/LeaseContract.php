<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaseContract extends Model
{
    use BelongsToAccount, HasFactory;

    protected $fillable = [
        'account_id',
        'lease_id',
        'contract_template_id',
        'language',
        'status',
        'rendered_html',
        'landlord_signature_path',
        'tenant_signature_path',
        'signed_on',
    ];

    protected $casts = [
        'signed_on' => 'date',
        // Optional but helpful (keeps it consistent):
        'status' => 'string',
        'language' => 'string',
        'rendered_html' => 'string',
    ];

    protected function accountParentMap(): array
    {
        return [
            'lease_id' => Lease::class,
            'contract_template_id' => ContractTemplate::class,
        ];
    }

    public function lease()
    {
        return $this->belongsTo(Lease::class);
    }

    // Keep this method (so nothing breaks)
    public function template()
    {
        return $this->belongsTo(ContractTemplate::class, 'contract_template_id');
    }

    // Add this alias for clearer Filament relationship usage (optional)
    public function contractTemplate()
    {
        return $this->template();
    }
}
