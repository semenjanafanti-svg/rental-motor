<?php

namespace Database\Seeders;

use App\Models\Bike;
use Illuminate\Database\Seeder;

class BikeSeeder extends Seeder
{
    public function run(): void
    {
        $bikes = [
            ['name' => 'Beat 110',      'brand' => 'Honda',  'license_plate' => 'L 1101 AB', 'category' => 'matic',  'cc' => 110, 'year' => 2022, 'daily_rate' => 75000,  'hourly_rate' => 9000],
            ['name' => 'Scoopy 110',    'brand' => 'Honda',  'license_plate' => 'L 1102 AB', 'category' => 'matic',  'cc' => 110, 'year' => 2023, 'daily_rate' => 85000,  'hourly_rate' => 10000],
            ['name' => 'Vario 160',     'brand' => 'Honda',  'license_plate' => 'L 1601 AB', 'category' => 'matic',  'cc' => 160, 'year' => 2023, 'daily_rate' => 100000, 'hourly_rate' => 12000],
            ['name' => 'NMAX 155',      'brand' => 'Yamaha', 'license_plate' => 'L 1551 AB', 'category' => 'matic',  'cc' => 155, 'year' => 2022, 'daily_rate' => 150000, 'hourly_rate' => 18000],
            ['name' => 'Supra X 125',   'brand' => 'Honda',  'license_plate' => 'L 1251 AB', 'category' => 'manual', 'cc' => 125, 'year' => 2021, 'daily_rate' => 80000,  'hourly_rate' => 10000],
            ['name' => 'R15',           'brand' => 'Yamaha', 'license_plate' => 'L 1501 AB', 'category' => 'sport',  'cc' => 155, 'year' => 2022, 'daily_rate' => 250000, 'hourly_rate' => 30000],
        ];

        foreach ($bikes as $bike) {
            Bike::updateOrCreate(['license_plate' => $bike['license_plate']], $bike + ['status' => 'available']);
        }
    }
}
