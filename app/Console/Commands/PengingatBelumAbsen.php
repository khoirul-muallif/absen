<?php

namespace App\Console\Commands;

use App\Models\KaryawanShift;
use App\Notifications\BelumAbsen;
use Illuminate\Console\Command;

class PengingatBelumAbsen extends Command
{
    protected $signature   = 'absensi:pengingat-belum-absen';
    protected $description = 'Kirim notifikasi ke karyawan yang belum absen masuk hari ini';

    public function handle(): int
    {
        $this->info('Mengecek karyawan yang belum absen masuk...');

        // Ambil semua karyawan yang punya shift aktif hari ini
        $karyawanShifts = KaryawanShift::with(['karyawan', 'shift'])
            ->where('tanggal_berlaku', '<=', today())
            ->where(fn ($q) => $q->whereNull('tanggal_berakhir')
                ->orWhere('tanggal_berakhir', '>=', today()))
            ->whereHas('karyawan', fn ($q) => $q->where('is_active', true))
            ->get();

        $terkirim = 0;
        $dilewati = 0;

        foreach ($karyawanShifts as $ks) {
            $karyawan = $ks->karyawan;
            $shift    = $ks->shift;

            // Cek apakah sudah melewati jam masuk + toleransi
            $batasNotifikasi = today()
                ->setTimeFromTimeString($shift->jam_masuk)
                ->addMinutes($shift->toleransi_menit + 15); // 15 menit grace period

            if (now()->lt($batasNotifikasi)) {
                $dilewati++;
                continue; // Belum waktunya notifikasi
            }

            // Cek apakah karyawan sudah absen hari ini
            $sudahAbsen = $karyawan->absensi()
                ->whereDate('tanggal', today())
                ->whereNotNull('waktu_masuk')
                ->exists();

            if ($sudahAbsen) {
                $dilewati++;
                continue;
            }

            // Cek apakah notifikasi ini sudah pernah dikirim hari ini
            $sudahNotif = $karyawan->notifications()
                ->whereDate('created_at', today())
                ->where('data->tipe', 'belum_absen_masuk')
                ->exists();

            if ($sudahNotif) {
                $dilewati++;
                continue;
            }

            // Kirim notifikasi
            $karyawan->notify(new BelumAbsen(
                jenisAbsen: 'masuk',
                namaShift:  $shift->nama_shift,
                jamShift:   "Masuk: {$shift->jam_masuk} — Pulang: {$shift->jam_pulang}",
            ));

            $terkirim++;
            $this->line("  → Notifikasi dikirim ke: {$karyawan->nama} (shift {$shift->nama_shift})");
        }

        $this->info("Selesai. Terkirim: {$terkirim}, Dilewati: {$dilewati}");

        return self::SUCCESS;
    }
}
