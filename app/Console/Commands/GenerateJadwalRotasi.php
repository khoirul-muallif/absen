<?php

namespace App\Console\Commands;

use App\Models\HariLibur;
use App\Models\Jadwal;
use App\Models\KaryawanPolaRotasi;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateJadwalRotasi extends Command
{
    protected $signature = 'jadwal:generate-rotasi
                            {bulan : Bulan target (1-12)}
                            {tahun : Tahun target (contoh: 2026)}
                            {--unit= : Filter unit_kerja tertentu}
                            {--overwrite-generate : Timpa ulang baris yang sumbernya generate; baris manual tetap tidak disentuh}';

    protected $description = 'Generate Jadwal bulanan untuk karyawan tipe_jadwal=rotasi berdasarkan pola_rotasi yang di-assign';

    public function handle(): int
    {
        $bulan = (int) $this->argument('bulan');
        $tahun = (int) $this->argument('tahun');

        $awalBulan = Carbon::create($tahun, $bulan, 1)->startOfDay();
        $akhirBulan = $awalBulan->copy()->endOfMonth();

        $query = KaryawanPolaRotasi::query()
            ->with(['karyawan', 'polaRotasi'])
            ->whereHas('karyawan', fn ($q) => $q->where('tipe_jadwal', 'rotasi'))
            ->where('tanggal_mulai', '<=', $akhirBulan)
            ->where(function ($q) use ($awalBulan) {
                $q->whereNull('tanggal_berakhir')->orWhere('tanggal_berakhir', '>=', $awalBulan);
            });

        if ($unit = $this->option('unit')) {
            $query->whereHas('polaRotasi', fn ($q) => $q->where('unit_kerja', $unit));
        }

        $assignments = $query->get();

        if ($assignments->isEmpty()) {
            $this->warn('Tidak ada assignment pola rotasi aktif untuk bulan ini.');
            return self::SUCCESS;
        }

        // Cache hari libur nasional sebulan ini, per instansi karyawan, biar nggak query berulang tiap hari.
        $hariLiburPerInstansi = HariLibur::whereBetween('tanggal', [$awalBulan, $akhirBulan])
            ->get()
            ->groupBy('instansi_id')
            ->map(fn ($grup) => $grup->pluck('tanggal')->map(fn ($t) => $t->format('Y-m-d'))->flip());

        $dibuat = 0;
        $dilewati = 0;

        foreach ($assignments as $assignment) {
            $karyawan = $assignment->karyawan;
            $pola = $assignment->polaRotasi;
            $liburNasional = $hariLiburPerInstansi->get($karyawan->instansi_id, collect());

            $tanggal = $awalBulan->copy();
            while ($tanggal->lte($akhirBulan)) {
                if ($tanggal->lt($assignment->tanggal_mulai) ||
                    ($assignment->tanggal_berakhir && $tanggal->gt($assignment->tanggal_berakhir))) {
                    $tanggal->addDay();
                    continue;
                }

                $existing = Jadwal::where('karyawan_id', $karyawan->id)
                    ->whereDate('tanggal', $tanggal)
                    ->first();

                if ($existing && ($existing->sumber === 'manual' || ! $this->option('overwrite-generate'))) {
                    $dilewati++;
                    $tanggal->addDay();
                    continue;
                }

                $posisi = $assignment->posisiSiklusPada($tanggal);
                $langkah = $pola->langkahKe($posisi);

                $isLiburNasional = $liburNasional->has($tanggal->format('Y-m-d'));

                if ($isLiburNasional && ! $pola->berlaku_saat_libur_nasional) {
                    $jenis = 'libur';
                    $shiftId = null;
                } elseif ($langkah['libur']) {
                    $jenis = 'libur';
                    $shiftId = null;
                } else {
                    $jenis = 'piket';
                    $shiftId = $langkah['shift_id'];
                }

                Jadwal::updateOrCreate(
                    ['karyawan_id' => $karyawan->id, 'tanggal' => $tanggal->format('Y-m-d')],
                    ['shift_id' => $shiftId, 'jenis' => $jenis, 'sumber' => 'generate']
                );

                $dibuat++;
                $tanggal->addDay();
            }
        }

        $this->info("Jadwal dibuat/diupdate: {$dibuat}");
        $this->info("Dilewati (sudah ada & tidak ditimpa): {$dilewati}");

        return self::SUCCESS;
    }
}
