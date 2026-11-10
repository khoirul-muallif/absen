<?php

namespace App\Support;

use App\Models\Karyawan;

/**
 * Rencana assignment sebelum ditulis — dipakai pratinjau DAN eksekusi.
 *
 * Satu sumber, dua pemakai: kalau pratinjau dan eksekusi menghitung
 * sendiri-sendiri, admin bisa melihat satu hal lalu mendapat hal lain.
 */
class RencanaAssignment
{
    /** @var array<int, array{karyawan:Karyawan, anchor:?string, posisi:?int}> */
    public array $siap = [];

    /** @var array<int, array{nama:string, alasan:string}> */
    public array $ditolak = [];

    public function tambahSiap(Karyawan $karyawan, ?string $anchor = null, ?int $posisi = null): void
    {
        $this->siap[] = ['karyawan' => $karyawan, 'anchor' => $anchor, 'posisi' => $posisi];
    }

    public function tolak(Karyawan $karyawan, string $alasan): void
    {
        $this->ditolak[] = ['nama' => $karyawan->nama, 'alasan' => $alasan];
    }

    public function adaYangBisaDitulis(): bool
    {
        return $this->siap !== [];
    }

    /** Berapa orang di tiap posisi siklus — inilah yang sebenarnya ingin dilihat admin. */
    public function distribusiPosisi(): array
    {
        $hitung = [];

        foreach ($this->siap as $baris) {
            if ($baris['posisi'] !== null) {
                $hitung[$baris['posisi']] = ($hitung[$baris['posisi']] ?? 0) + 1;
            }
        }

        ksort($hitung);

        return $hitung;
    }
}
