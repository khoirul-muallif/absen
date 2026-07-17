<?php

namespace Database\Factories;

use App\Models\Karyawan;
use App\Models\Shift;
use Illuminate\Database\Eloquent\Factories\Factory;

class JadwalFactory extends Factory
{
    public function definition(): array
    {
        return [
            'karyawan_id' => Karyawan::factory(),
            'shift_id'    => Shift::factory(),
            'tanggal'     => now()->addDay()->toDateString(),
            'jenis'       => 'reguler',
            'keterangan'  => null,
        ];
    }

    public function libur(): static
    {
        return $this->state(fn () => ['shift_id' => null, 'jenis' => 'libur']);
    }
}
