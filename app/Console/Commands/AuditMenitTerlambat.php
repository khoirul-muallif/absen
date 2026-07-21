<?php

namespace App\Console\Commands;

use App\Models\Absensi;
use Illuminate\Console\Command;

class AuditMenitTerlambat extends Command
{
    protected $signature = 'absensi:audit-menit-terlambat
                            {--instansi= : Filter by instansi_id}
                            {--fix : Backfill data yang salah (WAJIB backup DB dulu)}';

    protected $description = 'Audit & (opsional) backfill menit_terlambat + melebihi_toleransi_bulanan pada data Absensi lama';

    public function handle(): int
    {
        $query = Absensi::query()
            ->whereNotNull('waktu_masuk')
            ->whereNotNull('shift_id')
            ->with('shift')
            ->orderBy('karyawan_id')
            ->orderBy('tanggal');

        if ($instansiId = $this->option('instansi')) {
            $query->whereHas('karyawan', fn ($q) => $q->where('instansi_id', $instansiId));
        }

        $totalDicek = 0;
        $salahHarian = 0;
        $salahBulanan = 0;
        $rowsHarian = [];
        $rowsBulanan = [];

        $karyawanIdSaatIni = null;
        $bulanSaatIni = null;
        $akumulasi = 0;

        // chunk() (bukan chunkById) sengaja dipakai karena kita butuh urutan
        // karyawan_id + tanggal buat replay akumulasi bulanan; update kolom
        // menit_terlambat/melebihi_toleransi_bulanan tidak mengubah urutan ini,
        // jadi offset-based chunking tetap aman di sini.
        $query->chunk(500, function ($absensis) use (
            &$totalDicek, &$salahHarian, &$salahBulanan,
            &$rowsHarian, &$rowsBulanan,
            &$karyawanIdSaatIni, &$bulanSaatIni, &$akumulasi
        ) {
            foreach ($absensis as $absensi) {
                if (! $absensi->shift) {
                    continue;
                }
                $totalDicek++;

                $bulanIni = $absensi->tanggal->format('Y-m');
                if ($absensi->karyawan_id !== $karyawanIdSaatIni || $bulanIni !== $bulanSaatIni) {
                    $karyawanIdSaatIni = $absensi->karyawan_id;
                    $bulanSaatIni = $bulanIni;
                    $akumulasi = 0;
                }

                $menitBenar = $absensi->shift->hitungMenitTerlambat($absensi->waktu_masuk);
                $akumulasi += $menitBenar;
                $melebihiBenar = $absensi->shift->sudahMelebihiToleransiBulanan($akumulasi);

                $bedaHarian = $menitBenar !== $absensi->menit_terlambat;
                $bedaBulanan = $melebihiBenar !== (bool) $absensi->melebihi_toleransi_bulanan;

                if ($bedaHarian) {
                    $salahHarian++;
                    $rowsHarian[] = [$absensi->id, $absensi->karyawan_id, $absensi->tanggal->format('Y-m-d'), $absensi->menit_terlambat, $menitBenar];
                }

                if ($bedaBulanan) {
                    $salahBulanan++;
                    $rowsBulanan[] = [$absensi->id, $absensi->karyawan_id, $absensi->tanggal->format('Y-m-d'), $absensi->melebihi_toleransi_bulanan ? 'ya' : 'tidak', $melebihiBenar ? 'ya' : 'tidak', $akumulasi];
                }

                if ($this->option('fix') && ($bedaHarian || $bedaBulanan)) {
                    $absensi->update([
                        'menit_terlambat' => $menitBenar,
                        'melebihi_toleransi_bulanan' => $melebihiBenar,
                    ]);
                }
            }
        });

        $this->info("Total data dicek: {$totalDicek}");
        $this->info("menit_terlambat salah: {$salahHarian}");
        $this->info("melebihi_toleransi_bulanan salah: {$salahBulanan}");

        if ($rowsHarian) {
            $this->line('--- menit_terlambat ---');
            $this->table(['ID', 'Karyawan', 'Tanggal', 'Lama', 'Benar'], array_slice($rowsHarian, 0, 30));
        }
        if ($rowsBulanan) {
            $this->line('--- melebihi_toleransi_bulanan ---');
            $this->table(['ID', 'Karyawan', 'Tanggal', 'Lama', 'Benar', 'Akumulasi'], array_slice($rowsBulanan, 0, 30));
        }

        $this->comment($this->option('fix')
            ? "Sudah di-backfill: {$salahHarian} baris menit_terlambat, {$salahBulanan} baris melebihi_toleransi_bulanan."
            : 'Ini dry-run — jalankan ulang dengan --fix setelah backup DB kalau angkanya masuk akal.');

        return self::SUCCESS;
    }
}
