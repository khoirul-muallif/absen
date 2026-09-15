<?php

namespace App\Filament\Resources\Absensis\Concerns;

use App\Models\Absensi;
use App\Models\Shift;
use Carbon\Carbon;

trait MenghitungKeterlambatan
{
    /**
     * Hitung ulang menit_terlambat, status, dan melebihi_toleransi_bulanan
     * dari waktu_masuk + shift.
     *
     * Dipakai oleh CreateAbsensi DAN EditAbsensi. Sebelumnya cuma ada di
     * Create — akibatnya admin yang mengubah waktu_masuk lewat Edit
     * meninggalkan ketiga kolom itu pada nilai lama, padahal helper text di
     * form menjanjikan "otomatis dihitung ulang".
     *
     * $kecualiAbsensiId WAJIB diisi saat edit: tanpa itu, menit_terlambat
     * milik record yang sedang diedit ikut terjumlah ke akumulasi bulanan
     * dan dihitung dua kali.
     */
    protected function hitungKolomKeterlambatan(array $data, ?int $kecualiAbsensiId = null): array
    {
        if (empty($data['waktu_masuk']) || empty($data['shift_id'])) {
            return $data;
        }

        $shift = Shift::find($data['shift_id']);

        if (! $shift) {
            return $data;
        }

        $waktuMasuk = Carbon::parse($data['waktu_masuk']);

        // Akumulasi bulanan dikelompokkan per kolom `tanggal`, jadi bulan yang
        // dipakai juga harus diambil dari `tanggal` — bukan dari waktu_masuk.
        // Versi lama mencampur keduanya (whereYear('tanggal', $waktuMasuk->year)),
        // yang salah ember begitu tanggal & waktu_masuk beda bulan.
        $tanggal = Carbon::parse($data['tanggal'] ?? $waktuMasuk);

        $menitTerlambatHariIni = $shift->hitungMenitTerlambat($waktuMasuk);

        $totalSebelumnya = 0;
        if ($shift->mode_toleransi === 'akumulasi_bulanan') {
            $totalSebelumnya = (int) Absensi::where('karyawan_id', $data['karyawan_id'])
                ->whereYear('tanggal', $tanggal->year)
                ->whereMonth('tanggal', $tanggal->month)
                ->when($kecualiAbsensiId, fn ($q) => $q->where('id', '!=', $kecualiAbsensiId))
                ->sum('menit_terlambat');
        }

        $data['menit_terlambat'] = $menitTerlambatHariIni;
        $data['status'] = $shift->tentukanStatus($waktuMasuk);
        $data['melebihi_toleransi_bulanan'] = $shift->sudahMelebihiToleransiBulanan(
            $totalSebelumnya + $menitTerlambatHariIni
        );

        return $data;
    }
}
