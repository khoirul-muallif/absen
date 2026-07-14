<?php

namespace Database\Seeders;

use App\Models\JenisCuti;
use Illuminate\Database\Seeder;

class JenisCutiSeeder extends Seeder
{
    public function run(): void
    {
        JenisCuti::insert([
            [
                'nama' => 'Cuti Tahunan',
                'is_tahunan' => true,
                'default_kuota' => 12,
                'perlu_lampiran' => false,
                'potong_kuota' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nama' => 'Cuti Sakit',
                'is_tahunan' => false,
                'default_kuota' => 0,
                'perlu_lampiran' => true,
                'potong_kuota' => false,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nama' => 'Cuti Melahirkan',
                'is_tahunan' => false,
                'default_kuota' => 90,
                'perlu_lampiran' => true,
                'potong_kuota' => false,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nama' => 'Cuti Menikah',
                'is_tahunan' => false,
                'default_kuota' => 3,
                'perlu_lampiran' => false,
                'potong_kuota' => false,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
