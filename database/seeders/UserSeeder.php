<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['name' => 'Owner Rental',   'email' => 'owner@rental.test',    'phone_number' => '6281200000001', 'role' => 'super_admin'],
            ['name' => 'Admin Rental',   'email' => 'admin@rental.test',    'phone_number' => '6281200000002', 'role' => 'admin'],
            ['name' => 'Customer Demo',  'email' => 'customer@rental.test', 'phone_number' => '6281200000003', 'role' => 'customer'],
        ];

        foreach ($users as $data) {
            $user = User::firstOrNew(['email' => $data['email']]);

            // forceFill karena 'role' sengaja tidak masuk $fillable (mencegah mass assignment)
            $user->forceFill([
                'name' => $data['name'],
                'phone_number' => $data['phone_number'],
                'role' => $data['role'],
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ])->save();
        }
    }
}
