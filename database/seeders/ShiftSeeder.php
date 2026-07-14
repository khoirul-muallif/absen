<?php

namespace Database\Seeders;

use App\Models\Instansi;
use App\Models\Shift;
use Illuminate\Database\Seeder;

class ShiftSeeder extends Seeder
{
    public function run(): void
    {
        $instansi = Instansi::firstOrFail();

        Shift::create([
            'instansi_id' => $instansi->id,
            'nama_shift' => 'umum',
            'jam_masuk' => '07:30:00',
            'jam_pulang' => '16:00:00',
            'toleransi_menit' => 30,
            'mode_toleransi' => 'harian',
            'hari_kerja' => [1, 2, 3, 4, 5],
            'is_active' => true,
        ]);

        Shift::create([
            'instansi_id' => $instansi->id,
            'nama_shift' => 'pagi',
            'jam_masuk' => '07:00:00',
            'jam_pulang' => '12:00:00',
            'toleransi_menit' => 30,
            'mode_toleransi' => 'harian',
            'hari_kerja' => [],
            'is_active' => true,
        ]);
    }
}
