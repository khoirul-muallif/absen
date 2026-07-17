<?php

namespace Database\Factories;

use App\Models\JenisCuti;
use App\Models\Karyawan;
use Illuminate\Database\Eloquent\Factories\Factory;

class KuotaCutiFactory extends Factory
{
    public function definition(): array
    {
        return [
            'karyawan_id' => Karyawan::factory(),
            'jenis_cuti_id' => JenisCuti::factory(),
            'tahun' => now()->year,
            'kuota' => 12,
            'terpakai' => 0,
        ];
    }
}
