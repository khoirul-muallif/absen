<?php

namespace Database\Factories;

use App\Models\Karyawan;
use App\Models\PolaRotasi;
use Illuminate\Database\Eloquent\Factories\Factory;

class KaryawanPolaRotasiFactory extends Factory
{
    public function definition(): array
    {
        return [
            'karyawan_id'     => Karyawan::factory()->rotasi(),
            'pola_rotasi_id'  => PolaRotasi::factory(),
            'tanggal_mulai'   => now()->startOfMonth(),
            'tanggal_berakhir' => null,
        ];
    }
}
