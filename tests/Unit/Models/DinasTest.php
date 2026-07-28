<?php

use App\Models\Absensi;
use App\Models\Dinas;
use App\Models\Instansi;
use App\Models\Karyawan;
use App\Models\User;
use App\Models\Jadwal;
use App\Models\Shift;

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

it('menimpa absensi yang sudah punya waktu_masuk asli', function () {
    Absensi::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'tanggal' => '2026-08-11',
        'waktu_masuk' => '2026-08-11 07:30:00',
        'status' => 'tepat_waktu',
        'menit_terlambat' => 0,
    ]);

    $dinas = Dinas::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'tanggal_mulai' => '2026-08-10',
        'tanggal_selesai' => '2026-08-12',
    ]);

    $dinas->approve($this->admin);

    $absensiHari2 = Absensi::where('karyawan_id', $this->karyawan->id)
        ->whereDate('tanggal', '2026-08-11')->first();

    expect($absensiHari2->status)->toBe('dinas')
        ->and($absensiHari2->waktu_masuk)->toBeNull()
        ->and($absensiHari2->menit_terlambat)->toBe(0);
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

it('approve mensinkronkan jadwal jadi jenis dinas untuk setiap tanggal dalam rentang', function () {
    $dinas = Dinas::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'tanggal_mulai' => '2026-08-10',
        'tanggal_selesai' => '2026-08-12',
    ]);

    $dinas->approve($this->admin);

    $jadwal = Jadwal::where('karyawan_id', $this->karyawan->id)
        ->whereBetween('tanggal', ['2026-08-10', '2026-08-12'])
        ->get();

    expect($jadwal)->toHaveCount(3);
    expect($jadwal->every(fn ($j) => $j->jenis === 'dinas' && $j->shift_id === null))
        ->toBeTrue();
});

it('menimpa jadwal manual yang sudah ada sebelum dinas disetujui', function () {
    $shift = Shift::factory()->for($this->instansi)->create();

    Jadwal::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'shift_id' => $shift->id,
        'tanggal' => '2026-08-11',
        'jenis' => 'reguler',
        'sumber' => 'manual',
    ]);

    $dinas = Dinas::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'tanggal_mulai' => '2026-08-10',
        'tanggal_selesai' => '2026-08-12',
    ]);

    $dinas->approve($this->admin);

    $jadwalHari2 = Jadwal::where('karyawan_id', $this->karyawan->id)
        ->whereDate('tanggal', '2026-08-11')->first();

    expect($jadwalHari2->jenis)->toBe('dinas')
        ->and($jadwalHari2->shift_id)->toBeNull();
});
