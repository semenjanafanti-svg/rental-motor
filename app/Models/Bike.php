<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bike extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'brand', 'license_plate', 'category', 'cc', 'year',
        'daily_rate', 'hourly_rate', 'status', 'photo',
    ];

    protected function casts(): array
    {
        return [
            'daily_rate' => 'decimal:2',
            'hourly_rate' => 'decimal:2',
        ];
    }

    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class);
    }
}
