<?php

namespace Database\Factories;

use App\Models\Karyawan;
use App\Models\Shift;
use Illuminate\Database\Eloquent\Factories\Factory;

class AbsensiFactory extends Factory
{
    public function definition(): array
    {
        return [
            'karyawan_id' => Karyawan::factory(),
            'shift_id' => Shift::factory(),
            'qr_instansi_id' => null,
            'tanggal' => now()->toDateString(),
            'waktu_masuk' => null,
            'waktu_pulang' => null,
            'latitude_masuk' => null,
            'longitude_masuk' => null,
            'foto_masuk' => null,
            'latitude_pulang' => null,
            'longitude_pulang' => null,
            'foto_pulang' => null,
            'status' => 'alpha',
            'keterangan' => null,
            'menit_terlambat' => 0,
        ];
    }

    public function sudahMasuk(): static
    {
        return $this->state(fn () => [
            'waktu_masuk' => now(),
            'status' => 'tepat_waktu',
        ]);
    }
}
