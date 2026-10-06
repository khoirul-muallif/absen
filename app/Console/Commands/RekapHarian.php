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
            // 1. Sudah ada record absensi (sync cuti/dinas, absen manual)?
            if ($karyawan->absensi()->whereDate('tanggal', $tanggal)->exists()) {
                $stat['sudah_absen']++;
                continue;
            }

            // 2. Jadwal libur eksplisit (berlaku untuk umum & rotasi)
            $jadwal = Jadwal::where('karyawan_id', $karyawan->id)
                ->whereDate('tanggal', $tanggal)
                ->first();

            if ($jadwal?->jenis === Jadwal::JENIS_LIBUR) {
                $stat['libur_personal']++;
                continue;
            }

            // 3. Seharusnya kerja atau tidak?
            // shiftYangDiharapkanPada() sudah mengembalikan null untuk libur instansi,
            // cuti/dinas approved, dan hari tidak wajib masuk.
            $shift = $karyawan->shiftYangDiharapkanPada($tanggal);

            if (! $shift) {
                if ($karyawan->jadwalRotasiHilangPada($tanggal)) {
                    $stat['jadwal_hilang']++;
                    $this->warn("  → Jadwal hilang: {$karyawan->nama} ({$tanggal->format('d M Y')}) — cek manual.");
                    continue;
                }

                $adalahHariLibur = HariLibur::where('instansi_id', $karyawan->instansi_id)
                    ->whereDate('tanggal', $tanggal)
                    ->exists();

                if ($adalahHariLibur) {
                    Absensi::create([
                        'karyawan_id' => $karyawan->id,
                        'tanggal'     => $tanggal,
                        'status'      => 'libur',
                        'keterangan'  => 'Hari libur nasional/cuti bersama - otomatis dari rekap harian',
                    ]);
                    $stat['libur_nasional']++;
                } else {
                    $stat['libur_mingguan']++;
                }
                continue;
            }

            // 4. Seharusnya kerja, belum absen = alpha
            $qrInstansiId = $karyawan->instansi->qrInstansi()
                ->where('is_active', true)
                ->first()?->id;

            Absensi::create([
                'karyawan_id'    => $karyawan->id,
                'shift_id'       => $shift->id,
                'qr_instansi_id' => $qrInstansiId,
                'tanggal'        => $tanggal,
                'status'         => 'alpha',
                'keterangan'     => 'Tidak hadir - otomatis dari rekap harian',
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
