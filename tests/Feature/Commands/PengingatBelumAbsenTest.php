<?php

use App\Models\Absensi;
use App\Models\Cuti;
use App\Models\Instansi;
use App\Models\Karyawan;
use App\Models\KaryawanShift;
use App\Models\Shift;
use App\Notifications\BelumAbsen;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->instansi = Instansi::factory()->create();
    $this->shift = Shift::factory()->create([
        'instansi_id'      => $this->instansi->id,
        'jam_masuk'        => '07:30',
        'jam_pulang'       => '16:00',
        'toleransi_menit'  => 15,
        'hari_kerja'       => [],   // kosong = kerja tiap hari
    ]);
});

// ── Pengingat masuk ─────────────────────────────────────────────────────

test('pengingat masuk belum terkirim 1 menit sebelum batas', function () {
    // batas = 07:30 + 15 toleransi + 15 grace = 08:00
    $this->travelTo(Carbon::parse('2026-07-17 07:59:00'));
    $karyawan = Karyawan::factory()->umum()->create(['instansi_id' => $this->instansi->id]);
    KaryawanShift::factory()->openEnded()->create([
        'karyawan_id' => $karyawan->id,
        'shift_id' => $this->shift->id,
        'tanggal_berlaku' => '2026-07-01',
    ]);

    Notification::fake();
    Artisan::call('absensi:pengingat-belum-absen');

    Notification::assertNotSentTo($karyawan, BelumAbsen::class);
});

test('pengingat masuk terkirim 1 menit setelah batas', function () {
    $this->travelTo(Carbon::parse('2026-07-17 08:01:00'));
    $karyawan = Karyawan::factory()->umum()->create(['instansi_id' => $this->instansi->id]);
    KaryawanShift::factory()->openEnded()->create([
        'karyawan_id' => $karyawan->id,
        'shift_id' => $this->shift->id,
        'tanggal_berlaku' => '2026-07-01',
    ]);

    Notification::fake();
    Artisan::call('absensi:pengingat-belum-absen');

    Notification::assertSentTo($karyawan, BelumAbsen::class);
});

test('pengingat masuk terkirim ke karyawan umum yang belum absen setelah batas', function () {
    $this->travelTo(Carbon::parse('2026-07-17 09:00:00'));
    $karyawan = Karyawan::factory()->umum()->create(['instansi_id' => $this->instansi->id]);
    KaryawanShift::factory()->openEnded()->create([
        'karyawan_id' => $karyawan->id,
        'shift_id' => $this->shift->id,
        'tanggal_berlaku' => '2026-07-01',
    ]);

    Notification::fake();
    Artisan::call('absensi:pengingat-belum-absen');

    Notification::assertSentTo($karyawan, BelumAbsen::class,
        fn ($n) => $n->jenisAbsen === 'masuk');
});

test('pengingat masuk terkirim ke karyawan rotasi yang punya Jadwal reguler', function () {
    $this->travelTo(Carbon::parse('2026-07-17 09:00:00'));
    $karyawan = Karyawan::factory()->rotasi()->create(['instansi_id' => $this->instansi->id]);
    \App\Models\Jadwal::create([
        'karyawan_id' => $karyawan->id,
        'tanggal'     => '2026-07-17',
        'shift_id'    => $this->shift->id,
        'jenis'       => 'reguler',
        'sumber'      => 'generate',
    ]);

    Notification::fake();
    Artisan::call('absensi:pengingat-belum-absen');

    Notification::assertSentTo($karyawan, BelumAbsen::class);
});

test('pengingat masuk TIDAK terkirim ke karyawan yang sedang cuti approved', function () {
    $this->travelTo(Carbon::parse('2026-07-17 09:00:00'));
    $karyawan = Karyawan::factory()->umum()->create(['instansi_id' => $this->instansi->id]);
    KaryawanShift::factory()->openEnded()->create([
        'karyawan_id' => $karyawan->id,
        'shift_id' => $this->shift->id,
        'tanggal_berlaku' => '2026-07-01',
    ]);
    Cuti::factory()->create([
        'karyawan_id'     => $karyawan->id,
        'tanggal_mulai'   => '2026-07-16',
        'tanggal_selesai' => '2026-07-18',
        'status'          => 'approved',
    ]);

    Notification::fake();
    Artisan::call('absensi:pengingat-belum-absen');

    Notification::assertNotSentTo($karyawan, BelumAbsen::class);
});

test('pengingat masuk TIDAK terkirim ke karyawan rotasi dengan Jadwal libur', function () {
    $this->travelTo(Carbon::parse('2026-07-17 09:00:00'));
    $karyawan = Karyawan::factory()->rotasi()->create(['instansi_id' => $this->instansi->id]);
    \App\Models\Jadwal::create([
        'karyawan_id' => $karyawan->id,
        'tanggal'     => '2026-07-17',
        'shift_id'    => null,
        'jenis'       => 'libur',
        'sumber'      => 'generate',
    ]);

    Notification::fake();
    Artisan::call('absensi:pengingat-belum-absen');

    Notification::assertNotSentTo($karyawan, BelumAbsen::class);
});

test('pengingat masuk TIDAK terkirim ke karyawan yang sudah absen masuk', function () {
    $this->travelTo(Carbon::parse('2026-07-17 09:00:00'));
    $karyawan = Karyawan::factory()->umum()->create(['instansi_id' => $this->instansi->id]);
    KaryawanShift::factory()->openEnded()->create([
        'karyawan_id' => $karyawan->id,
        'shift_id' => $this->shift->id,
        'tanggal_berlaku' => '2026-07-01',
    ]);
    Absensi::factory()->create([
        'karyawan_id' => $karyawan->id,
        'shift_id'    => $this->shift->id,
        'tanggal'     => '2026-07-17',
        'waktu_masuk' => '2026-07-17 07:35:00',
        'status'      => 'terlambat',
    ]);

    Notification::fake();
    Artisan::call('absensi:pengingat-belum-absen');

    Notification::assertNotSentTo($karyawan, BelumAbsen::class);
});

test('pengingat masuk hanya terkirim sekali walau command dijalankan dua kali', function () {
    $this->travelTo(Carbon::parse('2026-07-17 09:00:00'));
    $karyawan = Karyawan::factory()->umum()->create(['instansi_id' => $this->instansi->id]);
    KaryawanShift::factory()->openEnded()->create([
        'karyawan_id' => $karyawan->id,
        'shift_id' => $this->shift->id,
        'tanggal_berlaku' => '2026-07-01',
    ]);

    // Tanpa fake: dedupe membaca tabel notifications yang nyata
    Artisan::call('absensi:pengingat-belum-absen');
    Artisan::call('absensi:pengingat-belum-absen');

    expect($karyawan->notifications()->where('data->tipe', 'belum_absen_masuk')->count())->toBe(1);
});

// ── Pengingat pulang ────────────────────────────────────────────────────

test('pengingat pulang TIDAK terkirim sebelum jam pulang + 15 menit', function () {
    $this->travelTo(Carbon::parse('2026-07-17 16:10:00'));  // batas = 16:15
    $karyawan = Karyawan::factory()->umum()->create(['instansi_id' => $this->instansi->id]);
    Absensi::factory()->create([
        'karyawan_id' => $karyawan->id,
        'shift_id'    => $this->shift->id,
        'tanggal'     => '2026-07-17',
        'waktu_masuk' => '2026-07-17 07:30:00',
        'waktu_pulang' => null,
        'status'      => 'tepat_waktu',
    ]);

    Notification::fake();
    Artisan::call('absensi:pengingat-belum-pulang');

    Notification::assertNotSentTo($karyawan, BelumAbsen::class);
});

test('pengingat pulang terkirim setelah jam pulang + 15 menit', function () {
    $this->travelTo(Carbon::parse('2026-07-17 16:20:00'));
    $karyawan = Karyawan::factory()->umum()->create(['instansi_id' => $this->instansi->id]);
    Absensi::factory()->create([
        'karyawan_id' => $karyawan->id,
        'shift_id'    => $this->shift->id,
        'tanggal'     => '2026-07-17',
        'waktu_masuk' => '2026-07-17 07:30:00',
        'waktu_pulang' => null,
        'status'      => 'tepat_waktu',
    ]);

    Notification::fake();
    Artisan::call('absensi:pengingat-belum-pulang');

    Notification::assertSentTo($karyawan, BelumAbsen::class,
        fn ($n) => $n->jenisAbsen === 'pulang');
});

test('pengingat pulang shift malam: tidak terkirim sesaat setelah absen masuk', function () {
    $shiftMalam = Shift::factory()->create([
        'instansi_id'     => $this->instansi->id,
        'jam_masuk'       => '22:00',
        'jam_pulang'      => '07:00',
        'toleransi_menit' => 15,
        'hari_kerja'      => [],
    ]);
    $this->travelTo(Carbon::parse('2026-07-16 22:30:00'));   // masuk 22:05, belum saatnya pulang
    $karyawan = Karyawan::factory()->umum()->create(['instansi_id' => $this->instansi->id]);
    Absensi::factory()->create([
        'karyawan_id' => $karyawan->id,
        'shift_id'    => $shiftMalam->id,
        'tanggal'     => '2026-07-16',
        'waktu_masuk' => '2026-07-16 22:05:00',
        'waktu_pulang' => null,
        'status'      => 'tepat_waktu',
    ]);

    Notification::fake();
    Artisan::call('absensi:pengingat-belum-pulang');

    Notification::assertNotSentTo($karyawan, BelumAbsen::class);
});

test('pengingat pulang shift malam: terkirim setelah jam pulang dini hari + 15 menit', function () {
    $shiftMalam = Shift::factory()->create([
        'instansi_id'     => $this->instansi->id,
        'jam_masuk'       => '22:00',
        'jam_pulang'      => '07:00',
        'toleransi_menit' => 15,
        'hari_kerja'      => [],
    ]);
    $this->travelTo(Carbon::parse('2026-07-17 07:20:00'));   // batas = 07:15 hari berikutnya
    $karyawan = Karyawan::factory()->umum()->create(['instansi_id' => $this->instansi->id]);
    Absensi::factory()->create([
        'karyawan_id' => $karyawan->id,
        'shift_id'    => $shiftMalam->id,
        'tanggal'     => '2026-07-16',
        'waktu_masuk' => '2026-07-16 22:05:00',
        'waktu_pulang' => null,
        'status'      => 'tepat_waktu',
    ]);

    Notification::fake();
    Artisan::call('absensi:pengingat-belum-pulang');

    Notification::assertSentTo($karyawan, BelumAbsen::class,
        fn ($n) => $n->jenisAbsen === 'pulang');
});

test('pengingat pulang terkirim ke karyawan rotasi yang lupa absen pulang', function () {
    $this->travelTo(Carbon::parse('2026-07-17 16:20:00'));
    $karyawan = Karyawan::factory()->rotasi()->create(['instansi_id' => $this->instansi->id]);
    Absensi::factory()->create([
        'karyawan_id' => $karyawan->id,
        'shift_id'    => $this->shift->id,
        'tanggal'     => '2026-07-17',
        'waktu_masuk' => '2026-07-17 07:30:00',
        'waktu_pulang' => null,
        'status'      => 'tepat_waktu',
    ]);

    Notification::fake();
    Artisan::call('absensi:pengingat-belum-pulang');

    Notification::assertSentTo($karyawan, BelumAbsen::class);
});
