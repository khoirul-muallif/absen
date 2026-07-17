<?php

namespace Database\Factories;

use App\Models\Karyawan;
use Illuminate\Database\Eloquent\Factories\Factory;

class IzinFactory extends Factory
{
    public function definition(): array
    {
        return [
            'karyawan_id' => Karyawan::factory(),
            'tanggal' => now()->addDay()->toDateString(),
            'jam_keluar' => '10:00:00',
            'jam_kembali' => '12:00:00',
            'keperluan' => $this->faker->sentence(),
            'status' => 'pending',
        ];
    }
}
