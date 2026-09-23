<?php

namespace Database\Seeders;

use App\Models\Bike;
use Illuminate\Database\Seeder;

class BikeSeeder extends Seeder
{
    public function run(): void
    {
        $bikes = [
            ['name' => 'Beat 110', 'brand' => 'Honda', 'license_plate' => 'L 1101 AB', 'category' => 'matic', 'cc' => 110, 'year' => 2022, 'color' => 'Hitam', 'daily_rate' => 75000, 'hourly_rate' => 9000, 'facilities' => ['helm', 'stnk', 'kunci_ganda']],
            ['name' => 'Scoopy 110', 'brand' => 'Honda', 'license_plate' => 'L 1102 AB', 'category' => 'matic', 'cc' => 110, 'year' => 2023, 'color' => 'Krem', 'daily_rate' => 95000, 'hourly_rate' => 10000, 'facilities' => ['helm', 'stnk', 'jas_hujan']],
            ['name' => 'Vario 160', 'brand' => 'Honda', 'license_plate' => 'L 1601 AB', 'category' => 'matic', 'cc' => 160, 'year' => 2023, 'color' => 'Merah', 'daily_rate' => 125000, 'hourly_rate' => 15000, 'facilities' => ['helm', 'stnk', 'phone_holder', 'charger']],
            ['name' => 'NMAX 155', 'brand' => 'Yamaha', 'license_plate' => 'L 1551 AB', 'category' => 'matic', 'cc' => 155, 'year' => 2022, 'color' => 'Biru navy', 'daily_rate' => 175000, 'hourly_rate' => 20000, 'facilities' => ['helm', 'stnk', 'phone_holder', 'charger']],
            ['name' => 'Supra X 125', 'brand' => 'Honda', 'license_plate' => 'L 1251 AB', 'category' => 'manual', 'cc' => 125, 'year' => 2021, 'color' => 'Hitam merah', 'daily_rate' => 90000, 'hourly_rate' => 10000, 'facilities' => ['helm', 'stnk', 'jas_hujan']],
            ['name' => 'R15', 'brand' => 'Yamaha', 'license_plate' => 'L 1501 AB', 'category' => 'sport', 'cc' => 155, 'year' => 2022, 'color' => 'Biru', 'daily_rate' => 225000, 'hourly_rate' => 30000, 'facilities' => ['helm', 'stnk', 'phone_holder']],
        ];

        foreach ($bikes as $bike) {
            Bike::updateOrCreate(['license_plate' => $bike['license_plate']], $bike + ['status' => 'available']);
        }
    }
}
