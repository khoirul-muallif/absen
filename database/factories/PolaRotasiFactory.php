<?php

namespace Database\Factories;

use App\Models\Instansi;
use Illuminate\Database\Eloquent\Factories\Factory;

class PolaRotasiFactory extends Factory
{
    public function definition(): array
    {
        return [
            'instansi_id'    => Instansi::factory(),
            'unit_kerja'     => 'IGD',
            'nama_pola'      => 'Rotasi 2 Shift',
            'langkah'        => [
                ['shift_id' => null, 'libur' => false],
                ['shift_id' => null, 'libur' => true],
            ],
            'berlaku_saat_libur_nasional' => true,
            'is_active'      => true,
        ];
    }
}
