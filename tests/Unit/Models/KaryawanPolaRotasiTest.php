<?php

use App\Models\KaryawanPolaRotasi;
use App\Models\PolaRotasi;
use Carbon\Carbon;

function buatAssignment(array $langkah, string $tanggalMulai): KaryawanPolaRotasi
{
    $pola = new PolaRotasi(['langkah' => $langkah]);

    $assignment = new KaryawanPolaRotasi(['tanggal_mulai' => Carbon::parse($tanggalMulai)]);
    $assignment->setRelation('polaRotasi', $pola);

    return $assignment;
}

$langkah3Hari = [
    ['shift_id' => 1, 'libur' => false], // hari ke-0: pagi
    ['shift_id' => 2, 'libur' => false], // hari ke-1: malam
    ['shift_id' => null, 'libur' => true], // hari ke-2: libur
];

test('posisi di tanggal_mulai persis adalah 0', function () use ($langkah3Hari) {
    $a = buatAssignment($langkah3Hari, '2026-07-01');

    expect($a->posisiSiklusPada(Carbon::parse('2026-07-01')))->toBe(0);
});

test('posisi maju sesuai jarak hari dari tanggal_mulai', function () use ($langkah3Hari) {
    $a = buatAssignment($langkah3Hari, '2026-07-01');

    expect($a->posisiSiklusPada(Carbon::parse('2026-07-02')))->toBe(1)
        ->and($a->posisiSiklusPada(Carbon::parse('2026-07-03')))->toBe(2);
});

test('posisi wrap balik ke 0 pas nyampe panjang siklus penuh', function () use ($langkah3Hari) {
    $a = buatAssignment($langkah3Hari, '2026-07-01');

    // hari ke-3 (siklus ke-2, offset 0)
    expect($a->posisiSiklusPada(Carbon::parse('2026-07-04')))->toBe(0);
});

test('posisi tetap benar setelah beberapa siklus penuh terlewati', function () use ($langkah3Hari) {
    $a = buatAssignment($langkah3Hari, '2026-07-01');

    // 3 siklus penuh (9 hari) + offset 2 = 11 hari setelah tanggal_mulai
    expect($a->posisiSiklusPada(Carbon::parse('2026-07-12')))->toBe(2);
});

test('posisi tetap benar lintas akhir tahun', function () use ($langkah3Hari) {
    $a = buatAssignment($langkah3Hari, '2025-12-30');

    // 2025-12-30 -> 2026-01-02 = 3 hari = 1 siklus penuh, offset 0
    expect($a->posisiSiklusPada(Carbon::parse('2026-01-02')))->toBe(0);
});

test('siklus panjang 1 selalu balik ke posisi 0 (shift sama tiap hari)', function () {
    $a = buatAssignment(
        [['shift_id' => 5, 'libur' => false]],
        '2026-07-01'
    );

    expect($a->posisiSiklusPada(Carbon::parse('2026-07-01')))->toBe(0)
        ->and($a->posisiSiklusPada(Carbon::parse('2026-08-15')))->toBe(0);
});

test('dua karyawan staggered di pola sama menghasilkan posisi berbeda di tanggal sama', function () use ($langkah3Hari) {
    $karyawanA = buatAssignment($langkah3Hari, '2026-07-01'); // mulai hari-0
    $karyawanB = buatAssignment($langkah3Hari, '2026-07-02'); // mulai 1 hari setelah A

    $tanggalCek = Carbon::parse('2026-07-05');

    expect($karyawanA->posisiSiklusPada($tanggalCek))
        ->not->toBe($karyawanB->posisiSiklusPada($tanggalCek));
});

// ============================================================================
// TAMBAHAN untuk tests/Unit/Models/KaryawanPolaRotasiTest.php
//
// Tempel di akhir file. Helper buatAssignment() yang sudah ada dipakai ulang.
// ============================================================================

// ── Guard perhitungan siklus (fase 33) ───────────────────────────────────

test('melempar exception kalau pola tidak punya langkah sama sekali', function () {
    // Pola dengan langkah kosong memang mungkin terjadi lewat seeder atau
    // insert langsung — minItems(1) cuma berlaku di form (lihat fase 32).
    // Sebelumnya ini jadi `% 0` → DivisionByZeroError, dan yang memanggil
    // bukan cuma Filament tapi GenerateJadwalRotasi.
    $a = buatAssignment([], '2026-07-01');

    expect(fn () => $a->posisiSiklusPada(Carbon::parse('2026-07-05')))
        ->toThrow(LogicException::class);
});

test('posisi untuk tanggal SEBELUM anchor dihitung mundur, bukan sebagai jarak absolut', function () {
    // Siklus 3 hari, anchor 10 Juli. Tanggal 8 Juli = 2 hari SEBELUM anchor.
    //
    // Versi lama memakai diffInDays() tanpa argumen kedua, jadi nilainya
    // absolut: 2 % 3 = 2 — seolah assignment sudah berjalan 2 hari.
    // Sekarang selisihnya bertanda (-2) lalu dinormalisasi: ((-2 % 3) + 3) % 3 = 1.
    $a = buatAssignment(
        [
            ['shift_id' => 1, 'libur' => false],
            ['shift_id' => 2, 'libur' => false],
            ['shift_id' => null, 'libur' => true],
        ],
        '2026-07-10'
    );

    expect($a->posisiSiklusPada(Carbon::parse('2026-07-08')))->toBe(1)
        ->and($a->posisiSiklusPada(Carbon::parse('2026-07-09')))->toBe(2)
        ->and($a->posisiSiklusPada(Carbon::parse('2026-07-10')))->toBe(0);
});

// ── berlakuPada() ────────────────────────────────────────────────────────
//
// Pemanggil sebaiknya menyaring dengan ini dulu: posisi untuk tanggal di luar
// masa berlaku assignment memang tidak punya arti bisnis.

test('berlakuPada false sebelum tanggal_mulai', function () {
    $a = buatAssignment([['shift_id' => 1, 'libur' => false]], '2026-07-10');

    expect($a->berlakuPada(Carbon::parse('2026-07-09')))->toBeFalse()
        ->and($a->berlakuPada(Carbon::parse('2026-07-10')))->toBeTrue();
});

test('berlakuPada true tanpa batas kalau tanggal_berakhir null', function () {
    $a = buatAssignment([['shift_id' => 1, 'libur' => false]], '2026-07-10');

    expect($a->berlakuPada(Carbon::parse('2030-01-01')))->toBeTrue();
});

test('berlakuPada menghormati tanggal_berakhir', function () {
    $a = buatAssignment([['shift_id' => 1, 'libur' => false]], '2026-07-10');
    $a->tanggal_berakhir = Carbon::parse('2026-07-20');

    expect($a->berlakuPada(Carbon::parse('2026-07-20')))->toBeTrue()
        ->and($a->berlakuPada(Carbon::parse('2026-07-21')))->toBeFalse();
});
