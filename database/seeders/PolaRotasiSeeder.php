<?php

namespace Database\Seeders;

use App\Models\Instansi;
use App\Models\PolaRotasi;
use App\Models\Shift;
use Illuminate\Database\Seeder;

class PolaRotasiSeeder extends Seeder
{
    public function run(): void
    {
        $instansi = Instansi::firstOrFail();
        $shift = Shift::whereIn('nama_shift', ['pagi', 'siang', 'malam'])
            ->get()
            ->keyBy('nama_shift');

        // Pola 5 hari kerja + 2 hari libur, unit non-24 jam (Rawat Inap biasa).
        // Berbeda dari rotasi Dedi/Siti/Rina (nonstop 15 hari tanpa satu pun
        // langkah libur) — jalur "karyawan rotasi sedang libur" belum pernah
        // dilewati sama sekali sebelum pola ini ada (lihat todo.md).
        //
        // berlaku_saat_libur_nasional = false (sengaja beda dari IGD/ICU):
        // di hari libur nasional, generator MEMAKSA libur meski posisi
        // siklusnya seharusnya kerja. Ini jalur override yang juga belum
        // pernah ada data ujinya — yang sudah ada baru jalur IGD (true).
        PolaRotasi::create([
            'instansi_id' => $instansi->id,
            'unit_kerja'  => 'Rawat Inap',
            'nama_pola'   => 'Rotasi 5 Kerja 2 Libur',
            'langkah'     => [
                ['shift_id' => $shift['pagi']->id, 'libur' => false],
                ['shift_id' => $shift['siang']->id, 'libur' => false],
                ['shift_id' => $shift['malam']->id, 'libur' => false],
                ['shift_id' => $shift['pagi']->id, 'libur' => false],
                ['shift_id' => $shift['siang']->id, 'libur' => false],
                ['shift_id' => null, 'libur' => true],
                ['shift_id' => null, 'libur' => true],
            ],
            'berlaku_saat_libur_nasional' => false,
            'is_active' => true,
        ]);
    }
}
