<?php

// tests/Feature/Filament/HariLiburResourceTest.php
//
// File baru — HariLiburResource sama sekali belum pernah punya test sampai
// fase 29, padahal satu barisnya berpengaruh ke 4 tempat: GenerateJadwalBulanan,
// GenerateJadwalRotasi, RekapHarian, dan helperText tanggal di JadwalForm.

use App\Filament\Resources\HariLiburs\Pages\CreateHariLibur;
use App\Filament\Resources\HariLiburs\Pages\EditHariLibur;
use App\Filament\Resources\HariLiburs\Pages\ListHariLiburs;
use App\Filament\Resources\HariLiburs\Pages\ViewHariLibur;
use App\Models\HariLibur;
use App\Models\Instansi;

use function Pest\Livewire\livewire;

beforeEach(function () {
    actingAsAdmin();
    $this->instansi = Instansi::factory()->create();
});

// ── List & create dasar ──────────────────────────────────────────────────

it('menampilkan daftar hari libur', function () {
    $records = HariLibur::factory()->count(3)->create(['instansi_id' => $this->instansi->id]);

    livewire(ListHariLiburs::class)
        ->assertCanSeeTableRecords($records);
});

it('bisa membuat hari libur dengan data valid', function () {
    livewire(CreateHariLibur::class)
        ->fillForm([
            'instansi_id' => $this->instansi->id,
            'tanggal' => '2026-08-17',
            'nama' => 'Hari Kemerdekaan',
            'is_cuti_bersama' => false,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(HariLibur::where('instansi_id', $this->instansi->id)
        ->whereDate('tanggal', '2026-08-17')
        ->first())
        ->nama->toBe('Hari Kemerdekaan');
});

it('menolak submit tanpa instansi, tanggal, atau nama', function () {
    livewire(CreateHariLibur::class)
        ->fillForm([
            'instansi_id' => null,
            'tanggal' => null,
            'nama' => null,
        ])
        ->call('create')
        ->assertHasFormErrors(['instansi_id', 'tanggal', 'nama']);
});

// ── Unique (instansi_id, tanggal) ────────────────────────────────────────
//
// Rule lama cuma mengecek kolom `tanggal` tanpa scope instansi — LEBIH KETAT
// dari constraint DB. Akibatnya instansi kedua tidak bisa mendaftarkan
// tanggal yang sudah dipakai instansi pertama. Kebalikan dari kasus
// KuotaCuti (fase 25) & Absensi (fase 27) yang form-nya justru terlalu
// longgar.

it('menolak tanggal duplikat untuk instansi yang sama', function () {
    HariLibur::factory()->create([
        'instansi_id' => $this->instansi->id,
        'tanggal' => '2026-08-17',
    ]);

    livewire(CreateHariLibur::class)
        ->fillForm([
            'instansi_id' => $this->instansi->id,
            'tanggal' => '2026-08-17',
            'nama' => 'Duplikat',
        ])
        ->call('create')
        ->assertHasFormErrors(['tanggal']);

    expect(HariLibur::where('instansi_id', $this->instansi->id)->count())->toBe(1);
});

it('MENGIZINKAN tanggal yang sama untuk instansi berbeda', function () {
    $instansiLain = Instansi::factory()->create();

    HariLibur::factory()->create([
        'instansi_id' => $this->instansi->id,
        'tanggal' => '2026-08-17',
        'nama' => 'Hari Kemerdekaan',
    ]);

    livewire(CreateHariLibur::class)
        ->fillForm([
            'instansi_id' => $instansiLain->id,
            'tanggal' => '2026-08-17',
            'nama' => 'Hari Kemerdekaan',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(HariLibur::whereDate('tanggal', '2026-08-17')->count())->toBe(2);
});

it('edit tidak kena unique constraint dirinya sendiri', function () {
    $hariLibur = HariLibur::factory()->create([
        'instansi_id' => $this->instansi->id,
        'tanggal' => '2026-08-17',
        'nama' => 'Hari Kemerdekaan',
    ]);

    livewire(EditHariLibur::class, ['record' => $hariLibur->getRouteKey()])
        ->fillForm(['nama' => 'HUT RI ke-81'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($hariLibur->fresh()->nama)->toBe('HUT RI ke-81');
});

// ── is_cuti_bersama ──────────────────────────────────────────────────────
//
// Kebijakan RS: saat cuti bersama karyawan TETAP MASUK; yang ingin libur
// mengajukan cuti biasa. Tapi sistem masih memperlakukan baris ini sama
// persis seperti libur nasional di GenerateJadwalBulanan,
// GenerateJadwalRotasi, dan RekapHarian — flag ini tidak dibaca di mana pun.
//
// Dua test di bawah MENDOKUMENTASIKAN keadaan sekarang, bukan membenarkannya.
// Kalau nanti generator disesuaikan (lihat todo.md), test kedua harus dibalik
// secara sadar — bukan kebetulan.

it('is_cuti_bersama tersimpan', function () {
    livewire(CreateHariLibur::class)
        ->fillForm([
            'instansi_id' => $this->instansi->id,
            'tanggal' => '2026-12-26',
            'nama' => 'Cuti Bersama Natal',
            'is_cuti_bersama' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(HariLibur::whereDate('tanggal', '2026-12-26')->first())
        ->is_cuti_bersama->toBeTruthy();
});

it('cuti bersama saat ini TIDAK dibedakan dari libur nasional oleh generator', function () {
    $cutiBersama = HariLibur::factory()->cutiBersama()->create([
        'instansi_id' => $this->instansi->id,
        'tanggal' => '2026-12-26',
    ]);

    $liburNasional = HariLibur::factory()->create([
        'instansi_id' => $this->instansi->id,
        'tanggal' => '2026-12-25',
        'is_cuti_bersama' => false,
    ]);

    // Tidak ada satu pun query di aplikasi yang memfilter berdasarkan
    // is_cuti_bersama — keduanya sama-sama terambil sebagai hari libur.
    $semuaLibur = HariLibur::where('instansi_id', $this->instansi->id)
        ->whereBetween('tanggal', ['2026-12-25', '2026-12-26'])
        ->pluck('id');

    expect($semuaLibur)->toHaveCount(2)
        ->and($semuaLibur)->toContain($cutiBersama->id, $liburNasional->id);
});



// ============================================================================
// TAMBAHAN untuk tests/Feature/Filament/HariLiburResourceTest.php
//
// Tempel di akhir file. Tambahkan import:
//   use App\Filament\Resources\HariLiburs\Pages\ViewHariLibur;
// ============================================================================

// ── Halaman View (batch B) ───────────────────────────────────────────────

it('halaman View hari libur bisa dibuka', function () {
    $hariLibur = HariLibur::factory()->create([
        'instansi_id' => $this->instansi->id,
        'tanggal' => '2026-08-17',
    ]);

    livewire(ViewHariLibur::class, ['record' => $hariLibur->getRouteKey()])
        ->assertSuccessful();
});

it('ViewAction muncul di tabel', function () {
    $hariLibur = HariLibur::factory()->create([
        'instansi_id' => $this->instansi->id,
        'tanggal' => '2026-08-18',
    ]);

    livewire(ListHariLiburs::class)
        ->assertTableActionVisible('view', $hariLibur);
});

// ── Filter (batch C) ─────────────────────────────────────────────────────

it('filter instansi membatasi baris ke instansi yang dipilih', function () {
    $instansiLain = Instansi::factory()->create();

    $milikIni = HariLibur::factory()->create([
        'instansi_id' => $this->instansi->id,
        'tanggal' => '2026-08-17',
    ]);

    $milikLain = HariLibur::factory()->create([
        'instansi_id' => $instansiLain->id,
        'tanggal' => '2026-08-17',
    ]);

    livewire(ListHariLiburs::class)
        ->filterTable('instansi_id', $this->instansi->id)
        ->assertCanSeeTableRecords([$milikIni])
        ->assertCanNotSeeTableRecords([$milikLain]);
});

it('filter rentang tanggal membatasi baris yang tampil', function () {
    $diDalam = HariLibur::factory()->create([
        'instansi_id' => $this->instansi->id,
        'tanggal' => '2026-08-17',
    ]);

    $diLuar = HariLibur::factory()->create([
        'instansi_id' => $this->instansi->id,
        'tanggal' => '2026-12-25',
    ]);

    livewire(ListHariLiburs::class)
        ->filterTable('rentang_tanggal', [
            'dari' => '2026-08-01',
            'sampai' => '2026-08-31',
        ])
        ->assertCanSeeTableRecords([$diDalam])
        ->assertCanNotSeeTableRecords([$diLuar]);
});
