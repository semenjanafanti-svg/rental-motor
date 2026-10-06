<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Rental extends Model
{
    protected $fillable = [
        'booking_code', 'user_id', 'bike_id',
        'start_time', 'end_time',
        'hourly_rate_applied',
        'total_price', 'dp_amount', 'balance_amount',
        'payment_status', 'status',
        'expires_at', 'resubmission_expires_at', 'picked_up_at', 'handed_over_by',
        'cancelled_reason', 'notes', 'ktp_photo', 'sim_photo', 'verification_status',
        'verification_rejection_reason', 'verification_rejection_count', 'verified_by', 'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'expires_at' => 'datetime',
            'resubmission_expires_at' => 'datetime',
            'picked_up_at' => 'datetime',
            'hourly_rate_applied' => 'decimal:2',
            'total_price' => 'decimal:2',
            'dp_amount' => 'decimal:2',
            'balance_amount' => 'decimal:2',
            'verified_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bike(): BelongsTo
    {
        // withTrashed: riwayat tetap utuh walau motor sudah di-soft-delete
        return $this->belongsTo(Bike::class)->withTrashed();
    }

    public function handedOverBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handed_over_by');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function getTotalHoursAttribute(): int
    {
        if (! $this->start_time || ! $this->end_time) {
            return 0;
        }

        return (int) ceil($this->start_time->diffInMinutes($this->end_time, true) / 60);
    }

    public function isPickupPeriodOpen(): bool
    {
        return $this->start_time !== null && now()->greaterThanOrEqualTo($this->start_time);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function rentalReturn(): HasOne
    {
        return $this->hasOne(RentalReturn::class);
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(RentalReminder::class);
    }

    /** Status "terlambat" tidak disimpan, cukup diturunkan. */
    public function isOverdue(): bool
    {
        return $this->status === 'active' && now()->greaterThan($this->end_time);
    }
}
