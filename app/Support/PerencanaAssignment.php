<?php

namespace App\Support;

use App\Models\Karyawan;
use App\Models\PolaRotasi;
use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PerencanaAssignment
{
    /**
     * Assignment shift periode untuk karyawan umum.
     *
     * Karyawan yang sudah punya KaryawanShift aktif DITOLAK, tidak ditimpa
     * dan tidak ditutup otomatis. Bulk action yang mengubah riwayat 40 orang
     * sekaligus terlalu banyak konsekuensi untuk fitur yang dipakai beberapa
     * kali seumur hidup aplikasi; admin melepas yang lama manual dulu.
     */
    public static function umum(Collection $karyawans, Shift $shift, Carbon $tanggalBerlaku): RencanaAssignment
    {
        $rencana = new RencanaAssignment();

        foreach ($karyawans as $karyawan) {
            if (! $karyawan->isUmum()) {
                $rencana->tolak($karyawan, 'Tipe jadwalnya rotasi, bukan umum.');
                continue;
            }

            if ($karyawan->instansi_id !== $shift->instansi_id) {
                // Dua instansi bisa sama-sama punya shift bernama "Pagi".
                $rencana->tolak($karyawan, 'Shift ini milik instansi lain.');
                continue;
            }

            if ($karyawan->shiftAktif()->exists()) {
                $rencana->tolak($karyawan, 'Sudah punya penugasan shift aktif. Lepas dulu yang lama.');
                continue;
            }

            // tanggal_bergabung dipakai kalau ada dan lebih lambat dari
            // tanggal yang dipilih — assignment tidak boleh berlaku sebelum
            // orangnya bekerja.
            $berlaku = $karyawan->tanggal_bergabung && $karyawan->tanggal_bergabung->gt($tanggalBerlaku)
                ? $karyawan->tanggal_bergabung->copy()
                : $tanggalBerlaku->copy();

            $rencana->tambahSiap($karyawan, $berlaku->toDateString());
        }

        return $rencana;
    }

    /**
     * Assignment pola rotasi dengan anchor yang di-stagger.
     *
     * ARAH STAGGER: anchor DIMUNDURKAN (subDays), bukan dimajukan.
     * KaryawanPolaRotasi::posisiSiklusPada() menghitung
     * diffInDays(tanggal_mulai -> tanggal), jadi anchor lebih awal = posisi
     * lebih maju. Kalau dimajukan, berlakuPada() menolak orang ke-n selama
     * n hari pertama dan generator melewatinya diam-diam.
     *
     * Urutan stagger memakai id: deterministik, dan untuk karyawan hasil
     * import berarti urutan baris di file Excel.
     */
    public static function rotasi(Collection $karyawans, PolaRotasi $pola, Carbon $tanggalMulai): RencanaAssignment
    {
        $rencana = new RencanaAssignment();
        $panjang = $pola->panjangSiklus();

        if ($panjang === 0) {
            // Pola tanpa langkah mungkin terjadi lewat seeder/insert langsung
            // (minItems(1) cuma berlaku di form). Tanpa guard ini, modulo nol
            // melempar DivisionByZeroError.
            foreach ($karyawans as $karyawan) {
                $rencana->tolak($karyawan, 'Pola rotasi ini tidak punya langkah sama sekali.');
            }

            return $rencana;
        }

        $urutan = 0;

        foreach ($karyawans->sortBy('id') as $karyawan) {
            if (! $karyawan->isRotasi()) {
                $rencana->tolak($karyawan, 'Tipe jadwalnya umum, bukan rotasi.');
                continue;
            }

            if ($karyawan->instansi_id !== $pola->instansi_id) {
                $rencana->tolak($karyawan, 'Pola ini milik instansi lain.');
                continue;
            }

            // Fase 33: pola cuma bisa di-assign kalau unit_kerja SAMA PERSIS.
            // Termasuk kapitalisasi — "igd" bukan "IGD".
            if ($karyawan->unit_kerja !== $pola->unit_kerja) {
                $rencana->tolak($karyawan, sprintf(
                    'Unit kerjanya "%s", pola ini untuk "%s".',
                    $karyawan->unit_kerja ?? '(kosong)',
                    $pola->unit_kerja,
                ));
                continue;
            }

            if ($karyawan->karyawanPolaRotasis()->whereNull('tanggal_berakhir')->exists()) {
                $rencana->tolak($karyawan, 'Sudah punya assignment pola aktif. Lepas dulu yang lama.');
                continue;
            }

            $posisi = $urutan % $panjang;
            $anchor = $tanggalMulai->copy()->subDays($posisi);

            $rencana->tambahSiap($karyawan, $anchor->toDateString(), $posisi);
            $urutan++;
        }

        return $rencana;
    }
}
