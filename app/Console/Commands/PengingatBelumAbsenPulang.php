<?php

namespace App\Console\Commands;

use App\Models\Absensi;
use App\Models\KaryawanShift;
use App\Notifications\BelumAbsen;
use Illuminate\Console\Command;
use Carbon\Carbon;


class PengingatBelumAbsenPulang extends Command
{
    protected $signature   = 'absensi:pengingat-belum-pulang';
    protected $description = 'Kirim notifikasi ke karyawan yang sudah masuk tapi belum absen pulang';


public function handle(): int
{
    $this->info('Mengecek karyawan yang belum absen pulang...');
    $terkirim = 0;
    $dilewati = 0;

    $kandidat = Absensi::with(['karyawan', 'shift'])
        ->whereBetween('tanggal', [today()->subDay(), today()])
        ->whereNotNull('waktu_masuk')
        ->whereNull('waktu_pulang')
        ->whereHas('karyawan', fn ($q) => $q->where('is_active', true))
        ->get();

    foreach ($kandidat as $absensi) {
        $karyawan = $absensi->karyawan;
        $shift    = $absensi->shift;

        if (! $shift) {
            $dilewati++;
            continue;
        }

        // Jam pulang dihitung dari tanggal ABSEN MASUK. Shift malam
        // (jam pulang <= jam masuk) jatuh di hari berikutnya.
        $deadline = Carbon::parse($absensi->tanggal)
            ->setTimeFromTimeString($shift->jamPulangString());

        if ($shift->jamPulangString() <= $shift->jamMasukString()) {
            $deadline->addDay();
        }

        if (now()->lt($deadline->copy()->addMinutes(15))) {
            $dilewati++;
            continue;
        }

        $sudahNotif = $karyawan->notifications()
            ->where('data->tipe', 'belum_absen_pulang')
            ->where('data->absensi_id', $absensi->id)
            ->exists();

        if ($sudahNotif) {
            $dilewati++;
            continue;
        }

        $karyawan->notify(new BelumAbsen(
            jenisAbsen:   'pulang',
            namaShift:    $shift->nama_shift,
            jamShift:     "Pulang: {$shift->jamPulangString()}",
            tanggalShift: Carbon::parse($absensi->tanggal),
            absensiId:    $absensi->id,
        ));
        $terkirim++;
        $this->line("  → Notifikasi dikirim ke: {$karyawan->nama} (shift {$shift->nama_shift})");
    }

    $this->info("Selesai. Terkirim: {$terkirim}, Dilewati: {$dilewati}");

    return self::SUCCESS;
}
}
