<?php

namespace App\Console\Commands;

use App\Models\Absensi;
use App\Models\KaryawanShift;
use Illuminate\Console\Command;

class RekapHarian extends Command
{
    protected $signature   = 'absensi:rekap-harian {--tanggal= : Tanggal rekap (Y-m-d), default kemarin}';
    protected $description = 'Generate rekap harian — tandai karyawan yang tidak absen sebagai alpha';

    public function handle(): int
    {
        $tanggal = $this->option('tanggal')
            ? \Carbon\Carbon::parse($this->option('tanggal'))
            : today()->subDay(); // Default: kemarin

        $this->info("Membuat rekap harian untuk tanggal: {$tanggal->format('d M Y')}");

        // Ambil semua karyawan yang punya shift aktif pada tanggal tersebut
        $karyawanShifts = KaryawanShift::with(['karyawan', 'shift'])
            ->where('tanggal_berlaku', '<=', $tanggal)
            ->where(fn ($q) => $q->whereNull('tanggal_berakhir')
                ->orWhere('tanggal_berakhir', '>=', $tanggal))
            ->whereHas('karyawan', fn ($q) => $q->where('is_active', true))
            ->get();

        $diproses  = 0;
        $dilewati  = 0;
        $alphaCount = 0;

        foreach ($karyawanShifts as $ks) {
            $karyawan = $ks->karyawan;

            // Cek apakah sudah ada record absensi untuk tanggal ini
            $absensi = $karyawan->absensi()
                ->whereDate('tanggal', $tanggal)
                ->first();

            if ($absensi) {
                $dilewati++;
                continue; // Sudah ada record, skip
            }

            // Tidak ada record absensi = alpha
            // Tapi cek dulu apakah hari itu adalah hari libur (bisa dikembangkan nanti)
            Absensi::create([
                'karyawan_id'    => $karyawan->id,
                'shift_id'       => $ks->shift_id,
                'qr_instansi_id' => $ks->karyawan->instansi
                    ->qrInstansi()
                    ->where('is_active', true)
                    ->first()?->id ?? 1,
                'tanggal'        => $tanggal,
                'status'         => 'alpha',
                'keterangan'     => 'Tidak hadir - otomatis dari rekap harian',
            ]);

            $alphaCount++;
            $diproses++;
            $this->line("  → Alpha: {$karyawan->nama} ({$tanggal->format('d M Y')})");
        }

        $this->info("Selesai. Diproses: {$diproses} (Alpha: {$alphaCount}), Dilewati: {$dilewati}");
        $this->table(
            ['Tanggal', 'Total Shift Aktif', 'Sudah Absen', 'Alpha Baru'],
            [[
                $tanggal->format('d M Y'),
                $karyawanShifts->count(),
                $dilewati,
                $alphaCount,
            ]]
        );

        return self::SUCCESS;
    }
}
