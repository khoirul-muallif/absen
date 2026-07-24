<?php

namespace Database\Factories;

use App\Models\Karyawan;
use Illuminate\Database\Eloquent\Factories\Factory;

class LemburFactory extends Factory
{
    public function definition(): array
    {
        return [
            'karyawan_id' => Karyawan::factory(),
            'tanggal'     => now()->addDays($this->faker->numberBetween(1, 30))->toDateString(),
            'jam_mulai'   => '17:00',
            'jam_selesai' => '20:00',
            'alasan'      => $this->faker->sentence(),
            'status'      => 'pending',
            'approved_by' => null,
            'approved_at' => null,
            'catatan_approval' => null,
        ];
    }
}
