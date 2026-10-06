<?php

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

    $this->pola = PolaRotasi::factory()->create([
        'instansi_id' => $this->instansi->id,
        'unit_kerja'  => 'IGD',
        'langkah'     => [
            ['shift_id' => $this->shift->id, 'libur' => false],
            ['shift_id' => null,             'libur' => true],
        ],
        'berlaku_saat_libur_nasional' => true,
    ]);

    $this->karyawan = Karyawan::factory()->rotasi()->create([
        'instansi_id' => $this->instansi->id,
        'unit_kerja'  => 'IGD',
    ]);
});

test('tanggal sebelum tanggal_mulai assignment tidak menghasilkan jadwal', function () {
    // Assignment mulai 10 Juli, generate untuk Juli penuh
    KaryawanPolaRotasi::factory()->create([
        'karyawan_id'      => $this->karyawan->id,
        'pola_rotasi_id'   => $this->pola->id,
        'tanggal_mulai'    => '2026-07-10',
        'tanggal_berakhir' => null,
    ]);

    Artisan::call('jadwal:generate-rotasi', ['bulan' => 7, 'tahun' => 2026]);

    $sebelumMulai = Jadwal::where('karyawan_id', $this->karyawan->id)
        ->whereDate('tanggal', '<', '2026-07-10')
        ->count();

    expect($sebelumMulai)->toBe(0);
});

test('tanggal setelah tanggal_berakhir assignment tidak menghasilkan jadwal', function () {
    KaryawanPolaRotasi::factory()->create([
        'karyawan_id'      => $this->karyawan->id,
        'pola_rotasi_id'   => $this->pola->id,
        'tanggal_mulai'    => '2026-07-01',
        'tanggal_berakhir' => '2026-07-15',
    ]);

    Artisan::call('jadwal:generate-rotasi', ['bulan' => 7, 'tahun' => 2026]);

    $setelahBerakhir = Jadwal::where('karyawan_id', $this->karyawan->id)
        ->whereDate('tanggal', '>', '2026-07-15')
        ->count();

    expect($setelahBerakhir)->toBe(0);
});

test('tanggal dalam masa berlaku menghasilkan jadwal', function () {
    KaryawanPolaRotasi::factory()->create([
        'karyawan_id'      => $this->karyawan->id,
        'pola_rotasi_id'   => $this->pola->id,
        'tanggal_mulai'    => '2026-07-10',
        'tanggal_berakhir' => '2026-07-20',
    ]);

    Artisan::call('jadwal:generate-rotasi', ['bulan' => 7, 'tahun' => 2026]);

    $dalamMasaBerlaku = Jadwal::where('karyawan_id', $this->karyawan->id)
        ->whereBetween('tanggal', ['2026-07-10', '2026-07-20'])
        ->count();

    expect($dalamMasaBerlaku)->toBe(11);
});
