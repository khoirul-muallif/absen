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

        // Tim bershift TETAP (bukan muter pagi->siang->malam per orang).
        // Kenapa: pola lama (1 tim muter 3 jenis shift lewat offset
        // tanggal_mulai antar grup) TERBUKTI tidak bisa menjamin shift malam
        // selalu terisi -- malam cuma 1 dari 7 posisi siklus, jadi dengan
        // offset berapapun selama grupnya < 7, selalu ada hari di mana tidak
        // ada satupun grup yang jatuh di posisi malam. Lihat insiden 7 Okt
        // 2026 (2 shift kosong) di todo.md / status report.
        //
        // Desain baru: tiap tim shift-nya tetap, cuma kerja/libur yang
        // diputar 5-2. Coverage harian dijamin bukan dari struktur pola ini,
        // tapi dari OFFSET INDIVIDUAL tiap karyawan dalam tim (lihat
        // SimulasiMassalSeeder) -- selama anggota tim offsetnya menyebar ke
        // beberapa posisi siklus berbeda, tidak mungkin semua anggota libur
        // bareng di hari yang sama.
        $timShift = [
            'Pagi' => $shift['pagi'],
            'Siang' => $shift['siang'],
            'Malam' => $shift['malam'],
        ];

        $unitRotasi = [
            ['unit_kerja' => 'Rawat Inap', 'berlaku_saat_libur_nasional' => false],
            ['unit_kerja' => 'IGD', 'berlaku_saat_libur_nasional' => true],
            ['unit_kerja' => 'ICU', 'berlaku_saat_libur_nasional' => true],
        ];

        foreach ($unitRotasi as $unit) {
            foreach ($timShift as $namaTim => $shiftTim) {
                $langkah = [
                    ...array_fill(0, 5, ['shift_id' => $shiftTim->id, 'libur' => false]),
                    ...array_fill(0, 2, ['shift_id' => null, 'libur' => true]),
                ];

                PolaRotasi::updateOrCreate(
                    [
                        'instansi_id' => $instansi->id,
                        'unit_kerja' => $unit['unit_kerja'],
                        'nama_pola' => "Tim {$namaTim} - 5 Kerja 2 Libur",
                    ],
                    [
                        'langkah' => $langkah,
                        'berlaku_saat_libur_nasional' => $unit['berlaku_saat_libur_nasional'],
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
