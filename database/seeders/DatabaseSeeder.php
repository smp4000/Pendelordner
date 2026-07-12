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
            ['email' => 'admin@admin.com'],
            [
                'name' => 'Administrator',
                'password' => Hash::make('password'),
            ]
        );

        $this->call([
            UserSeeder::class,
            MasterDataSeeder::class,
            SupplierSeeder::class,
            BankPresetSeeder::class,
            LedgerAccountSeeder::class,
            WashSeeder::class,
        ]);
    }
}
