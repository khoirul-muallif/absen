<?php

use App\Models\HariLibur;
use App\Models\Instansi;
use App\Models\Jadwal;
use App\Models\Karyawan;
use App\Models\KaryawanPolaRotasi;
use App\Models\PolaRotasi;
use App\Models\Shift;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    $this->instansi = Instansi::factory()->create();
    $this->shift = Shift::factory()->create(['instansi_id' => $this->instansi->id]);

    // Pola 1 hari kerja, 1 hari libur, mulai 1 Juli 2026
    $this->pola = PolaRotasi::factory()->create([
        'instansi_id' => $this->instansi->id,
        'unit_kerja'  => 'IGD',
        'langkah'     => [
            ['shift_id' => $this->shift->id, 'libur' => false],
            ['shift_id' => null,             'libur' => true],
        ],
        'berlaku_saat_libur_nasional' => false, // supaya libur nasional berpengaruh
    ]);

    $this->karyawan = Karyawan::factory()->rotasi()->create([
        'instansi_id' => $this->instansi->id,
        'unit_kerja'  => 'IGD',
    ]);

    KaryawanPolaRotasi::factory()->create([
        'karyawan_id'      => $this->karyawan->id,
        'pola_rotasi_id'   => $this->pola->id,
        'tanggal_mulai'    => '2026-07-01',
        'tanggal_berakhir' => null,
    ]);
});

test('cuti bersama tidak mengubah hasil generator rotasi (hari kerja tetap piket)', function () {
    HariLibur::create([
        'instansi_id'     => $this->instansi->id,
        'tanggal'         => '2026-07-17',
        'nama'            => 'Cuti Bersama Uji',
        'is_cuti_bersama' => true,
    ]);

    Artisan::call('jadwal:generate-rotasi', ['bulan' => 7, 'tahun' => 2026]);

    // Posisi siklus 17 Juli dari anchor 1 Juli = (16 % 2) = 0 -> langkah kerja
    $jadwal = Jadwal::where('karyawan_id', $this->karyawan->id)
        ->whereDate('tanggal', '2026-07-17')
        ->first();

    expect($jadwal->jenis)->toBe('piket')
        ->and($jadwal->shift_id)->toBe($this->shift->id);
});

test('libur nasional tetap meng-override hari kerja saat pola tidak berlaku saat libur nasional', function () {
    HariLibur::create([
        'instansi_id'     => $this->instansi->id,
        'tanggal'         => '2026-07-17',
        'nama'            => 'Libur Nasional Uji',
        'is_cuti_bersama' => false,
    ]);

    Artisan::call('jadwal:generate-rotasi', ['bulan' => 7, 'tahun' => 2026]);

    $jadwal = Jadwal::where('karyawan_id', $this->karyawan->id)
        ->whereDate('tanggal', '2026-07-17')
        ->first();

    expect($jadwal->jenis)->toBe('libur');
});
