<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'rental_id', 'order_id', 'transaction_id', 'type', 'method', 'payment_type',
        'gross_amount', 'payment_status', 'snap_token', 'paid_at',
        'refunded_amount', 'received_by', 'raw_response',
        'proof_photo', 'proof_uploaded_at', 'rejection_reason', 'rejection_count',
    ];

    protected function casts(): array
    {
        return [
            'gross_amount' => 'decimal:2',
            'refunded_amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'proof_uploaded_at' => 'datetime',
            'raw_response' => 'array',
        ];
    }

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
