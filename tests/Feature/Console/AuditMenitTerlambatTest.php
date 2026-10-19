<?php

use App\Models\Absensi;
use App\Models\Instansi;
use App\Models\Karyawan;
use App\Models\Shift;
use Carbon\Carbon;

beforeEach(function () {
    $this->instansi = Instansi::factory()->create();
});

test('dry-run (default, tanpa --fix) tidak mengubah data sama sekali', function () {
    $shift = Shift::factory()->create([
        'instansi_id' => $this->instansi->id,
        'mode_toleransi' => 'harian',
        'jam_masuk' => '07:00',
    ]);
    $karyawan = Karyawan::factory()->create(['instansi_id' => $this->instansi->id]);

    $absensi = Absensi::factory()->create([
        'karyawan_id' => $karyawan->id,
        'shift_id' => $shift->id,
        'tanggal' => '2026-01-10',
        'waktu_masuk' => '2026-01-10 07:15:00', // telat 15 menit
        'menit_terlambat' => 999,                // sengaja salah
        'melebihi_toleransi_bulanan' => true,    // sengaja salah
    ]);

    $this->artisan('absensi:audit-menit-terlambat')
        ->expectsOutputToContain('dry-run')
        ->assertSuccessful();

    expect($absensi->fresh())
        ->menit_terlambat->toBe(999)
        ->melebihi_toleransi_bulanan->toBeTrue();
});

test('--fix membetulkan menit_terlambat dan memastikan mode harian selalu melebihi=false', function () {
    $shift = Shift::factory()->create([
        'instansi_id' => $this->instansi->id,
        'mode_toleransi' => 'harian',
        'jam_masuk' => '07:00',
    ]);
    $karyawan = Karyawan::factory()->create(['instansi_id' => $this->instansi->id]);

    $absensi = Absensi::factory()->create([
        'karyawan_id' => $karyawan->id,
        'shift_id' => $shift->id,
        'tanggal' => '2026-01-10',
        'waktu_masuk' => '2026-01-10 07:15:00',
        'menit_terlambat' => 999,
        'melebihi_toleransi_bulanan' => true,
    ]);

    $this->artisan('absensi:audit-menit-terlambat --fix')->assertSuccessful();

    expect($absensi->fresh())
        ->menit_terlambat->toBe(15)
        ->melebihi_toleransi_bulanan->toBeFalse();
});

test('akumulasi bulanan: lewat toleransi di hari kedua, lalu reset saat ganti bulan', function () {
    $shift = Shift::factory()->create([
        'instansi_id' => $this->instansi->id,
        'mode_toleransi' => 'akumulasi_bulanan',
        'toleransi_menit' => 20,
        'jam_masuk' => '07:00',
    ]);
    $karyawan = Karyawan::factory()->create(['instansi_id' => $this->instansi->id]);

    $hari1 = Absensi::factory()->create([
        'karyawan_id' => $karyawan->id,
        'shift_id' => $shift->id,
        'tanggal' => '2026-01-10',
        'waktu_masuk' => '2026-01-10 07:15:00', // telat 15, akumulasi 15 (<=20)
        'menit_terlambat' => 0,
        'melebihi_toleransi_bulanan' => true, // sengaja salah
    ]);
    $hari2 = Absensi::factory()->create([
        'karyawan_id' => $karyawan->id,
        'shift_id' => $shift->id,
        'tanggal' => '2026-01-20',
        'waktu_masuk' => '2026-01-20 07:10:00', // telat 10, akumulasi 25 (>20)
        'menit_terlambat' => 0,
        'melebihi_toleransi_bulanan' => false, // sengaja salah
    ]);
    $bulanBaru = Absensi::factory()->create([
        'karyawan_id' => $karyawan->id,
        'shift_id' => $shift->id,
        'tanggal' => '2026-02-01',
        'waktu_masuk' => '2026-02-01 07:05:00', // telat 5, HARUS reset (bukan 15+10+5=30)
        'menit_terlambat' => 0,
        'melebihi_toleransi_bulanan' => false,
    ]);

    $this->artisan('absensi:audit-menit-terlambat --fix')->assertSuccessful();

    expect($hari1->fresh())->menit_terlambat->toBe(15)->melebihi_toleransi_bulanan->toBeFalse();
    expect($hari2->fresh())->menit_terlambat->toBe(10)->melebihi_toleransi_bulanan->toBeTrue();
    expect($bulanBaru->fresh())->menit_terlambat->toBe(5)->melebihi_toleransi_bulanan->toBeFalse();
});

test('baris tanpa shift (shift_id null) tidak ikut dihitung maupun disentuh', function () {
    $karyawan = Karyawan::factory()->create(['instansi_id' => $this->instansi->id]);

    $cuti = Absensi::factory()->create([
        'karyawan_id' => $karyawan->id,
        'shift_id' => null,
        'status' => 'cuti',
        'waktu_masuk' => null,
        'menit_terlambat' => 0,
        'melebihi_toleransi_bulanan' => false,
    ]);

    $this->artisan('absensi:audit-menit-terlambat --fix')
        ->expectsOutputToContain('Total data dicek: 0')
        ->assertSuccessful();

    expect($cuti->fresh()->menit_terlambat)->toBe(0);
});

test('filter --instansi membatasi audit ke satu instansi saja', function () {
    $instansiLain = Instansi::factory()->create();

    $shiftA = Shift::factory()->create(['instansi_id' => $this->instansi->id, 'mode_toleransi' => 'harian', 'jam_masuk' => '07:00']);
    $shiftB = Shift::factory()->create(['instansi_id' => $instansiLain->id, 'mode_toleransi' => 'harian', 'jam_masuk' => '07:00']);

    $karyawanA = Karyawan::factory()->create(['instansi_id' => $this->instansi->id]);
    $karyawanB = Karyawan::factory()->create(['instansi_id' => $instansiLain->id]);

    $absensiA = Absensi::factory()->create([
        'karyawan_id' => $karyawanA->id, 'shift_id' => $shiftA->id,
        'tanggal' => '2026-01-10', 'waktu_masuk' => '2026-01-10 07:15:00',
        'menit_terlambat' => 999,
    ]);
    $absensiB = Absensi::factory()->create([
        'karyawan_id' => $karyawanB->id, 'shift_id' => $shiftB->id,
        'tanggal' => '2026-01-10', 'waktu_masuk' => '2026-01-10 07:15:00',
        'menit_terlambat' => 999,
    ]);

    $this->artisan("absensi:audit-menit-terlambat --fix --instansi={$this->instansi->id}")->assertSuccessful();

    expect($absensiA->fresh()->menit_terlambat)->toBe(15);
    expect($absensiB->fresh()->menit_terlambat)->toBe(999); // instansi lain, tidak disentuh
});

test('--fix idempoten: run kedua tidak menemukan apa pun lagi', function () {
    $shift = Shift::factory()->create([
        'instansi_id' => $this->instansi->id,
        'mode_toleransi' => 'harian',
        'jam_masuk' => '07:00',
    ]);
    $karyawan = Karyawan::factory()->create(['instansi_id' => $this->instansi->id]);

    Absensi::factory()->create([
        'karyawan_id' => $karyawan->id,
        'shift_id' => $shift->id,
        'tanggal' => '2026-01-10',
        'waktu_masuk' => '2026-01-10 07:15:00',
        'menit_terlambat' => 999,
    ]);

    $this->artisan('absensi:audit-menit-terlambat --fix')->assertSuccessful();

    $this->artisan('absensi:audit-menit-terlambat --fix')
        ->expectsOutputToContain('menit_terlambat salah: 0')
        ->expectsOutputToContain('melebihi_toleransi_bulanan salah: 0')
        ->assertSuccessful();
});

test('akumulasi bulanan tetap benar tepat di batas chunk (baris ke-500 dan ke-501)', function () {
    $shiftHarian = Shift::factory()->create([
        'instansi_id' => $this->instansi->id,
        'mode_toleransi' => 'harian',
        'jam_masuk' => '07:00',
    ]);
    $shiftAkumulasi = Shift::factory()->create([
        'instansi_id' => $this->instansi->id,
        'mode_toleransi' => 'akumulasi_bulanan',
        'toleransi_menit' => 500,
        'jam_masuk' => '07:00',
    ]);
    $karyawan = Karyawan::factory()->create(['instansi_id' => $this->instansi->id]);

    // 499 baris pengisi: tepat waktu, shift harian, tidak pernah salah --
    // murni buat menggeser 2 baris target ke posisi 500 & 501, persis di
    // batas chunk(500) di command.
    Absensi::factory()
        ->count(499)
        ->sequence(fn ($seq) => [
            'tanggal' => Carbon::parse('2020-01-01')->addDays($seq->index),
            'waktu_masuk' => Carbon::parse('2020-01-01')->addDays($seq->index)->setTime(7, 0),
        ])
        ->create([
            'karyawan_id' => $karyawan->id,
            'shift_id' => $shiftHarian->id,
            'menit_terlambat' => 0,
            'melebihi_toleransi_bulanan' => false,
        ]);

    // Baris ke-500 (baris terakhir chunk pertama): akumulasi tepat 500,
    // belum lewat (500 > 500 salah).
    $rowBatas = Absensi::factory()->create([
        'karyawan_id' => $karyawan->id,
        'shift_id' => $shiftAkumulasi->id,
        'tanggal' => '2025-01-01',
        'waktu_masuk' => '2025-01-01 15:20:00', // 07:00 + 500 menit
        'menit_terlambat' => 0,
        'melebihi_toleransi_bulanan' => false,
    ]);

    // Baris ke-501 (baris PERTAMA chunk kedua). Kalau akumulasi ke-reset
    // keliru di batas chunk, baris ini akan salah hitung (akumulasi jadi 1,
    // bukan 501) dan melebihi_toleransi_bulanan jadi salah.
    $rowSetelahBatas = Absensi::factory()->create([
        'karyawan_id' => $karyawan->id,
        'shift_id' => $shiftAkumulasi->id,
        'tanggal' => '2025-01-02',
        'waktu_masuk' => '2025-01-02 07:01:00', // 07:00 + 1 menit
        'menit_terlambat' => 0,
        'melebihi_toleransi_bulanan' => false,
    ]);

    $this->artisan('absensi:audit-menit-terlambat --fix')->assertSuccessful();

    expect($rowBatas->fresh())
        ->menit_terlambat->toBe(500)
        ->melebihi_toleransi_bulanan->toBeFalse();

    expect($rowSetelahBatas->fresh())
        ->menit_terlambat->toBe(1)
        ->melebihi_toleransi_bulanan->toBeTrue();
});
