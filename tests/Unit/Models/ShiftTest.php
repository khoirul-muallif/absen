<?php

use App\Models\Instansi;
use App\Models\Shift;
use Carbon\Carbon;

beforeEach(function () {
    $this->instansi = Instansi::factory()->create();
});

// ── tentukanStatus() ────────────────────────────────────────────────────
// Berdasar source code asli: status harian TIDAK pernah mempertimbangkan
// toleransi_menit. Begitu waktu_masuk > jam_masuk, langsung 'terlambat'.
// toleransi_menit cuma dipakai di sudahMelebihiToleransiBulanan() (KPI).

it('tepat waktu jika masuk persis di jam masuk shift', function () {
    $shift = Shift::factory()->for($this->instansi)->create([
        'jam_masuk' => '07:30:00',
        'toleransi_menit' => 15,
    ]);

    $status = $shift->tentukanStatus(Carbon::parse('2026-07-17 07:30:00'));

    expect($status)->toBe('tepat_waktu');
});

it('tepat waktu jika masuk lebih awal dari jam masuk shift', function () {
    $shift = Shift::factory()->for($this->instansi)->create([
        'jam_masuk' => '07:30:00',
        'toleransi_menit' => 15,
    ]);

    $status = $shift->tentukanStatus(Carbon::parse('2026-07-17 07:15:00'));

    expect($status)->toBe('tepat_waktu');
});

it('terlambat begitu lewat 1 menit dari jam masuk, walau masih dalam toleransi', function () {
    $shift = Shift::factory()->for($this->instansi)->create([
        'jam_masuk' => '07:30:00',
        'toleransi_menit' => 30, // toleransi besar, tapi TIDAK dipakai di status harian
    ]);

    $status = $shift->tentukanStatus(Carbon::parse('2026-07-17 07:31:00'));

    expect($status)->toBe('terlambat');
});

it('terlambat jauh melebihi jam masuk', function () {
    $shift = Shift::factory()->for($this->instansi)->create([
        'jam_masuk' => '07:30:00',
    ]);

    $status = $shift->tentukanStatus(Carbon::parse('2026-07-17 09:00:00'));

    expect($status)->toBe('terlambat');
});

it('mode akumulasi_bulanan tetap pakai logic status harian yang sama (tidak beda dari harian)', function () {
    $shift = Shift::factory()->for($this->instansi)->create([
        'jam_masuk' => '07:30:00',
        'toleransi_menit' => 30,
        'mode_toleransi' => 'akumulasi_bulanan',
    ]);

    $status = $shift->tentukanStatus(Carbon::parse('2026-07-17 07:35:00'));

    expect($status)->toBe('terlambat');
});

it('dataset: berbagai selisih menit selalu konsisten dengan aturan >0 = terlambat', function (int $menitSetelahJamMasuk, string $expected) {
    $shift = Shift::factory()->for($this->instansi)->create([
        'jam_masuk' => '07:30:00',
        'toleransi_menit' => 15,
    ]);

    $waktu = Carbon::parse('2026-07-17 07:30:00')->addMinutes($menitSetelahJamMasuk);

    expect($shift->tentukanStatus($waktu))->toBe($expected);
})->with([
    'tepat di jam masuk' => [0, 'tepat_waktu'],
    '1 menit lewat' => [1, 'terlambat'],
    '14 menit lewat (masih dalam toleransi_menit tapi tetap terlambat)' => [14, 'terlambat'],
    '15 menit lewat (pas toleransi_menit)' => [15, 'terlambat'],
    '60 menit lewat' => [60, 'terlambat'],
]);

// ── hitungMenitTerlambat() ──────────────────────────────────────────────

it('menghitung menit terlambat dengan benar', function () {
    $shift = Shift::factory()->for($this->instansi)->create([
        'jam_masuk' => '07:30:00',
    ]);

    $menit = $shift->hitungMenitTerlambat(Carbon::parse('2026-07-17 07:45:00'));

    expect($menit)->toBe(15);
});

it('menit terlambat tidak pernah negatif jika masuk lebih awal', function () {
    $shift = Shift::factory()->for($this->instansi)->create([
        'jam_masuk' => '07:30:00',
    ]);

    $menit = $shift->hitungMenitTerlambat(Carbon::parse('2026-07-17 07:00:00'));

    expect($menit)->toBe(0);
});

// ── sudahMelebihiToleransiBulanan() ─────────────────────────────────────
// Ini yang benar-benar mempertimbangkan toleransi_menit, tapi HANYA untuk
// mode_toleransi='akumulasi_bulanan'. Mode 'harian' selalu return false
// (KPI bulanan tidak relevan buat mode ini).

it('mode harian selalu false walau akumulasi menit sangat besar', function () {
    $shift = Shift::factory()->for($this->instansi)->create([
        'mode_toleransi' => 'harian',
        'toleransi_menit' => 30,
    ]);

    expect($shift->sudahMelebihiToleransiBulanan(1000))->toBeFalse();
});

it('mode akumulasi_bulanan: belum melebihi jika masih di bawah atau sama dengan toleransi', function () {
    $shift = Shift::factory()->for($this->instansi)->create([
        'mode_toleransi' => 'akumulasi_bulanan',
        'toleransi_menit' => 30,
    ]);

    expect($shift->sudahMelebihiToleransiBulanan(30))->toBeFalse()
        ->and($shift->sudahMelebihiToleransiBulanan(29))->toBeFalse();
});

it('mode akumulasi_bulanan: melebihi begitu lewat 1 menit dari toleransi', function () {
    $shift = Shift::factory()->for($this->instansi)->create([
        'mode_toleransi' => 'akumulasi_bulanan',
        'toleransi_menit' => 30,
    ]);

    expect($shift->sudahMelebihiToleransiBulanan(31))->toBeTrue();
});

// ── adalahHariKerja() ────────────────────────────────────────────────────

it('hari_kerja kosong dianggap kerja setiap hari (fallback aman)', function () {
    $shift = Shift::factory()->for($this->instansi)->create([
        'hari_kerja' => [],
    ]);

    // 2026-07-19 = Minggu (dayOfWeek 0)
    expect($shift->adalahHariKerja(Carbon::parse('2026-07-19')))->toBeTrue();
});

it('hari_kerja eksplisit membatasi hari tertentu saja', function () {
    $shift = Shift::factory()->for($this->instansi)->create([
        'hari_kerja' => [1, 2, 3, 4, 5], // Senin-Jumat
    ]);

    // 2026-07-17 = Jumat (dayOfWeek 5)
    expect($shift->adalahHariKerja(Carbon::parse('2026-07-17')))->toBeTrue();

    // 2026-07-18 = Sabtu (dayOfWeek 6)
    expect($shift->adalahHariKerja(Carbon::parse('2026-07-18')))->toBeFalse();
});
