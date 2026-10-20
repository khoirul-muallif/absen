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

        // Rotasi individu (bukan tim tetap per shift seperti fase 56).
        // Alasan: tim tetap valid secara matematis (coverage terjamin)
        // tapi tidak mencerminkan praktik lazim RS -- yang umum adalah
        // tiap perawat gentian pagi/siang/malam, BUKAN dikunci permanen
        // di satu shift. Lihat diskusi 20 Okt 2026.
        //
        // Siklus 8 hari: 2 Pagi, 2 Siang, 2 Malam, 2 Libur. Urutan ini
        // SENGAJA menaruh Libur tepat setelah Malam (bukan Pagi) --
        // supaya tidak ada yang masuk pagi langsung sehabis dua hari
        // shift malam (aturan umum di RS: ada jeda istirahat wajib
        // setelah shift malam sebelum masuk shift lain).
        //
        // Coverage harian dijamin dari OFFSET INDIVIDUAL tiap karyawan
        // dalam unit (lihat SimulasiMassalSeeder), BUKAN dari banyaknya
        // pola per unit seperti desain tim-tetap sebelumnya -- jadi
        // SATU PolaRotasi per unit sudah cukup. Karyawan dengan
        // tanggal_mulai berbeda otomatis jatuh di posisi siklus berbeda
        // pada hari yang sama, sehingga tiap shift tetap ada yang isi.
        $langkah = [
            ...array_fill(0, 2, ['shift_id' => $shift['pagi']->id, 'libur' => false]),
            ...array_fill(0, 2, ['shift_id' => $shift['siang']->id, 'libur' => false]),
            ...array_fill(0, 2, ['shift_id' => $shift['malam']->id, 'libur' => false]),
            ...array_fill(0, 2, ['shift_id' => null, 'libur' => true]),
        ];

        $unitRotasi = [
            ['unit_kerja' => 'Rawat Inap', 'berlaku_saat_libur_nasional' => false],
            ['unit_kerja' => 'IGD', 'berlaku_saat_libur_nasional' => true],
            ['unit_kerja' => 'ICU', 'berlaku_saat_libur_nasional' => true],
        ];

        foreach ($unitRotasi as $unit) {
            PolaRotasi::updateOrCreate(
                [
                    'instansi_id' => $instansi->id,
                    'unit_kerja' => $unit['unit_kerja'],
                    'nama_pola' => 'Rotasi 2 Pagi - 2 Siang - 2 Malam - 2 Libur',
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
