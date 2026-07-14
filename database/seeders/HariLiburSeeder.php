<?php

namespace Database\Seeders;

use App\Models\HariLibur;
use App\Models\Instansi;
use Illuminate\Database\Seeder;

class HariLiburSeeder extends Seeder
{
    public function run(): void
    {
        $instansi = Instansi::firstOrFail();

        HariLibur::insert([
            [
                'instansi_id' => $instansi->id,
                'tanggal' => '2026-07-22',
                'nama' => 'Libur Nasional (contoh)',
                'keterangan' => 'Data dummy untuk testing',
                'is_cuti_bersama' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'instansi_id' => $instansi->id,
                'tanggal' => '2026-07-30',
                'nama' => 'Cuti Bersama (contoh)',
                'keterangan' => 'Data dummy untuk testing',
                'is_cuti_bersama' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
