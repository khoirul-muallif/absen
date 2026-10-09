<?php

namespace Database\Seeders;

use App\Models\Absensi;
use App\Models\Karyawan;
use App\Models\QrInstansi;
use App\Models\Shift;
use Illuminate\Database\Seeder;

class AbsensiSimulasiSeeder extends Seeder
{
    public function run(): void
    {
        $karyawan = Karyawan::where('email', 'budi@rsb.com')->firstOrFail();
        $shift = Shift::where('instansi_id', $karyawan->instansi_id)
            ->where('nama_shift', 'umum')
            ->firstOrFail();

        // Di-scope ke instansi karyawan — sebelumnya QrInstansi::first() bisa
        // narik QR milik instansi lain kalau suatu saat ada >1 instansi.
        // Nullable kalau belum ada QR sama sekali untuk instansi ini.
        $qr = QrInstansi::where('instansi_id', $karyawan->instansi_id)->first();

        // Pastikan shift dalam mode akumulasi buat simulasi ini
        $shift->update([
            'mode_toleransi' => 'akumulasi_bulanan',
            'toleransi_menit' => 30,
        ]);
        $shift->refresh();

        // Simulasi 5 hari ke belakang, telat dikit-dikit
        $simulasiTelat = [
            5 => 2,   // 5 hari lalu, telat 2 menit
            4 => 5,   // 4 hari lalu, telat 5 menit
            3 => 8,   // 3 hari lalu, telat 8 menit
            2 => 10,  // 2 hari lalu, telat 10 menit
            1 => 3,   // kemarin, telat 3 menit
        ];

        $bulanAktif = null;
        $akumulasi  = 0;

        foreach ($simulasiTelat as $hariLalu => $menitTelat) {
            $tanggal = today()->subDays($hariLalu);

            // Reset akumulasi tiap ganti bulan. Dicek DI LUAR skip hari libur
            // supaya pelacakan bulan tetap akurat lintas hari libur — tapi
            // TIDAK ikut menambah $akumulasi untuk hari yang di-skip (lihat
            // guard adalahHariKerja() di bawah, sekarang dicek PALING AWAL).
            if ($bulanAktif !== $tanggal->format('Y-m')) {
                $bulanAktif = $tanggal->format('Y-m');
                $akumulasi  = 0;
            }

            // Guard ini WAJIB di awal — sebelumnya di bawah perhitungan
            // akumulasi, jadi menit telat hari libur sempat ikut nambah
            // $akumulasi walau row Absensi-nya sendiri tidak pernah dibuat.
            // Angka akumulasi yang ditampilkan jadi lebih besar dari yang
            // sebenarnya akan dihasilkan AbsensiController::masuk() (yang
            // menjumlah dari DB, bukan dari variabel lokal ini).
            if (! $shift->adalahHariKerja($tanggal)) {
                $this->command->warn("Lewati {$tanggal->toDateString()}: bukan hari kerja shift.");
                continue;
            }

            $waktuMasuk = $tanggal->copy()
                ->setTimeFromTimeString($shift->jamMasukString())
                ->addMinutes($menitTelat);
            $akumulasi += $menitTelat;
            $status = $shift->tentukanStatus($waktuMasuk);
            $melebihi = $shift->sudahMelebihiToleransiBulanan($akumulasi);

            Absensi::updateOrCreate(
                ['karyawan_id' => $karyawan->id, 'tanggal' => $tanggal->toDateString()],
                [
                    'shift_id' => $shift->id,
                    'qr_instansi_id' => $qr?->id,
                    'waktu_masuk' => $waktuMasuk,
                    'menit_terlambat' => $menitTelat,
                    'melebihi_toleransi_bulanan' => $melebihi,
                    'status' => $status,
                    'latitude_masuk' => -7.0784947,
                    'longitude_masuk' => 110.4119292,
                ]
            );

            $this->command->info("Hari -{$hariLalu} ({$tanggal->toDateString()}): telat {$menitTelat} menit, akumulasi {$akumulasi}, status: {$status}, melebihi KPI: " . ($melebihi ? 'ya' : 'belum'));
        }

        $this->command->info("Total akumulasi sebelum hari ini: {$akumulasi} menit dari kuota {$shift->toleransi_menit} menit.");
        $this->command->info("Sisa kuota sebelum tembus: " . max(0, $shift->toleransi_menit - $akumulasi) . " menit.");
    }
}
