<?php

use App\Models\Absensi;
use App\Models\Cuti;
use App\Models\HariLibur;
use App\Models\Instansi;
use App\Models\Jadwal;
use App\Models\Karyawan;
use App\Models\KaryawanShift;
use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    $this->instansi = Instansi::factory()->create();

    $this->shiftPagi = Shift::factory()->create([
        'instansi_id'     => $this->instansi->id,
        'nama_shift'      => 'Pagi',
        'jam_masuk'       => '07:00:00',
        'jam_pulang'      => '14:00:00',
        'toleransi_menit' => 15,
    ]);

    // Karyawan umum dengan assignment shift pagi, berlaku terus
    $this->umum = Karyawan::factory()->create([
        'instansi_id'  => $this->instansi->id,
        'is_active'    => true,
        'tipe_jadwal'  => Karyawan::TIPE_UMUM,
    ]);
    KaryawanShift::factory()->create([
        'karyawan_id'     => $this->umum->id,
        'shift_id'        => $this->shiftPagi->id,
        'tanggal_berlaku' => '2026-10-01',
        'tanggal_berakhir' => null,
    ]);

    $this->rotasi = Karyawan::factory()->create([
        'instansi_id' => $this->instansi->id,
        'is_active'   => true,
        'tipe_jadwal' => Karyawan::TIPE_ROTASI,
    ]);
});

// Deadline masuk pagi = 07:00 + toleransi 15 + grace 15 = 07:30

it('belum mengirim sebelum deadline masuk', function () {
    $this->travelTo(Carbon::parse('2026-10-06 07:29:00'));
    Artisan::call('absensi:pengingat-belum-absen');

    expect($this->umum->notifications()->count())->toBe(0);
});

it('mengirim setelah deadline masuk dengan teks yang benar', function () {
    $this->travelTo(Carbon::parse('2026-10-06 07:31:00'));
    Artisan::call('absensi:pengingat-belum-absen');

    $notif = $this->umum->notifications()->first();
    expect($this->umum->notifications()->count())->toBe(1)
        ->and($notif->data['tipe'])->toBe('belum_absen_masuk')
        ->and($notif->data['pesan'])->toContain('Masuk: 07:00')
        ->and($notif->data['pesan'])->toContain('Pulang: 14:00')
        ->and($notif->data['pesan'])->not->toContain('{');
});

it('tidak mengirim jika sudah absen masuk', function () {
    Absensi::factory()->create([
        'karyawan_id' => $this->umum->id,
        'shift_id'    => $this->shiftPagi->id,
        'tanggal'     => '2026-10-06',
        'waktu_masuk' => Carbon::parse('2026-10-06 07:02:00'),
    ]);

    $this->travelTo(Carbon::parse('2026-10-06 08:00:00'));
    Artisan::call('absensi:pengingat-belum-absen');

    expect($this->umum->notifications()->count())->toBe(0);
});

it('tidak mengirim saat cuti approved', function () {
    Cuti::factory()->create([
        'karyawan_id'    => $this->umum->id,
        'tanggal_mulai'  => '2026-10-06',
        'tanggal_selesai' => '2026-10-06',
        'status'         => 'approved',
    ]);

    $this->travelTo(Carbon::parse('2026-10-06 08:00:00'));
    Artisan::call('absensi:pengingat-belum-absen');

    expect($this->umum->notifications()->count())->toBe(0);
});

it('tidak mengirim pada libur nasional untuk karyawan umum', function () {
    HariLibur::factory()->create([
        'instansi_id'      => $this->instansi->id,
        'tanggal'          => '2026-10-06',
        'is_cuti_bersama'  => false,
    ]);

    $this->travelTo(Carbon::parse('2026-10-06 08:00:00'));
    Artisan::call('absensi:pengingat-belum-absen');

    expect($this->umum->notifications()->count())->toBe(0);
});

it('tetap mengirim pada cuti bersama untuk karyawan umum (kebijakan BUMS)', function () {
    HariLibur::factory()->cutiBersama()->create([
        'instansi_id' => $this->instansi->id,
        'tanggal'     => '2026-10-06',
    ]);

    $this->travelTo(Carbon::parse('2026-10-06 08:00:00'));
    Artisan::call('absensi:pengingat-belum-absen');

    expect($this->umum->notifications()->count())->toBe(1);
});

it('tidak mengirim dua kali pada hari yang sama', function () {
    $this->travelTo(Carbon::parse('2026-10-06 08:00:00'));
    Artisan::call('absensi:pengingat-belum-absen');
    Artisan::call('absensi:pengingat-belum-absen');

    expect($this->umum->notifications()->count())->toBe(1);
});

// ----- Rotasi -----

it('rotasi tanpa Jadwal tidak diingatkan (anomali, dicek lewat RekapHarian)', function () {
    $this->travelTo(Carbon::parse('2026-10-06 08:00:00'));
    Artisan::call('absensi:pengingat-belum-absen');

    expect($this->rotasi->notifications()->count())->toBe(0);
});

it('rotasi dengan Jadwal shift diingatkan sesuai jam shift', function () {
    Jadwal::factory()->create([
        'karyawan_id' => $this->rotasi->id,
        'shift_id'    => $this->shiftPagi->id,
        'tanggal'     => '2026-10-06',
        'jenis'       => Jadwal::JENIS_REGULER,
        'sumber'      => 'generate',
    ]);

    $this->travelTo(Carbon::parse('2026-10-06 07:31:00'));
    Artisan::call('absensi:pengingat-belum-absen');

    expect($this->rotasi->notifications()->count())->toBe(1);
});

it('rotasi IGD (libur nasional tetap masuk) diingatkan walau tanggal libur nasional', function () {
    // Pola IGD berlaku_saat_libur_nasional=true: generator tetap menulis shift
    Jadwal::factory()->create([
        'karyawan_id' => $this->rotasi->id,
        'shift_id'    => $this->shiftPagi->id,
        'tanggal'     => '2026-10-06',
        'jenis'       => Jadwal::JENIS_REGULER,
        'sumber'      => 'generate',
    ]);
    HariLibur::factory()->create([
        'instansi_id'     => $this->instansi->id,
        'tanggal'         => '2026-10-06',
        'is_cuti_bersama' => false,
    ]);

    $this->travelTo(Carbon::parse('2026-10-06 07:31:00'));
    Artisan::call('absensi:pengingat-belum-absen');

    expect($this->rotasi->notifications()->count())->toBe(1);
});

it('rotasi non-24 jam tidak diingatkan saat libur nasional (Jadwal = libur)', function () {
    // Generator menulis jenis libur untuk pola yang di-override libur nasional
    Jadwal::factory()->create([
        'karyawan_id' => $this->rotasi->id,
        'shift_id'    => null,
        'tanggal'     => '2026-10-06',
        'jenis'       => Jadwal::JENIS_LIBUR,
        'sumber'      => 'generate',
    ]);
    HariLibur::factory()->create([
        'instansi_id'     => $this->instansi->id,
        'tanggal'         => '2026-10-06',
        'is_cuti_bersama' => false,
    ]);

    $this->travelTo(Carbon::parse('2026-10-06 08:00:00'));
    Artisan::call('absensi:pengingat-belum-absen');

    expect($this->rotasi->notifications()->count())->toBe(0);
});

it('rekap harian menandai alpha rotasi IGD yang libur nasional tapi tidak absen', function () {
    Jadwal::factory()->create([
        'karyawan_id' => $this->rotasi->id,
        'shift_id'    => $this->shiftPagi->id,
        'tanggal'     => '2026-10-06',
        'jenis'       => Jadwal::JENIS_REGULER,
        'sumber'      => 'generate',
    ]);
    HariLibur::factory()->create([
        'instansi_id'     => $this->instansi->id,
        'tanggal'         => '2026-10-06',
        'is_cuti_bersama' => false,
    ]);

    Artisan::call('absensi:rekap-harian', ['--tanggal' => '2026-10-06']);

    $absensi = Absensi::where('karyawan_id', $this->rotasi->id)
        ->whereDate('tanggal', '2026-10-06')
        ->first();

    expect($absensi?->status)->toBe('alpha');
});
