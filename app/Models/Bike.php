<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Bike extends Model
{
    use SoftDeletes;

    public const IMAGE_DISK = 'catalog_images';

    public const STANDARD_FACILITIES = ['helm', 'stnk', 'jas_hujan', 'phone_holder'];

    protected $fillable = [
        'name', 'brand', 'license_plate', 'category', 'cc', 'year', 'color',
        'daily_rate', 'status', 'photo', 'facilities',
    ];

    protected function casts(): array
    {
        return [
            'daily_rate' => 'decimal:2',
            'hourly_rate' => 'decimal:2',
            'facilities' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Bike $bike): void {
            $bike->setAttribute('hourly_rate', self::calculateHourlyRate($bike->daily_rate));
            $bike->setAttribute('facilities', self::STANDARD_FACILITIES);
        });
    }

    /** Denda per jam = 20% tarif harian, dibulatkan ke Rp1.000 terdekat. */
    public static function calculateHourlyRate(float|int|string|null $dailyRate): int
    {
        return (int) (round(((float) $dailyRate * 0.2) / 1000) * 1000);
    }

    /** Keep legacy rows consistent with the derived rate even before they are edited. */
    protected function hourlyRate(): Attribute
    {
        return Attribute::get(fn () => self::calculateHourlyRate($this->daily_rate));
    }

    /** The rental package is fixed for every bike, including legacy database rows. */
    protected function facilities(): Attribute
    {
        return Attribute::get(fn () => self::STANDARD_FACILITIES);
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
        return filled($this->photo) && Storage::disk(self::IMAGE_DISK)->exists($this->photo);
    }

    public function photoUrl(): ?string
    {
        return $this->hasPhoto() ? Storage::disk(self::IMAGE_DISK)->url($this->photo) : null;
    }
}
