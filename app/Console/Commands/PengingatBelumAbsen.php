<?php

namespace App\Console\Commands;

use App\Models\Karyawan;
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
    $hari = today();
    $terkirim = 0;
    $dilewati = 0;

    foreach (Karyawan::where('is_active', true)->get() as $karyawan) {
        $shift = $karyawan->shiftYangDiharapkanPada($hari);

        if (! $shift) {
            $dilewati++;
            continue;
        }

        $batas = $hari->copy()
            ->setTimeFromTimeString($shift->jamMasukString())
            ->addMinutes($shift->toleransi_menit + 15);

        if (now()->lt($batas)) {
            $dilewati++;
            continue;
        }

        // Sudah absen masuk?
        $sudahMasuk = $karyawan->absensi()
            ->whereDate('tanggal', $hari)
            ->whereNotNull('waktu_masuk')
            ->exists();

        if ($sudahMasuk) {
            $dilewati++;
            continue;
        }

        $sudahNotif = $karyawan->notifications()
            ->where('data->tipe', 'belum_absen_masuk')
            ->where('data->tanggal', $hari->toDateString())
            ->exists();

        if ($sudahNotif) {
            $dilewati++;
            continue;
        }

        $karyawan->notify(new BelumAbsen(
            jenisAbsen:   'masuk',
            namaShift:    $shift->nama_shift,
            jamShift:     "Masuk: {$shift->jamMasukString()} — Pulang: {$shift->jamPulangString()}",
            tanggalShift: $hari,
        ));

        $terkirim++;
        $this->line("  → Notifikasi dikirim ke: {$karyawan->nama} (shift {$shift->nama_shift})");
    }

    $this->info("Selesai. Terkirim: {$terkirim}, Dilewati: {$dilewati}");

    return self::SUCCESS;
}
}
