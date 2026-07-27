<?php

use App\Models\Absensi;
use App\Models\Instansi;
use App\Models\Izin;
use App\Models\Karyawan;
use App\Models\Lembur;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->instansi = Instansi::factory()->create();
    $this->karyawan = Karyawan::factory()->for($this->instansi)->create();
});

it('approve izin tidak menyentuh tabel absensi sama sekali', function () {
    // Karyawan sudah absen fisik normal hari itu
    $absensi = Absensi::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'tanggal' => '2026-08-05',
        'waktu_masuk' => '2026-08-05 07:30:00',
        'status' => 'tepat_waktu',
        'menit_terlambat' => 0,
    ]);

    $izin = Izin::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'tanggal' => '2026-08-05',
        'jam_keluar' => '10:00:00',
        'jam_kembali' => '12:00:00',
    ]);

    $izin->approve($this->admin);

    $absensi->refresh();

    // Sengaja: Izin tidak sync ke Absensi sama sekali (keputusan bisnis,
    // bisa beda per perusahaan) - status & waktu_masuk tetap seperti semula
    expect($absensi->status)->toBe('tepat_waktu')
        ->and($absensi->waktu_masuk->format('H:i:s'))->toBe('07:30:00');
});

it('approve izin tidak membuat row absensi baru kalau belum ada', function () {
    $izin = Izin::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'tanggal' => '2026-08-06',
        'jam_keluar' => '10:00:00',
        'jam_kembali' => '12:00:00',
    ]);

    $izin->approve($this->admin);

    $absensi = Absensi::where('karyawan_id', $this->karyawan->id)
        ->whereDate('tanggal', '2026-08-06')
        ->first();

    expect($absensi)->toBeNull();
});

it('approve lembur tidak menyentuh tabel absensi sama sekali', function () {
    $absensi = Absensi::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'tanggal' => '2026-08-05',
        'waktu_masuk' => '2026-08-05 07:30:00',
        'waktu_pulang' => '2026-08-05 16:00:00',
        'status' => 'tepat_waktu',
        'menit_terlambat' => 0,
    ]);

    $lembur = Lembur::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'tanggal' => '2026-08-05',
        'jam_mulai' => '17:00:00',
        'jam_selesai' => '20:00:00',
    ]);

    $lembur->approve($this->admin);

    $absensi->refresh();

    // Sengaja: Lembur tidak sync ke Absensi - waktu_pulang tidak di-extend
    // otomatis, murni catatan jam kerja tambahan terpisah
    expect($absensi->waktu_pulang->format('H:i:s'))->toBe('16:00:00');
});

it('approve lembur tidak membuat row absensi baru kalau belum ada', function () {
    $lembur = Lembur::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'tanggal' => '2026-08-07',
        'jam_mulai' => '17:00:00',
        'jam_selesai' => '20:00:00',
    ]);

    $lembur->approve($this->admin);

    $absensi = Absensi::where('karyawan_id', $this->karyawan->id)
        ->whereDate('tanggal', '2026-08-07')
        ->first();

    expect($absensi)->toBeNull();
});

it('izin bisa diajukan dan di-approve untuk tanggal yang absensinya sudah berstatus dinas/cuti', function () {
    // Sengaja tidak ada guard silang di sini - Izin/Lembur murni catatan
    // independen, tidak perlu tahu-menahu status Absensi di tanggal itu
    Absensi::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'tanggal' => '2026-08-08',
        'status' => 'dinas',
        'waktu_masuk' => null,
    ]);

    $izin = Izin::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'tanggal' => '2026-08-08',
        'jam_keluar' => '10:00:00',
        'jam_kembali' => '12:00:00',
    ]);

    expect(fn () => $izin->approve($this->admin))->not->toThrow(\Throwable::class);

    $absensi = Absensi::where('karyawan_id', $this->karyawan->id)
        ->whereDate('tanggal', '2026-08-08')->first();

    expect($absensi->status)->toBe('dinas'); // tidak berubah
});
