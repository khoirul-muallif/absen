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
