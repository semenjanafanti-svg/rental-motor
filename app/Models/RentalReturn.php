<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalReturn extends Model
{
    protected $table = 'rental_returns';

    protected $fillable = [
        'rental_id', 'actual_return_time', 'late_hours',
        'late_fee', 'damage_fee', 'condition_notes', 'checked_by',
    ];

    protected function casts(): array
    {
        return [
            'actual_return_time' => 'datetime',
            'late_fee' => 'decimal:2',
            'damage_fee' => 'decimal:2',
        ];
    }

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }

    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }
}
