<?php

namespace Database\Factories;

use App\Models\JenisCuti;
use App\Models\Karyawan;
use Illuminate\Database\Eloquent\Factories\Factory;

class CutiFactory extends Factory
{
    public function definition(): array
    {
        return [
            'karyawan_id' => Karyawan::factory(),
            'jenis_cuti_id' => JenisCuti::factory(),
            'tanggal_mulai' => now()->addWeek()->toDateString(),
            'tanggal_selesai' => now()->addWeek()->toDateString(),
            'jumlah_hari' => 1,
            'alasan' => $this->faker->sentence(),
            'lampiran' => null,
            'status' => 'pending',
        ];
    }
}
