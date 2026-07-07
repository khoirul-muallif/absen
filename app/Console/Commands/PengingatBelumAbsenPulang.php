<?php

namespace App\Console\Commands;

use App\Models\Absensi;
use App\Models\KaryawanShift;
use App\Notifications\BelumAbsen;
use Illuminate\Console\Command;

class PengingatBelumAbsenPulang extends Command
{
    protected $signature   = 'absensi:pengingat-belum-pulang';
    protected $description = 'Kirim notifikasi ke karyawan yang sudah masuk tapi belum absen pulang';

    public function handle(): int
    {
        $this->info('Mengecek karyawan yang belum absen pulang...');

        // Ambil absensi yang sudah masuk tapi belum pulang hari ini
        $absensiMasuk = Absensi::with(['karyawan', 'shift'])
            ->whereDate('tanggal', today())
            ->whereNotNull('waktu_masuk')
            ->whereNull('waktu_pulang')
            ->whereHas('karyawan', fn ($q) => $q->where('is_active', true))
            ->get();

        $terkirim = 0;
        $dilewati = 0;

        foreach ($absensiMasuk as $absensi) {
            $karyawan = $absensi->karyawan;
            $shift    = $absensi->shift;

            // Cek apakah sudah melewati jam pulang + 15 menit
            $batasNotifikasi = today()
                ->setTimeFromTimeString($shift->jam_pulang)
                ->addMinutes(15);

            if (now()->lt($batasNotifikasi)) {
                $dilewati++;
                continue;
            }

            // Cek apakah notifikasi ini sudah pernah dikirim hari ini
            $sudahNotif = $karyawan->notifications()
                ->whereDate('created_at', today())
                ->where('data->tipe', 'belum_absen_pulang')
                ->exists();

            if ($sudahNotif) {
                $dilewati++;
                continue;
            }

            $karyawan->notify(new BelumAbsen(
                jenisAbsen: 'pulang',
                namaShift:  $shift->nama_shift,
                jamShift:   "Pulang: {$shift->jam_pulang}",
            ));

            $terkirim++;
            $this->line("  → Notifikasi dikirim ke: {$karyawan->nama} (shift {$shift->nama_shift})");
        }

        $this->info("Selesai. Terkirim: {$terkirim}, Dilewati: {$dilewati}");

        return self::SUCCESS;
    }
}
