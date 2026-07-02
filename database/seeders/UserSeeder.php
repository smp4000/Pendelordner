<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Panel-Benutzer. Idempotent (firstOrCreate über die E-Mail). Einzeln
 * aufrufbar: php artisan db:seed --class=UserSeeder
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'sv.welle@aral-welle.de'],
            [
                'name' => 'SV Welle',
                'password' => Hash::make('123456Bv'),
            ]
        );
    }
}
