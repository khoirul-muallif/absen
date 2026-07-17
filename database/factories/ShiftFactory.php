<?php

namespace Database\Factories;

use App\Models\Instansi;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShiftFactory extends Factory
{
    public function definition(): array
    {
        return [
            'instansi_id'     => Instansi::factory(),
            'nama_shift'      => 'umum',
            'jam_masuk'       => '07:30:00',
            'jam_pulang'      => '16:00:00',
            'toleransi_menit' => 15,
            'mode_toleransi'  => 'harian',
            'hari_kerja'      => [1, 2, 3, 4, 5], // Senin-Jumat
            'is_active'       => true,
        ];
    }

    public function akumulasiBulanan(): static
    {
        return $this->state(fn () => ['mode_toleransi' => 'akumulasi_bulanan']);
    }

    public function tanpaHariKerjaTetap(): static
    {
        // hari_kerja kosong = dianggap kerja tiap hari (dipakai shift rotasi pagi/siang/malam)
        return $this->state(fn () => ['hari_kerja' => []]);
    }
}
