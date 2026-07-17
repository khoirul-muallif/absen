<?php

namespace Database\Factories;

use App\Models\Karyawan;
use App\Models\Shift;
use Illuminate\Database\Eloquent\Factories\Factory;

class KaryawanShiftFactory extends Factory
{
    public function definition(): array
    {
        return [
            'karyawan_id'       => Karyawan::factory(),
            'shift_id'          => Shift::factory(),
            'tanggal_berlaku'   => now()->startOfMonth()->toDateString(),
            'tanggal_berakhir'  => now()->endOfMonth()->toDateString(),
        ];
    }

    public function openEnded(): static
    {
        return $this->state(fn () => ['tanggal_berakhir' => null]);
    }
}
