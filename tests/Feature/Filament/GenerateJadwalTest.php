<?php

use App\Filament\Pages\GenerateJadwal;
use App\Models\Instansi;
use App\Models\Jadwal;
use App\Models\Karyawan;
use App\Models\KaryawanPolaRotasi;
use App\Models\KaryawanShift;
use App\Models\PolaRotasi;
use App\Models\Shift;

use function Pest\Livewire\livewire;

beforeEach(function () {
    actingAsAdmin();
});

it('halaman generate jadwal bisa dibuka', function () {
    livewire(GenerateJadwal::class)->assertSuccessful();
});

it('mount mengisi bulan & tahun default ke bulan depan di kedua form', function () {
    $bulanDepan = now()->addMonthNoOverflow();

    livewire(GenerateJadwal::class)
        ->assertSet('dataBulanan.bulan', $bulanDepan->month)
        ->assertSet('dataBulanan.tahun', $bulanDepan->year)
        ->assertSet('dataRotasi.bulan', $bulanDepan->month)
        ->assertSet('dataRotasi.tahun', $bulanDepan->year);
});

// ── generateBulananAction ───────────────────────────────────────────────

it('generateBulananAction membuat Jadwal untuk karyawan umum sesuai bulan dipilih', function () {
    $instansi = Instansi::factory()->create();
    $shift = Shift::factory()->create([
        'instansi_id' => $instansi->id,
        'hari_kerja' => [], // kosong = dianggap kerja setiap hari (lihat ShiftTest fase 14)
    ]);
    $karyawan = Karyawan::factory()->create([
        'instansi_id' => $instansi->id,
        'tipe_jadwal' => 'umum',
    ]);
    KaryawanShift::factory()->create([
        'karyawan_id' => $karyawan->id,
        'shift_id' => $shift->id,
        'tanggal_berlaku' => '2026-10-01',
        'tanggal_berakhir' => '2026-12-31',
    ]);

    livewire(GenerateJadwal::class)
        ->set('dataBulanan.bulan', 11)
        ->set('dataBulanan.tahun', 2026)
        ->callAction('generateBulanan');

    expect(Jadwal::where('karyawan_id', $karyawan->id)
        ->whereYear('tanggal', 2026)
        ->whereMonth('tanggal', 11)
        ->exists())->toBeTrue();
});

it('generateBulananAction memunculkan notifikasi hasil', function () {
    livewire(GenerateJadwal::class)
        ->set('dataBulanan.bulan', 11)
        ->set('dataBulanan.tahun', 2026)
        ->callAction('generateBulanan')
        ->assertNotified();
});

it('generateBulananAction tidak menimpa Jadwal yang sudah ada', function () {
    $instansi = Instansi::factory()->create();
    $shift = Shift::factory()->create(['instansi_id' => $instansi->id, 'hari_kerja' => []]);
    $karyawan = Karyawan::factory()->create(['instansi_id' => $instansi->id, 'tipe_jadwal' => 'umum']);
    KaryawanShift::factory()->create([
        'karyawan_id' => $karyawan->id,
        'shift_id' => $shift->id,
        'tanggal_berlaku' => '2026-10-01',
        'tanggal_berakhir' => '2026-12-31',
    ]);

    $existing = Jadwal::factory()->create([
        'karyawan_id' => $karyawan->id,
        'tanggal' => '2026-11-05',
        'jenis' => 'reguler',
        'sumber' => 'manual',
        'keterangan' => 'jangan ditimpa',
    ]);

    livewire(GenerateJadwal::class)
        ->set('dataBulanan.bulan', 11)
        ->set('dataBulanan.tahun', 2026)
        ->callAction('generateBulanan');

    expect($existing->fresh()->keterangan)->toBe('jangan ditimpa');
});

// ── generateRotasiAction ─────────────────────────────────────────────────

it('generateRotasiAction membuat Jadwal untuk karyawan rotasi tanpa filter unit', function () {
    $instansi = Instansi::factory()->create();
    $shift = Shift::factory()->create(['instansi_id' => $instansi->id]);
    $pola = PolaRotasi::factory()->create([
        'instansi_id' => $instansi->id,
        'unit_kerja' => 'IGD',
        'langkah' => [
            ['libur' => false, 'shift_id' => $shift->id],
        ],
    ]);
    $karyawan = Karyawan::factory()->create([
        'instansi_id' => $instansi->id,
        'tipe_jadwal' => 'rotasi',
        'unit_kerja' => 'IGD',
    ]);
    KaryawanPolaRotasi::factory()->create([
        'karyawan_id' => $karyawan->id,
        'pola_rotasi_id' => $pola->id,
        'tanggal_mulai' => '2026-11-01',
        'tanggal_berakhir' => null,
    ]);

    livewire(GenerateJadwal::class)
        ->set('dataRotasi.bulan', 11)
        ->set('dataRotasi.tahun', 2026)
        ->callAction('generateRotasi');

    expect(Jadwal::where('karyawan_id', $karyawan->id)
        ->whereYear('tanggal', 2026)
        ->whereMonth('tanggal', 11)
        ->exists())->toBeTrue();
});

it('generateRotasiAction dengan filter unit hanya menyentuh unit tersebut', function () {
    $instansi = Instansi::factory()->create();
    $shiftIgd = Shift::factory()->create(['instansi_id' => $instansi->id]);
    $shiftIcu = Shift::factory()->create(['instansi_id' => $instansi->id]);

    $polaIgd = PolaRotasi::factory()->create([
        'instansi_id' => $instansi->id,
        'unit_kerja' => 'IGD',
        'langkah' => [['libur' => false, 'shift_id' => $shiftIgd->id]],
    ]);
    $polaIcu = PolaRotasi::factory()->create([
        'instansi_id' => $instansi->id,
        'unit_kerja' => 'ICU',
        'langkah' => [['libur' => false, 'shift_id' => $shiftIcu->id]],
    ]);

    $karyawanIgd = Karyawan::factory()->create([
        'instansi_id' => $instansi->id, 'tipe_jadwal' => 'rotasi', 'unit_kerja' => 'IGD',
    ]);
    $karyawanIcu = Karyawan::factory()->create([
        'instansi_id' => $instansi->id, 'tipe_jadwal' => 'rotasi', 'unit_kerja' => 'ICU',
    ]);

    KaryawanPolaRotasi::factory()->create([
        'karyawan_id' => $karyawanIgd->id, 'pola_rotasi_id' => $polaIgd->id,
        'tanggal_mulai' => '2026-11-01', 'tanggal_berakhir' => null,
    ]);
    KaryawanPolaRotasi::factory()->create([
        'karyawan_id' => $karyawanIcu->id, 'pola_rotasi_id' => $polaIcu->id,
        'tanggal_mulai' => '2026-11-01', 'tanggal_berakhir' => null,
    ]);

    livewire(GenerateJadwal::class)
        ->set('dataRotasi.bulan', 11)
        ->set('dataRotasi.tahun', 2026)
        ->set('dataRotasi.unit', 'IGD')
        ->callAction('generateRotasi');

    expect(Jadwal::where('karyawan_id', $karyawanIgd->id)
        ->whereYear('tanggal', 2026)->whereMonth('tanggal', 11)->exists())->toBeTrue();

    expect(Jadwal::where('karyawan_id', $karyawanIcu->id)
        ->whereYear('tanggal', 2026)->whereMonth('tanggal', 11)->exists())->toBeFalse();
});

it('generateRotasiAction memunculkan notifikasi hasil', function () {
    livewire(GenerateJadwal::class)
        ->set('dataRotasi.bulan', 11)
        ->set('dataRotasi.tahun', 2026)
        ->callAction('generateRotasi')
        ->assertNotified();
});
