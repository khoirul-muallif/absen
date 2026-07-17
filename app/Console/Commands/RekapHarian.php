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
    protected $description = 'Generate rekap harian — tandai karyawan yang tidak absen sebagai alpha, dengan cek hari libur';

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
            'sudah_absen' => 0,
            'libur_mingguan' => 0,
            'libur_nasional' => 0,
            'libur_personal' => 0,
            'alpha' => 0,
        ];

        foreach ($karyawanAktif as $karyawan) {
            // 1. Sudah ada record absensi (termasuk dari sync cuti/dinas/absen manual)?
            $sudahAda = $karyawan->absensi()->whereDate('tanggal', $tanggal)->exists();
            if ($sudahAda) {
                $stat['sudah_absen']++;
                continue;
            }

            // 2. Cek Jadwal eksplisit untuk tanggal ini (nutup karyawan rotasi + override manual)
            $jadwal = Jadwal::where('karyawan_id', $karyawan->id)
                ->where('tanggal', $tanggal->toDateString())
                ->first();

            if ($jadwal && $jadwal->jenis === 'libur') {
                $stat['libur_personal']++;
                continue; // Jadwal eksplisit bilang libur, gak perlu apa-apa
            }

            // 3. Tentuin apakah dijadwalkan kerja hari ini
            $shiftId = null;
            $dijadwalkanKerja = false;

            if ($jadwal && in_array($jadwal->jenis, ['reguler', 'piket'])) {
                $dijadwalkanKerja = true;
                $shiftId = $jadwal->shift_id;
            } else {
                // Fallback: cek KaryawanShift (assignment periode) + pola hari_kerja shift-nya
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

            // 4. Cek hari libur nasional/instansi
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

            // 5. Dijadwalkan kerja, bukan hari libur, gak ada absensi = alpha
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
            ['Tanggal', 'Total Karyawan', 'Sudah Absen', 'Libur Mingguan', 'Libur Nasional', 'Libur Personal', 'Alpha Baru'],
            [[
                $tanggal->format('d M Y'),
                $karyawanAktif->count(),
                $stat['sudah_absen'],
                $stat['libur_mingguan'],
                $stat['libur_nasional'],
                $stat['libur_personal'],
                $stat['alpha'],
            ]]
        );

        return self::SUCCESS;
    }
}
