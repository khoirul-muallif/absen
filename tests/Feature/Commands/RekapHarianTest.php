<?php

use App\Models\Absensi;
use App\Models\Cuti;
use App\Models\HariLibur;
use App\Models\Instansi;
use App\Models\Jadwal;
use App\Models\Karyawan;
use App\Models\KaryawanShift;
use App\Models\QrInstansi;
use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;

function jalankanRekap(string $tanggal): void
{
    Artisan::call('absensi:rekap-harian', ['--tanggal' => $tanggal]);
}

beforeEach(function () {
    $this->instansi = Instansi::factory()->create();
});

test('karyawan yang sudah absen dilewati, tidak dibuat record baru', function () {
    $karyawan = Karyawan::factory()->umum()->create(['instansi_id' => $this->instansi->id]);
    Absensi::factory()->create([
        'karyawan_id' => $karyawan->id,
        'tanggal' => '2026-07-17',
        'status' => 'tepat_waktu',
    ]);

    jalankanRekap('2026-07-17');

    expect(Absensi::where('karyawan_id', $karyawan->id)->whereDate('tanggal', '2026-07-17')->count())->toBe(1);
});

test('karyawan umum tanpa KaryawanShift & tanpa Jadwal dianggap libur mingguan, tidak dibuat absensi', function () {
    $karyawan = Karyawan::factory()->umum()->create(['instansi_id' => $this->instansi->id]);

    jalankanRekap('2026-07-17');

    expect(Absensi::where('karyawan_id', $karyawan->id)->exists())->toBeFalse();
});

test('karyawan umum dengan shift yang bukan hari kerjanya dianggap libur mingguan', function () {
    $shift = Shift::factory()->create([
        'instansi_id' => $this->instansi->id,
        'hari_kerja' => [1, 2, 3, 4, 5], // Senin-Jumat
    ]);
    $karyawan = Karyawan::factory()->umum()->create(['instansi_id' => $this->instansi->id]);
    KaryawanShift::factory()->openEnded()->create([
        'karyawan_id' => $karyawan->id,
        'shift_id' => $shift->id,
        'tanggal_berlaku' => '2026-07-01',
    ]);

    // 2026-07-18 = Sabtu, bukan hari kerja shift ini
    jalankanRekap('2026-07-18');

    expect(Absensi::where('karyawan_id', $karyawan->id)->exists())->toBeFalse();
});

test('karyawan umum dijadwalkan kerja tapi tidak absen jadi alpha', function () {
    $shift = Shift::factory()->create(['instansi_id' => $this->instansi->id, 'hari_kerja' => []]);
    $karyawan = Karyawan::factory()->umum()->create(['instansi_id' => $this->instansi->id]);
    KaryawanShift::factory()->openEnded()->create([
        'karyawan_id' => $karyawan->id,
        'shift_id' => $shift->id,
        'tanggal_berlaku' => '2026-07-01',
    ]);

    jalankanRekap('2026-07-17');

    $absensi = Absensi::where('karyawan_id', $karyawan->id)->whereDate('tanggal', '2026-07-17')->first();
    expect($absensi)->not->toBeNull()
        ->and($absensi->status)->toBe('alpha')
        ->and($absensi->shift_id)->toBe($shift->id);
});

test('alpha tetap mengambil qr_instansi_id aktif milik instansi karyawan', function () {
    $shift = Shift::factory()->create(['instansi_id' => $this->instansi->id, 'hari_kerja' => []]);
    $karyawan = Karyawan::factory()->umum()->create(['instansi_id' => $this->instansi->id]);
    KaryawanShift::factory()->openEnded()->create([
        'karyawan_id' => $karyawan->id,
        'shift_id' => $shift->id,
        'tanggal_berlaku' => '2026-07-01',
    ]);
    $qr = QrInstansi::factory()->create(['instansi_id' => $this->instansi->id, 'is_active' => true]);

    jalankanRekap('2026-07-17');

    $absensi = Absensi::where('karyawan_id', $karyawan->id)->whereDate('tanggal', '2026-07-17')->first();
    expect($absensi->qr_instansi_id)->toBe($qr->id);
});

test('hari libur nasional membuat Absensi status libur, bukan alpha', function () {
    $shift = Shift::factory()->create(['instansi_id' => $this->instansi->id, 'hari_kerja' => []]);
    $karyawan = Karyawan::factory()->umum()->create(['instansi_id' => $this->instansi->id]);
    KaryawanShift::factory()->openEnded()->create([
        'karyawan_id' => $karyawan->id,
        'shift_id' => $shift->id,
        'tanggal_berlaku' => '2026-07-01',
    ]);
    HariLibur::create([
        'instansi_id' => $this->instansi->id,
        'tanggal' => '2026-07-17',
        'nama' => 'Libur Uji',
        'is_cuti_bersama' => false,
    ]);

    jalankanRekap('2026-07-17');

    $absensi = Absensi::where('karyawan_id', $karyawan->id)->whereDate('tanggal', '2026-07-17')->first();
    expect($absensi->status)->toBe('libur');
});

test('karyawan rotasi tanpa Jadwal tercatat dianggap jadwal_hilang, bukan alpha', function () {
    $karyawan = Karyawan::factory()->rotasi()->create(['instansi_id' => $this->instansi->id]);

    jalankanRekap('2026-07-17');

    expect(Absensi::where('karyawan_id', $karyawan->id)->exists())->toBeFalse();
});

test('karyawan rotasi dengan Jadwal jenis libur dilewati sebagai libur personal', function () {
    $karyawan = Karyawan::factory()->rotasi()->create(['instansi_id' => $this->instansi->id]);
    Jadwal::create([
        'karyawan_id' => $karyawan->id,
        'tanggal' => '2026-07-17',
        'shift_id' => null,
        'jenis' => 'libur',
        'sumber' => 'generate',
    ]);

    jalankanRekap('2026-07-17');

    expect(Absensi::where('karyawan_id', $karyawan->id)->exists())->toBeFalse();
});

test('karyawan rotasi dengan Jadwal jenis reguler tapi tidak absen jadi alpha dengan shift dari Jadwal', function () {
    $shift = Shift::factory()->create(['instansi_id' => $this->instansi->id]);
    $karyawan = Karyawan::factory()->rotasi()->create(['instansi_id' => $this->instansi->id]);
    Jadwal::create([
        'karyawan_id' => $karyawan->id,
        'tanggal' => '2026-07-17',
        'shift_id' => $shift->id,
        'jenis' => 'reguler',
        'sumber' => 'generate',
    ]);

    jalankanRekap('2026-07-17');

    $absensi = Absensi::where('karyawan_id', $karyawan->id)->whereDate('tanggal', '2026-07-17')->first();
    expect($absensi->status)->toBe('alpha')->and($absensi->shift_id)->toBe($shift->id);
});

test('karyawan tidak aktif (is_active false) tidak diproses sama sekali', function () {
    $karyawan = Karyawan::factory()->umum()->create(['instansi_id' => $this->instansi->id, 'is_active' => false]);

    jalankanRekap('2026-07-17');

    expect(Absensi::where('karyawan_id', $karyawan->id)->exists())->toBeFalse();
});
test('cuti approved membuat karyawan tidak dianggap alpha', function () {
    $shift = Shift::factory()->create(['instansi_id' => $this->instansi->id, 'hari_kerja' => []]);
    $karyawan = Karyawan::factory()->umum()->create(['instansi_id' => $this->instansi->id]);
    KaryawanShift::factory()->openEnded()->create([
        'karyawan_id' => $karyawan->id,
        'shift_id' => $shift->id,
        'tanggal_berlaku' => '2026-07-01',
    ]);
    // Sengaja tanpa sinkronisasi Absensi, supaya yang diuji jalur helper
    Cuti::factory()->create([
        'karyawan_id' => $karyawan->id,
        'tanggal_mulai' => '2026-07-16',
        'tanggal_selesai' => '2026-07-18',
        'status' => 'approved',
    ]);

    jalankanRekap('2026-07-17');

    expect(Absensi::where('karyawan_id', $karyawan->id)->exists())->toBeFalse();
});

test('jadwal libur dihitung sebagai libur personal di tabel ringkasan', function () {
    $karyawan = Karyawan::factory()->rotasi()->create(['instansi_id' => $this->instansi->id]);
    Jadwal::create([
        'karyawan_id' => $karyawan->id,
        'tanggal' => '2026-07-17',
        'shift_id' => null,
        'jenis' => 'libur',
        'sumber' => 'generate',
    ]);

    Artisan::call('absensi:rekap-harian', ['--tanggal' => '2026-07-17']);

    $baris = collect(explode("\n", Artisan::output()))
        ->first(fn ($l) => str_contains($l, '|') && str_contains($l, '17 Jul 2026'));

    // Kolom: Tanggal | Total | Sudah Absen | Libur Mingguan | Libur Nasional | Libur Personal | Jadwal Hilang | Alpha
    $kolom = array_values(array_filter(array_map('trim', explode('|', $baris)), fn ($k) => $k !== ''));

    expect($kolom[1])->toBe('1')   // Total karyawan
        ->and($kolom[5])->toBe('1'); // Libur Personal
});
