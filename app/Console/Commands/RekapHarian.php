<?php

namespace App\Console\Commands;

use App\Models\Absensi;
use App\Models\HariLibur;
use App\Models\Jadwal;
use App\Models\Karyawan;
use App\Models\KaryawanShift;
use Illuminate\Console\Command;

class RekapHarian extends Command
{
    protected $signature   = 'absensi:rekap-harian {--tanggal= : Tanggal rekap (Y-m-d), default kemarin}';
    protected $description = 'Generate rekap harian — tandai karyawan yang tidak absen sebagai alpha, dengan cek hari libur & tipe jadwal';

    public function handle(): int
    {
        $tanggal = $this->option('tanggal')
            ? \Carbon\Carbon::parse($this->option('tanggal'))
            : today()->subDay();

        $this->info("Membuat rekap harian untuk tanggal: {$tanggal->format('d M Y')}");

        $karyawanAktif = Karyawan::with('instansi')
            ->where('is_active', true)
            ->get();

        $stat = [
            'sudah_absen'     => 0,
            'libur_mingguan'  => 0,
            'libur_nasional'  => 0,
            'libur_personal'  => 0,
            'jadwal_hilang'   => 0,
            'alpha'           => 0,
        ];

        foreach ($karyawanAktif as $karyawan) {
            // 1. Sudah ada record absensi (termasuk dari sync cuti/dinas/absen manual)?
            $sudahAda = $karyawan->absensi()->whereDate('tanggal', $tanggal)->exists();
            if ($sudahAda) {
                $stat['sudah_absen']++;
                continue;
            }

            // 2. Cek Jadwal eksplisit untuk tanggal ini
            $jadwal = Jadwal::where('karyawan_id', $karyawan->id)
                ->where('tanggal', $tanggal->toDateString())
                ->first();

            $shiftId = null;
            $dijadwalkanKerja = false;

            if ($karyawan->isRotasi()) {
                // Karyawan ROTASI: Jadwal WAJIB eksplisit tiap hari, TIDAK ADA
                // fallback ke KaryawanShift. Kalau gak ketemu, ini anomali data
                // (jadwal lupa dibuat admin) — bukan otomatis dianggap libur.
                if (! $jadwal) {
                    $stat['jadwal_hilang']++;
                    $this->warn("  → Jadwal hilang: {$karyawan->nama} ({$tanggal->format('d M Y')}) — tipe rotasi tanpa Jadwal tercatat, cek manual.");
                    continue;
                }

                if ($jadwal->jenis === 'libur') {
                    $stat['libur_personal']++;
                    continue;
                }

                // jenis reguler/piket
                $dijadwalkanKerja = true;
                $shiftId = $jadwal->shift_id;
            } else {
                // Karyawan UMUM: Jadwal manual (override/piket tambahan) kalau
                // ada, kalau tidak fallback ke KaryawanShift + pola hari_kerja.
                if ($jadwal && $jadwal->jenis === 'libur') {
                    $stat['libur_personal']++;
                    continue;
                }

                if ($jadwal && in_array($jadwal->jenis, ['reguler', 'piket'])) {
                    $dijadwalkanKerja = true;
                    $shiftId = $jadwal->shift_id;
                } else {
                    $karyawanShift = KaryawanShift::with('shift')
                        ->where('karyawan_id', $karyawan->id)
                        ->where('tanggal_berlaku', '<=', $tanggal)
                        ->where(fn ($q) => $q->whereNull('tanggal_berakhir')
                            ->orWhere('tanggal_berakhir', '>=', $tanggal))
                        ->latest('tanggal_berlaku')
                        ->first();

                    if ($karyawanShift && $karyawanShift->shift->adalahHariKerja($tanggal)) {
                        $dijadwalkanKerja = true;
                        $shiftId = $karyawanShift->shift_id;
                    }
                }

                if (! $dijadwalkanKerja) {
                    $stat['libur_mingguan']++;
                    continue; // Bukan hari kerjanya, wajar gak absen
                }
            }

            // 3. Cek hari libur nasional/instansi
            $adalahHariLibur = HariLibur::where('instansi_id', $karyawan->instansi_id)
                ->whereDate('tanggal', $tanggal)
                ->exists();

            if ($adalahHariLibur) {
                Absensi::create([
                    'karyawan_id' => $karyawan->id,
                    'shift_id' => $shiftId,
                    'tanggal' => $tanggal,
                    'status' => 'libur',
                    'keterangan' => 'Hari libur nasional/cuti bersama - otomatis dari rekap harian',
                ]);
                $stat['libur_nasional']++;
                continue;
            }

            // 4. Dijadwalkan kerja, bukan hari libur, gak ada absensi = alpha
            $qrInstansiId = $karyawan->instansi->qrInstansi()
                ->where('is_active', true)
                ->first()?->id;

            Absensi::create([
                'karyawan_id' => $karyawan->id,
                'shift_id' => $shiftId,
                'qr_instansi_id' => $qrInstansiId,
                'tanggal' => $tanggal,
                'status' => 'alpha',
                'keterangan' => 'Tidak hadir - otomatis dari rekap harian',
            ]);

            $stat['alpha']++;
            $this->line("  → Alpha: {$karyawan->nama} ({$tanggal->format('d M Y')})");
        }

        $this->info('Selesai.');
        $this->table(
            ['Tanggal', 'Total Karyawan', 'Sudah Absen', 'Libur Mingguan', 'Libur Nasional', 'Libur Personal', 'Jadwal Hilang', 'Alpha Baru'],
            [[
                $tanggal->format('d M Y'),
                $karyawanAktif->count(),
                $stat['sudah_absen'],
                $stat['libur_mingguan'],
                $stat['libur_nasional'],
                $stat['libur_personal'],
                $stat['jadwal_hilang'],
                $stat['alpha'],
            ]]
        );

        if ($stat['jadwal_hilang'] > 0) {
            $this->newLine();
            $this->warn("⚠ {$stat['jadwal_hilang']} karyawan rotasi gak punya Jadwal tercatat hari ini. Ini BUKAN alpha dan BUKAN libur — cek manual, kemungkinan jadwal lupa dibuat.");
        }

        return self::SUCCESS;
    }
}
