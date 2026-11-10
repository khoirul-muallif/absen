<?php

namespace App\Support;

/**
 * Pembawa hasil import karyawan — dipakai sama persis oleh dry-run dan
 * commit, supaya laporan pratinjau dan laporan hasil tidak pernah
 * menyimpang bentuknya.
 */
class HasilImportKaryawan
{
    /** @var array<int, array{baris:int, data:array}> */
    public array $siap = [];

    /** @var array<int, array{baris:int, nama:?string, pesan:array<string>}> */
    public array $ditolak = [];

    /** @var array<int, array{baris:int, pesan:string}> */
    public array $peringatan = [];

    public bool $tersimpan = false;

    public function tambahSiap(int $baris, array $data): void
    {
        $this->siap[] = ['baris' => $baris, 'data' => $data];
    }

    public function tambahDitolak(int $baris, ?string $nama, array $pesan): void
    {
        $this->ditolak[] = ['baris' => $baris, 'nama' => $nama, 'pesan' => $pesan];
    }

    public function tambahPeringatan(int $baris, string $pesan): void
    {
        $this->peringatan[] = ['baris' => $baris, 'pesan' => $pesan];
    }

    public function adaMasalah(): bool
    {
        return $this->ditolak !== [];
    }

    public function tidakAdaYangBisaDiimpor(): bool
    {
        return $this->siap === [];
    }

    public function ringkasan(): string
    {
        $bagian = [count($this->siap) . ' baris siap diimpor'];

        if ($this->ditolak !== []) {
            $bagian[] = count($this->ditolak) . ' ditolak';
        }

        if ($this->peringatan !== []) {
            $bagian[] = count($this->peringatan) . ' peringatan';
        }



        return implode(', ', $bagian) . '.';
    }
}
