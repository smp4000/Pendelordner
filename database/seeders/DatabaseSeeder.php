<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Admin-Benutzer für das Filament-Panel (lokaler Betrieb).
        User::firstOrCreate(
            ['email' => 'admin@pendelordner.local'],
            [
                'name' => 'Administrator',
                'password' => Hash::make('password'),
            ]
        );

        $this->call([
            StammdatenSeeder::class,
            LieferantenSeeder::class,
        ]);
    }
}
