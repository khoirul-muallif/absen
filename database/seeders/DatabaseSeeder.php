<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            InstansiSeeder::class,
            ShiftSeeder::class,
            JenisCutiSeeder::class,
            PolaRotasiSeeder::class,
            KaryawanSeeder::class,
            HariLiburSeeder::class,
        ]);
    }
}
