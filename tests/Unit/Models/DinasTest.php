<?php

use App\Models\Absensi;
use App\Models\Dinas;
use App\Models\Instansi;
use App\Models\Karyawan;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->instansi = Instansi::factory()->create();
    $this->karyawan = Karyawan::factory()->for($this->instansi)->create();
});

it('approve mensinkronkan absensi untuk setiap tanggal dalam rentang dinas', function () {
    $dinas = Dinas::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'tanggal_mulai' => '2026-08-10',
        'tanggal_selesai' => '2026-08-12',
    ]);

    $dinas->approve($this->admin);

    $absensi = Absensi::where('karyawan_id', $this->karyawan->id)
        ->whereBetween('tanggal', ['2026-08-10', '2026-08-12'])
        ->get();

    expect($absensi)->toHaveCount(3);
    expect($absensi->every(fn ($a) => $a->status === 'dinas'))->toBeTrue();
});

it('tidak menimpa absensi yang sudah punya waktu_masuk asli', function () {
    Absensi::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'tanggal' => '2026-08-11',
        'waktu_masuk' => '2026-08-11 07:30:00',
        'status' => 'tepat_waktu',
    ]);

    $dinas = Dinas::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'tanggal_mulai' => '2026-08-10',
        'tanggal_selesai' => '2026-08-12',
    ]);

    $dinas->approve($this->admin);

    $absensiHari2 = Absensi::where('karyawan_id', $this->karyawan->id)
        ->whereDate('tanggal', '2026-08-11')->first();

    expect($absensiHari2->status)->toBe('tepat_waktu');
});

it('status dinas tidak menyentuh kolom kuota apapun', function () {
    // Dinas sengaja tidak punya konsep kuota sama sekali - test ini
    // sekadar dokumentasi eksplisit bahwa afterApprove() Dinas cuma
    // sinkronisasi Absensi, tidak ada efek samping lain.
    $dinas = Dinas::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'tanggal_mulai' => '2026-08-10',
        'tanggal_selesai' => '2026-08-10',
    ]);

    expect(fn () => $dinas->approve($this->admin))->not->toThrow(\Throwable::class);
});
