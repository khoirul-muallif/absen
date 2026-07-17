<?php

namespace App\Console\Commands;

use App\Models\Cuti;
use App\Models\Dinas;
use App\Models\HariLibur;
use App\Models\Jadwal;
use App\Models\KaryawanShift;
use Illuminate\Console\Command;

class GenerateJadwalBulanan extends Command
{
    protected $signature   = 'jadwal:generate-bulanan
                                {--bulan= : Bulan target (1-12), default bulan depan}
                                {--tahun= : Tahun target, default tahun bulan depan}
                                {--dry-run : Cuma preview, gak nyimpen apa-apa}';

    protected $description = 'Generate Jadwal bulanan otomatis dari KaryawanShift (assignment periode), skip hari libur mingguan/nasional & cuti/dinas approved';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $bulanDepan = today()->addMonthNoOverflow();
        $bulan = (int) ($this->option('bulan') ?? $bulanDepan->month);
        $tahun = (int) ($this->option('tahun') ?? $bulanDepan->year);

        $awalBulan = \Carbon\Carbon::create($tahun, $bulan, 1)->startOfMonth();
        $akhirBulan = $awalBulan->copy()->endOfMonth();

        $this->info(($dryRun ? '[DRY-RUN] ' : '') . "Generate Jadwal untuk periode: {$awalBulan->format('d M Y')} s/d {$akhirBulan->format('d M Y')}");

        // Assignment KaryawanShift yang overlap dengan bulan target
        $assignments = KaryawanShift::with(['karyawan.instansi', 'shift'])
            ->whereHas('karyawan', fn ($q) => $q->where('is_active', true))
            ->where('tanggal_berlaku', '<=', $akhirBulan)
            ->where(fn ($q) => $q->whereNull('tanggal_berakhir')
                ->orWhere('tanggal_berakhir', '>=', $awalBulan))
            ->get();

        $stat = [
            'dibuat_reguler'   => 0,
            'dibuat_libur'     => 0,
            'skip_sudah_ada'   => 0,
            'skip_libur_mingguan' => 0,
            'skip_cuti_dinas'  => 0,
        ];

        foreach ($assignments as $assignment) {
            if (! $assignment->shift) {
                $this->warn("  → Lewati: KaryawanShift #{$assignment->id} tidak punya shift (data rusak?).");
                continue;
            }

            $karyawan = $assignment->karyawan;

            // Pengaman: seharusnya gak mungkin lolos sampai sini (KaryawanShiftForm
            // udah membatasi cuma karyawan tipe umum), tapi kalau ada yang lolos
            // lewat tinker/seeder manual, jangan ikut digenerate — bisa bentrok
            // sama Jadwal manual yang dibuat admin untuk karyawan rotasi ini.
            if ($karyawan->isRotasi()) {
                $this->warn("  → Lewati: {$karyawan->nama} tipe ROTASI tapi punya KaryawanShift #{$assignment->id}. Jalankan `php artisan karyawan:cek-tipe-jadwal` untuk detail.");
                continue;
            }
            $instansiId = $karyawan->instansi_id;

            $mulai = $assignment->tanggal_berlaku->greaterThan($awalBulan) ? $assignment->tanggal_berlaku : $awalBulan;
            $selesai = $assignment->tanggal_berakhir && $assignment->tanggal_berakhir->lessThan($akhirBulan)
                ? $assignment->tanggal_berakhir
                : $akhirBulan;

            for ($tanggal = $mulai->copy(); $tanggal->lte($selesai); $tanggal->addDay()) {
                // 1. Udah ada Jadwal eksplisit di tanggal ini? (manual override / tukar jadwal / assignment lain)
                $sudahAda = Jadwal::where('karyawan_id', $karyawan->id)
                    ->where('tanggal', $tanggal->toDateString())
                    ->exists();

                if ($sudahAda) {
                    $stat['skip_sudah_ada']++;
                    continue;
                }

                // 2. Bukan hari kerja pola shift ini? -> libur mingguan wajar, gak perlu row
                //    (konsisten dengan fallback RekapHarian)
                if (! $assignment->shift->adalahHariKerja($tanggal)) {
                    $stat['skip_libur_mingguan']++;
                    continue;
                }

                // 3. Cuti/Dinas approved di tanggal ini? -> skip, biar gak bentrok
                //    (konsisten dengan validasi manual di JadwalForm)
                $bentrokCutiDinas = Cuti::where('karyawan_id', $karyawan->id)
                    ->where('status', 'approved')
                    ->whereDate('tanggal_mulai', '<=', $tanggal)
                    ->whereDate('tanggal_selesai', '>=', $tanggal)
                    ->exists()
                    || Dinas::where('karyawan_id', $karyawan->id)
                    ->where('status', 'approved')
                    ->whereDate('tanggal_mulai', '<=', $tanggal)
                    ->whereDate('tanggal_selesai', '>=', $tanggal)
                    ->exists();

                if ($bentrokCutiDinas) {
                    $stat['skip_cuti_dinas']++;
                    continue;
                }

                // 4. Hari libur nasional/cuti bersama? -> generate eksplisit jenis 'libur'
                //    supaya tabel jadwal lengkap keliatan, bukan cuma "gap kosong"
                $adalahHariLibur = HariLibur::where('instansi_id', $instansiId)
                    ->whereDate('tanggal', $tanggal)
                    ->exists();

                if ($adalahHariLibur) {
                    if (! $dryRun) {
                        Jadwal::create([
                            'karyawan_id' => $karyawan->id,
                            'shift_id'    => null,
                            'tanggal'     => $tanggal->toDateString(),
                            'jenis'       => Jadwal::JENIS_LIBUR,
                            'keterangan'  => 'Hari libur nasional/cuti bersama - otomatis dari generator jadwal bulanan',
                        ]);
                    }
                    $stat['dibuat_libur']++;
                    continue;
                }

                // 5. Hari kerja normal -> generate jenis reguler
                if (! $dryRun) {
                    Jadwal::create([
                        'karyawan_id' => $karyawan->id,
                        'shift_id'    => $assignment->shift_id,
                        'tanggal'     => $tanggal->toDateString(),
                        'jenis'       => Jadwal::JENIS_REGULER,
                        'keterangan'  => 'Otomatis dari generator jadwal bulanan',
                    ]);
                }
                $stat['dibuat_reguler']++;
            }
        }

        $this->newLine();
        $this->info($dryRun ? 'Preview selesai (belum ada data yang disimpan).' : 'Selesai.');
        $this->table(
            ['Periode', 'Reguler Dibuat', 'Libur Dibuat', 'Skip (Sudah Ada)', 'Skip (Libur Mingguan)', 'Skip (Cuti/Dinas)'],
            [[
                $awalBulan->format('M Y'),
                $stat['dibuat_reguler'],
                $stat['dibuat_libur'],
                $stat['skip_sudah_ada'],
                $stat['skip_libur_mingguan'],
                $stat['skip_cuti_dinas'],
            ]]
        );

        return self::SUCCESS;
    }
}
