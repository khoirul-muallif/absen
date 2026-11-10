<?php

// Fase 85. Field `tipe` dan `aksi` dipakai FE untuk memilih ikon, warna, dan
// tujuan tap — tapi tidak ada satu pun yang menguncinya. Helper
// buatNotifikasi() di test lain memakai 'tipe' => 'warning', nilai yang tidak
// pernah ada di produksi, jadi test selama ini justru menguji yang salah.
//
// File ini sengaja mengumpulkan SEMUA nilai di satu tempat: daftar lengkapnya
// adalah kontrak dengan FE, dan nilai yang tidak dikenal NotifikasiScreen
// berarti ikon kosong atau crash.

use App\Models\Absensi;
use App\Models\Karyawan;
use App\Models\TukarJadwal;
use App\Notifications\AbsenTerlambat;
use App\Notifications\BelumAbsen;
use App\Notifications\TukarJadwalDiajukan;
use App\Notifications\TukarJadwalDibatalkanPengaju;
use App\Notifications\TukarJadwalDiproses;
use App\Notifications\TukarJadwalDiresponRekan;
use Illuminate\Support\Carbon;

// ── tipe ─────────────────────────────────────────────────────────────────

it('AbsenTerlambat mengirim tipe terlambat', function () {
    $karyawan = Karyawan::factory()->create();
    $absensi = Absensi::factory()->create([
        'karyawan_id' => $karyawan->id,
        'waktu_masuk' => now(),
    ]);

    $data = (new AbsenTerlambat($absensi->load('shift'), 15))->toDatabase($karyawan);

    expect($data['tipe'])->toBe('terlambat');
});

it('BelumAbsen mengirim tipe sesuai jenis absennya', function (string $jenis, string $tipe) {
    // `tipe` dibangun dengan interpolasi dari $jenisAbsen, yang cuma
    // didokumentasikan lewat komentar — tidak ada enum, tidak ada validasi.
    // Dua-duanya dikunci eksplisit, bukan satu lalu menganggap yang lain beres.
    $karyawan = Karyawan::factory()->create();

    $data = (new BelumAbsen($jenis, 'Pagi', '07:00', Carbon::parse('2026-11-10')))
        ->toDatabase($karyawan);

    expect($data['tipe'])->toBe($tipe);
})->with([
    ['masuk', 'belum_absen_masuk'],
    ['pulang', 'belum_absen_pulang'],
]);

it('semua notifikasi TukarJadwal mengirim tipe tukar_jadwal', function () {
    // Keempatnya memakai tipe yang SAMA; yang membedakan ada di `aksi`.
    // Kalau salah satu suatu saat memakai tipe sendiri, FE yang cuma punya
    // case 'tukar_jadwal' akan kehilangan ikonnya tanpa ada yang tahu.
    $karyawan = Karyawan::factory()->create();
    $tukar = TukarJadwal::factory()->create();

    $notifikasi = [
        new TukarJadwalDiajukan($tukar),
        new TukarJadwalDiproses($tukar, TukarJadwalDiproses::HASIL_APPROVED),
        new TukarJadwalDiresponRekan($tukar, true),
        new TukarJadwalDibatalkanPengaju($tukar, 'menunggu_rekan'),
    ];

    foreach ($notifikasi as $n) {
        expect($n->toDatabase($karyawan)['tipe'])->toBe('tukar_jadwal');
    }
});

// ── aksi ─────────────────────────────────────────────────────────────────
//
// Lebih berkonsekuensi daripada `tipe` untuk FE, dan tidak tercatat di mana
// pun. Tanpa `aksi`, "disetujui admin" dan "ditolak admin" tampil identik —
// dua hal yang berlawanan arti.

it('TukarJadwalDiajukan mengirim aksi diajukan', function () {
    $karyawan = Karyawan::factory()->create();
    $tukar = TukarJadwal::factory()->create();

    expect((new TukarJadwalDiajukan($tukar))->toDatabase($karyawan)['aksi'])
        ->toBe('diajukan');
});

it('TukarJadwalDiproses mengirim aksi sesuai hasilnya', function (string $hasil) {
    $karyawan = Karyawan::factory()->create();
    $tukar = TukarJadwal::factory()->create();

    expect((new TukarJadwalDiproses($tukar, $hasil))->toDatabase($karyawan)['aksi'])
        ->toBe($hasil);
})->with([
    TukarJadwalDiproses::HASIL_APPROVED,
    TukarJadwalDiproses::HASIL_REJECTED,
    TukarJadwalDiproses::HASIL_DIBATALKAN_ADMIN,
]);

it('TukarJadwalDiresponRekan membedakan setuju dan tolak lewat aksi', function (bool $setuju, string $aksi) {
    $karyawan = Karyawan::factory()->create();
    $tukar = TukarJadwal::factory()->create();

    expect((new TukarJadwalDiresponRekan($tukar, $setuju))->toDatabase($karyawan)['aksi'])
        ->toBe($aksi);
})->with([
    [true, 'disetujui_rekan'],
    [false, 'ditolak_rekan'],
]);

it('TukarJadwalDibatalkanPengaju mengirim aksi dibatalkan_pengaju', function () {
    $karyawan = Karyawan::factory()->create();
    $tukar = TukarJadwal::factory()->create();

    expect((new TukarJadwalDibatalkanPengaju($tukar, 'menunggu_rekan'))
        ->toDatabase($karyawan)['aksi'])->toBe('dibatalkan_pengaju');
});

// ── Daftar lengkap: kontrak dengan FE ────────────────────────────────────

it('kumpulan nilai tipe yang bisa sampai ke FE tidak bertambah diam-diam', function () {
    // Test ini SENGAJA rapuh. Kalau ada notifikasi baru dengan tipe baru,
    // dia merah — dan itu yang diinginkan: nilai yang tidak dikenal
    // NotifikasiScreen berarti ikon kosong atau crash di HP 150 orang.
    // Kalau merah, tambahkan nilainya DI SINI dan di todo-fr.md sekaligus.
    $karyawan = Karyawan::factory()->create();
    $tukar = TukarJadwal::factory()->create();
    $absensi = Absensi::factory()->create([
        'karyawan_id' => $karyawan->id,
        'waktu_masuk' => now(),
    ]);

    $semua = collect([
        new AbsenTerlambat($absensi->load('shift'), 15),
        new BelumAbsen('masuk', 'Pagi', '07:00', Carbon::parse('2026-11-10')),
        new BelumAbsen('pulang', 'Pagi', '15:00', Carbon::parse('2026-11-10')),
        new TukarJadwalDiajukan($tukar),
        new TukarJadwalDiproses($tukar, TukarJadwalDiproses::HASIL_APPROVED),
        new TukarJadwalDiresponRekan($tukar, true),
        new TukarJadwalDibatalkanPengaju($tukar, 'menunggu_rekan'),
    ])->map(fn ($n) => $n->toDatabase($karyawan)['tipe'])->unique()->sort()->values();

    expect($semua->all())->toBe([
        'belum_absen_masuk',
        'belum_absen_pulang',
        'terlambat',
        'tukar_jadwal',
    ]);
});

it('setiap notifikasi selalu mengirim judul, pesan dan tipe', function () {
    // NotifikasiController generik — dia meneruskan apa adanya. Kalau salah
    // satu field ini hilang, FE menampilkan kartu kosong tanpa ada yang tahu.
    $karyawan = Karyawan::factory()->create();
    $tukar = TukarJadwal::factory()->create();
    $absensi = Absensi::factory()->create([
        'karyawan_id' => $karyawan->id,
        'waktu_masuk' => now(),
    ]);

    $semua = [
        new AbsenTerlambat($absensi->load('shift'), 15),
        new BelumAbsen('masuk', 'Pagi', '07:00', Carbon::parse('2026-11-10')),
        new TukarJadwalDiajukan($tukar),
        new TukarJadwalDiproses($tukar, TukarJadwalDiproses::HASIL_REJECTED),
        new TukarJadwalDiresponRekan($tukar, false),
        new TukarJadwalDibatalkanPengaju($tukar, 'menunggu_admin'),
    ];

    foreach ($semua as $n) {
        $data = $n->toDatabase($karyawan);

        expect($data)->toHaveKeys(['judul', 'pesan', 'tipe'])
            ->and($data['judul'])->not->toBeEmpty()
            ->and($data['pesan'])->not->toBeEmpty();
    }
});
