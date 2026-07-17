<?php

namespace Database\Factories;

use App\Models\Karyawan;
use Illuminate\Database\Eloquent\Factories\Factory;

class DinasFactory extends Factory
{
    public function definition(): array
    {
        return [
            'karyawan_id' => Karyawan::factory(),
            'tanggal_mulai' => now()->addWeek()->toDateString(),
            'tanggal_selesai' => now()->addWeek()->toDateString(),
            'tujuan' => $this->faker->city(),
            'keperluan' => $this->faker->sentence(),
            'status' => 'pending',
        ];
    }
}
