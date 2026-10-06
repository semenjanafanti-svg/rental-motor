<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Bike extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'brand', 'license_plate', 'category', 'cc', 'year', 'color',
        'daily_rate', 'hourly_rate', 'status', 'photo', 'facilities',
    ];

    protected function casts(): array
    {
        return [
            'daily_rate' => 'decimal:2',
            'hourly_rate' => 'decimal:2',
            'facilities' => 'array',
        ];
    }

    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class);
    }

    /**
     * A photo path can remain in the database after its file is deleted.
     * Only render an image when its public-disk file is still available.
     */
    public function hasPhoto(): bool
    {
        return filled($this->photo) && Storage::disk('public')->exists($this->photo);
    }

    public function photoUrl(): ?string
    {
        return $this->hasPhoto() ? Storage::disk('public')->url($this->photo) : null;
    }
}
