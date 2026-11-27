<?php

use App\Models\HariLibur;
use App\Models\Instansi;
use App\Models\Jadwal;
use App\Models\Karyawan;
use App\Models\KaryawanPolaRotasi;
use App\Models\KaryawanShift;
use App\Models\PolaRotasi;
use App\Models\Shift;

it('generator bulanan menandai libur nasional, tidak menandai libur mingguan', function () {
    // Dua baris libur di bulan yang sama, lahir dari dua cabang berbeda.
    // Keduanya jenis='libur'; yang membedakan cuma kolom ini.
    $instansi = Instansi::factory()->create();

    $shift = Shift::factory()->create([
        'instansi_id' => $instansi->id,
        'hari_kerja'  => [1, 2, 3, 4, 5], // Senin-Jumat
    ]);

    $karyawan = Karyawan::factory()->create([
        'instansi_id' => $instansi->id,
        'tipe_jadwal' => Karyawan::TIPE_UMUM,
        'is_active'   => true,
    ]);

    KaryawanShift::factory()->create([
        'karyawan_id'      => $karyawan->id,
        'shift_id'         => $shift->id,
        'tanggal_berlaku'  => '2026-08-01',
        'tanggal_berakhir' => null,
    ]);

    // 17 Agustus 2026 jatuh hari Senin — hari kerja, jadi barisnya lahir
    // dari cabang libur nasional, bukan cabang hari-bukan-kerja.
    HariLibur::factory()->create([
        'instansi_id'     => $instansi->id,
        'tanggal'         => '2026-08-17',
        'is_cuti_bersama' => false,
    ]);

    $this->artisan('jadwal:generate-bulanan --bulan=8 --tahun=2026')->assertSuccessful();

    $liburNasional = Jadwal::where('karyawan_id', $karyawan->id)
        ->whereDate('tanggal', '2026-08-17')->first();

    // 16 Agustus 2026 = Minggu, bukan hari kerja shift.
    $liburMingguan = Jadwal::where('karyawan_id', $karyawan->id)
        ->whereDate('tanggal', '2026-08-16')->first();

    expect($liburNasional->jenis)->toBe(Jadwal::JENIS_LIBUR)
        ->and($liburNasional->libur_nasional)->toBeTrue()
        ->and($liburMingguan->jenis)->toBe(Jadwal::JENIS_LIBUR)
        ->and($liburMingguan->libur_nasional)->toBeFalse();
});

it('generator rotasi menandai libur nasional kalau polanya tidak berlaku saat itu', function () {
    $instansi = Instansi::factory()->create();

    $pola = PolaRotasi::factory()->create([
        'instansi_id'                 => $instansi->id,
        'unit_kerja'                  => 'IGD',
        'berlaku_saat_libur_nasional' => false,
    ]);

    $karyawan = Karyawan::factory()->create([
        'instansi_id' => $instansi->id,
        'tipe_jadwal' => Karyawan::TIPE_ROTASI,
        'unit_kerja'  => 'IGD',
    ]);

    KaryawanPolaRotasi::factory()->create([
        'karyawan_id'      => $karyawan->id,
        'pola_rotasi_id'   => $pola->id,
        'tanggal_mulai'    => '2026-08-01',
        'tanggal_berakhir' => null,
    ]);

    HariLibur::factory()->create([
        'instansi_id'     => $instansi->id,
        'tanggal'         => '2026-08-17',
        'is_cuti_bersama' => false,
    ]);

    $this->artisan('jadwal:generate-rotasi 8 2026')->assertSuccessful();

    $baris = Jadwal::where('karyawan_id', $karyawan->id)
        ->whereDate('tanggal', '2026-08-17')->first();

    expect($baris->jenis)->toBe('libur')
        ->and($baris->libur_nasional)->toBeTrue();
});

it('karyawan rotasi yang tetap bekerja saat libur nasional TIDAK ditandai', function () {
    // Kolom ini menandai "baris libur ini karena libur nasional", BUKAN
    // "tanggal ini libur nasional". Pola yang berlaku saat libur nasional
    // menghasilkan baris piket — bukan libur, jadi kolomnya false.
    // Perbedaan halus yang gampang terbalik saat kode ini disentuh lagi.
    $instansi = Instansi::factory()->create();

    $pola = PolaRotasi::factory()->create([
        'instansi_id'                 => $instansi->id,
        'unit_kerja'                  => 'IGD',
        'berlaku_saat_libur_nasional' => true,
    ]);

    $karyawan = Karyawan::factory()->create([
        'instansi_id' => $instansi->id,
        'tipe_jadwal' => Karyawan::TIPE_ROTASI,
        'unit_kerja'  => 'IGD',
    ]);

    KaryawanPolaRotasi::factory()->create([
        'karyawan_id'      => $karyawan->id,
        'pola_rotasi_id'   => $pola->id,
        'tanggal_mulai'    => '2026-08-17', // anchor di langkah kerja
        'tanggal_berakhir' => null,
    ]);

    HariLibur::factory()->create([
        'instansi_id'     => $instansi->id,
        'tanggal'         => '2026-08-17',
        'is_cuti_bersama' => false,
    ]);

    $this->artisan('jadwal:generate-rotasi 8 2026')->assertSuccessful();

    $baris = Jadwal::where('karyawan_id', $karyawan->id)
        ->whereDate('tanggal', '2026-08-17')->first();

    expect($baris->jenis)->toBe('piket')
        ->and($baris->libur_nasional)->toBeFalse();
});

it('libur mingguan pola rotasi tidak ditandai libur nasional', function () {
    // Langkah LIBUR di pola, di tanggal yang BUKAN libur nasional.
    $instansi = Instansi::factory()->create();

    $pola = PolaRotasi::factory()->create([
        'instansi_id' => $instansi->id,
        'unit_kerja'  => 'IGD',
    ]);

    $karyawan = Karyawan::factory()->create([
        'instansi_id' => $instansi->id,
        'tipe_jadwal' => Karyawan::TIPE_ROTASI,
        'unit_kerja'  => 'IGD',
    ]);

    KaryawanPolaRotasi::factory()->create([
        'karyawan_id'      => $karyawan->id,
        'pola_rotasi_id'   => $pola->id,
        'tanggal_mulai'    => '2026-08-01',
        'tanggal_berakhir' => null,
    ]);

    $adaLibur = Jadwal::where('karyawan_id', $karyawan->id)
        ->where('jenis', 'libur');

    $this->artisan('jadwal:generate-rotasi 8 2026')->assertSuccessful();

    expect($adaLibur->count())->toBeGreaterThan(0)
        ->and($adaLibur->where('libur_nasional', true)->count())->toBe(0);
});
