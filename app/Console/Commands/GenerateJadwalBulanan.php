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
            'dibuat_libur_nasional' => 0,
            'dibuat_libur_mingguan' => 0,
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
                // Nama command sengaja tidak disebut: pesan ini ikut
                // Artisan::output() dan ditampilkan ke admin di halaman
                // Generate Jadwal, yang tidak punya terminal. Nama
                // command-nya di runbook.md.
                $this->warn("  → Lewati: {$karyawan->nama} bertipe ROTASI tapi masih punya penugasan shift periode. Lepas penugasannya di menu Shift Karyawan Umum, atau ubah tipe jadwalnya.");
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

                // 2. Bukan hari kerja pola shift ini? -> generate eksplisit jenis 'libur'
                //    (opsi A dari todo.md) — supaya "Pindah Tanggal" TukarJadwal punya
                //    tanggal tujuan valid untuk karyawan umum, konsisten dengan rotasi
                //    yang juga punya baris eksplisit untuk hari liburnya sendiri.
                if (! $assignment->shift->adalahHariKerja($tanggal)) {
                    if (! $dryRun) {
                        Jadwal::create([
                            'karyawan_id'    => $karyawan->id,
                            'shift_id'       => null,
                            'tanggal'        => $tanggal->toDateString(),
                            'jenis'          => Jadwal::JENIS_LIBUR,
                            'libur_nasional' => false,
                            'keterangan'     => 'Libur mingguan - otomatis dari generator jadwal bulanan',
                        ]);
                    }
                    $stat['dibuat_libur_mingguan']++;
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
                    ->meliburkan()
                    ->whereDate('tanggal', $tanggal)
                    ->exists();

                if ($adalahHariLibur) {
                    if (! $dryRun) {
                        Jadwal::create([
                            'karyawan_id'    => $karyawan->id,
                            'shift_id'       => null,
                            'tanggal'        => $tanggal->toDateString(),
                            'jenis'          => Jadwal::JENIS_LIBUR,
                            'libur_nasional' => true,
                            'keterangan'     => 'Hari libur nasional - otomatis dari generator jadwal bulanan',
                        ]);
                    }
                    $stat['dibuat_libur_nasional']++;
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
        $this->info($dryRun ? 'Preview selesai, belum ada data yang disimpan.' : 'Selesai.');

        // Bukan $this->table(): halaman Generate Jadwal menampilkan
        // Artisan::output() apa adanya, dan bingkai ASCII tabel larut jadi
        // dinding karakter di notifikasi Filament. Satu baris per angka
        // terbaca di terminal maupun di panel.
        $this->info("Jadwal reguler dibuat: {$stat['dibuat_reguler']}");
        $this->info("Libur mingguan dibuat: {$stat['dibuat_libur_mingguan']}");
        $this->info("Libur nasional dibuat: {$stat['dibuat_libur_nasional']}");
        $this->info("Dilewati (sudah ada jadwal): {$stat['skip_sudah_ada']}");
        $this->info("Dilewati (cuti/dinas approved): {$stat['skip_cuti_dinas']}");

        return self::SUCCESS;
    }
}
