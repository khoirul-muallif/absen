<?php

use App\Models\Absensi;
use App\Models\HariLibur;
use App\Models\Instansi;
use App\Models\Jadwal;
use App\Models\Karyawan;
use App\Models\KaryawanShift;
use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    $this->instansi = Instansi::factory()->create();
    $this->shift = Shift::factory()->create([
        'instansi_id' => $this->instansi->id,
        'hari_kerja'  => [],
    ]);
    $this->karyawan = Karyawan::factory()->umum()->create(['instansi_id' => $this->instansi->id]);
    KaryawanShift::factory()->openEnded()->create([
        'karyawan_id'     => $this->karyawan->id,
        'shift_id'        => $this->shift->id,
        'tanggal_berlaku' => '2026-07-01',
    ]);
    HariLibur::create([
        'instansi_id'     => $this->instansi->id,
        'tanggal'         => '2026-07-17',
        'nama'            => 'Cuti Bersama Uji',
        'is_cuti_bersama' => true,
    ]);
});

test('shiftYangDiharapkanPada mengembalikan shift pada tanggal cuti bersama', function () {
    $shift = $this->karyawan->shiftYangDiharapkanPada(Carbon::parse('2026-07-17'));

    expect($shift)->not->toBeNull()
        ->and($shift->id)->toBe($this->shift->id);
});

test('rekap harian: cuti bersama tidak meliburkan, karyawan yang tidak absen jadi alpha', function () {
    Artisan::call('absensi:rekap-harian', ['--tanggal' => '2026-07-17']);

    $absensi = Absensi::where('karyawan_id', $this->karyawan->id)
        ->whereDate('tanggal', '2026-07-17')
        ->first();

    expect($absensi)->not->toBeNull()
        ->and($absensi->status)->toBe('alpha');
});

test('generate bulanan: cuti bersama tetap menghasilkan jadwal reguler, bukan libur', function () {
    Artisan::call('jadwal:generate-bulanan', ['--bulan' => 7, '--tahun' => 2026]);

    $jadwal = Jadwal::where('karyawan_id', $this->karyawan->id)
        ->whereDate('tanggal', '2026-07-17')
        ->first();

    expect($jadwal)->not->toBeNull()
        ->and($jadwal->jenis)->toBe(Jadwal::JENIS_REGULER);
});

test('libur nasional (bukan cuti bersama) tetap menghasilkan jadwal libur', function () {
    HariLibur::create([
        'instansi_id'     => $this->instansi->id,
        'tanggal'         => '2026-07-20',
        'nama'            => 'Libur Nasional Uji',
        'is_cuti_bersama' => false,
    ]);

    Artisan::call('jadwal:generate-bulanan', ['--bulan' => 7, '--tahun' => 2026]);

    $jadwal = Jadwal::where('karyawan_id', $this->karyawan->id)
        ->whereDate('tanggal', '2026-07-20')
        ->first();

    expect($jadwal->jenis)->toBe(Jadwal::JENIS_LIBUR);
});
