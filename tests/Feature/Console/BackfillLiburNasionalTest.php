<?php

use App\Models\HariLibur;
use App\Models\Instansi;
use App\Models\Jadwal;
use App\Models\Karyawan;

it('menandai baris libur yang jatuh di tanggal libur nasional', function () {
    $instansi = Instansi::factory()->create();
    $karyawan = Karyawan::factory()->create(['instansi_id' => $instansi->id]);

    HariLibur::factory()->create([
        'instansi_id'     => $instansi->id,
        'tanggal'         => '2026-08-17',
        'is_cuti_bersama' => false,
    ]);

    $jadwal = Jadwal::factory()->libur()->create([
        'karyawan_id' => $karyawan->id,
        'tanggal'     => '2026-08-17',
    ]);

    $this->artisan('jadwal:backfill-libur-nasional')->assertSuccessful();

    expect($jadwal->fresh()->libur_nasional)->toBeTrue();
});

it('tidak menandai baris libur di tanggal biasa', function () {
    $instansi = Instansi::factory()->create();
    $karyawan = Karyawan::factory()->create(['instansi_id' => $instansi->id]);

    HariLibur::factory()->create([
        'instansi_id'     => $instansi->id,
        'tanggal'         => '2026-08-17',
        'is_cuti_bersama' => false,
    ]);

    $jadwal = Jadwal::factory()->libur()->create([
        'karyawan_id' => $karyawan->id,
        'tanggal'     => '2026-08-18',
    ]);

    $this->artisan('jadwal:backfill-libur-nasional');

    expect($jadwal->fresh()->libur_nasional)->toBeFalse();
});

it('tidak menandai baris kerja walau jatuh di libur nasional', function () {
    // Karyawan rotasi dengan pola berlaku_saat_libur_nasional tetap
    // bekerja. Barisnya piket, bukan libur — kolom ini menandai baris
    // LIBUR, bukan tanggalnya.
    $instansi = Instansi::factory()->create();
    $karyawan = Karyawan::factory()->create(['instansi_id' => $instansi->id]);

    HariLibur::factory()->create([
        'instansi_id'     => $instansi->id,
        'tanggal'         => '2026-08-17',
        'is_cuti_bersama' => false,
    ]);

    $jadwal = Jadwal::factory()->create([
        'karyawan_id' => $karyawan->id,
        'tanggal'     => '2026-08-17',
        'jenis'       => Jadwal::JENIS_PIKET,
    ]);

    $this->artisan('jadwal:backfill-libur-nasional');

    expect($jadwal->fresh()->libur_nasional)->toBeFalse();
});

it('tidak menandai cuti bersama', function () {
    // Cuti bersama TIDAK meliburkan (scope meliburkan()). Karyawan tetap
    // masuk; yang ingin libur mengajukan cuti biasa.
    $instansi = Instansi::factory()->create();
    $karyawan = Karyawan::factory()->create(['instansi_id' => $instansi->id]);

    HariLibur::factory()->create([
        'instansi_id'     => $instansi->id,
        'tanggal'         => '2026-08-17',
        'is_cuti_bersama' => true,
    ]);

    $jadwal = Jadwal::factory()->libur()->create([
        'karyawan_id' => $karyawan->id,
        'tanggal'     => '2026-08-17',
    ]);

    $this->artisan('jadwal:backfill-libur-nasional');

    expect($jadwal->fresh()->libur_nasional)->toBeFalse();
});

it('tidak menandai baris karyawan dari instansi lain', function () {
    // Libur nasional disimpan per instansi. Tanpa filter instansi,
    // libur RS A akan menandai jadwal karyawan RS B.
    $instansiA = Instansi::factory()->create();
    $instansiB = Instansi::factory()->create();

    HariLibur::factory()->create([
        'instansi_id'     => $instansiA->id,
        'tanggal'         => '2026-08-17',
        'is_cuti_bersama' => false,
    ]);

    $karyawanB = Karyawan::factory()->create(['instansi_id' => $instansiB->id]);

    $jadwal = Jadwal::factory()->libur()->create([
        'karyawan_id' => $karyawanB->id,
        'tanggal'     => '2026-08-17',
    ]);

    $this->artisan('jadwal:backfill-libur-nasional');

    expect($jadwal->fresh()->libur_nasional)->toBeFalse();
});

it('dry-run tidak menulis apa pun', function () {
    $instansi = Instansi::factory()->create();
    $karyawan = Karyawan::factory()->create(['instansi_id' => $instansi->id]);

    HariLibur::factory()->create([
        'instansi_id'     => $instansi->id,
        'tanggal'         => '2026-08-17',
        'is_cuti_bersama' => false,
    ]);

    $jadwal = Jadwal::factory()->libur()->create([
        'karyawan_id' => $karyawan->id,
        'tanggal'     => '2026-08-17',
    ]);

    $this->artisan('jadwal:backfill-libur-nasional --dry-run');

    expect($jadwal->fresh()->libur_nasional)->toBeFalse();
});
