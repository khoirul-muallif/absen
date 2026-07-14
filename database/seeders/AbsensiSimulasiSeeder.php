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
        $shift = Shift::where('nama_shift', 'umum')->firstOrFail();
        $qr = QrInstansi::first();

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

        $akumulasi = 0;

        foreach ($simulasiTelat as $hariLalu => $menitTelat) {
            $tanggal = today()->subDays($hariLalu);
            $waktuMasuk = $tanggal->copy()->setTimeFromTimeString($shift->jam_masuk)->addMinutes($menitTelat);

            $akumulasi += $menitTelat;
            $status = $shift->tentukanStatus($waktuMasuk); // selalu berdasar hari itu
            $melebihi = $shift->sudahMelebihiToleransiBulanan($akumulasi);

            Absensi::updateOrCreate(
                ['karyawan_id' => $karyawan->id, 'tanggal' => $tanggal->toDateString()],
                [
                    'shift_id' => $shift->id,
                    'qr_instansi_id' => $qr->id,
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
