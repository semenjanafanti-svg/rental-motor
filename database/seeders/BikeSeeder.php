<?php

namespace Database\Seeders;

use App\Models\Bike;
use Illuminate\Database\Seeder;

class BikeSeeder extends Seeder
{
    public function run(): void
    {
        $bikes = [
            ['name' => 'Beat 110', 'brand' => 'Honda', 'license_plate' => 'L 1101 AB', 'category' => 'matic', 'cc' => 110, 'year' => 2022, 'color' => 'Hitam', 'daily_rate' => 75000, 'photo' => 'bikes/01M4AD99AP1ZBAZHP0XNX30NJC.jpeg'],
            ['name' => 'Scoopy 110', 'brand' => 'Honda', 'license_plate' => 'L 1102 AB', 'category' => 'matic', 'cc' => 110, 'year' => 2023, 'color' => 'Krem', 'daily_rate' => 95000, 'photo' => 'bikes/01M48CPG5B4T01J5B97EE5VJS2.jpg'],
            ['name' => 'Vario 160', 'brand' => 'Honda', 'license_plate' => 'L 1601 AB', 'category' => 'matic', 'cc' => 160, 'year' => 2023, 'color' => 'Merah', 'daily_rate' => 125000, 'photo' => 'bikes/01M4ADF14TR5XB4RJWM2HEG5BC.jpg'],
            ['name' => 'NMAX 155', 'brand' => 'Yamaha', 'license_plate' => 'L 1551 AB', 'category' => 'matic', 'cc' => 155, 'year' => 2022, 'color' => 'Biru navy', 'daily_rate' => 175000, 'photo' => 'bikes/01M4ADCCJ6ACEH6CG5N6FQKQJJ.jpg'],
            ['name' => 'Supra X 125', 'brand' => 'Honda', 'license_plate' => 'L 1251 AB', 'category' => 'manual', 'cc' => 125, 'year' => 2021, 'color' => 'Hitam merah', 'daily_rate' => 90000, 'photo' => 'bikes/01M48C66AB0KRM0R20KMY5DA9K.jpg'],
            ['name' => 'R15', 'brand' => 'Yamaha', 'license_plate' => 'L 1501 AB', 'category' => 'sport', 'cc' => 155, 'year' => 2022, 'color' => 'Biru', 'daily_rate' => 225000, 'photo' => 'bikes/01M4ADHDHC158DYPGKTJ44CH9H.webp'],
        ];

        foreach ($bikes as $bike) {
            Bike::updateOrCreate(['license_plate' => $bike['license_plate']], $bike + ['status' => 'available']);
        }
    }
}
