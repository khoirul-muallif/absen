<?php

// tests/Feature/Filament/InstansiResourceTest.php
//
// File baru — InstansiResource belum pernah punya test sampai fase 35.
// PERHATIKAN nama file: suffix harus "Test.php" dengan T besar, kalau tidak
// PHPUnit melewatinya diam-diam tanpa pesan error (kejadian di fase 34).

use App\Filament\Resources\Instansis\Pages\CreateInstansi;
use App\Filament\Resources\Instansis\Pages\EditInstansi;
use App\Filament\Resources\Instansis\Pages\ListInstansis;
use App\Filament\Resources\Instansis\Pages\ViewInstansi;
use App\Models\HariLibur;
use App\Models\Instansi;
use App\Models\Karyawan;
use App\Models\QrInstansi;

use function Pest\Livewire\livewire;

beforeEach(function () {
    actingAsAdmin();
});

// ── List & create dasar ──────────────────────────────────────────────────

it('menampilkan daftar instansi', function () {
    $records = Instansi::factory()->count(3)->create();

    livewire(ListInstansis::class)
        ->assertCanSeeTableRecords($records);
});

it('bisa membuat instansi dengan data valid', function () {
    livewire(CreateInstansi::class)
        ->fillForm([
            'nama' => 'RSU Uji Coba',
            'kode_instansi' => 'RSUUJI',
            'latitude' => -7.0333,
            'longitude' => 110.4167,
            'radius_meter' => 100,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Instansi::where('kode_instansi', 'RSUUJI')->exists())->toBeTrue();
});

it('menolak kode_instansi yang duplikat', function () {
    Instansi::factory()->create(['kode_instansi' => 'RSUDUP']);

    livewire(CreateInstansi::class)
        ->fillForm([
            'nama' => 'Kembar',
            'kode_instansi' => 'RSUDUP',
            'latitude' => -7.0333,
            'longitude' => 110.4167,
            'radius_meter' => 100,
        ])
        ->call('create')
        ->assertHasFormErrors(['kode_instansi']);
});

// ── Batas koordinat ──────────────────────────────────────────────────────
//
// Tanpa batas nilai, latitude & longitude yang tertukar tersimpan tanpa
// keluhan — dan akibatnya SEMUA absen ditolak "di luar radius" tanpa petunjuk
// kenapa. Rentang lintang cuma -90..90, jadi nilai bujur pasti salah tempat.

it('menolak latitude di luar rentang -90..90', function () {
    livewire(CreateInstansi::class)
        ->fillForm([
            'nama' => 'Koordinat Tertukar',
            'kode_instansi' => 'RSUTKR',
            'latitude' => 110.4167,  // ini nilai bujur, salah tempat
            'longitude' => -7.0333,
            'radius_meter' => 100,
        ])
        ->call('create')
        ->assertHasFormErrors(['latitude']);

    expect(Instansi::where('kode_instansi', 'RSUTKR')->exists())->toBeFalse();
});

it('menolak longitude di luar rentang -180..180', function () {
    livewire(CreateInstansi::class)
        ->fillForm([
            'nama' => 'Bujur Ngawur',
            'kode_instansi' => 'RSUBJR',
            'latitude' => -7.0333,
            'longitude' => 200,
            'radius_meter' => 100,
        ])
        ->call('create')
        ->assertHasFormErrors(['longitude']);
});

it('menolak radius di luar rentang yang masuk akal', function () {
    livewire(CreateInstansi::class)
        ->fillForm([
            'nama' => 'Radius Ngawur',
            'kode_instansi' => 'RSURDS',
            'latitude' => -7.0333,
            'longitude' => 110.4167,
            'radius_meter' => 1,
        ])
        ->call('create')
        ->assertHasFormErrors(['radius_meter']);
});

// ── Kunci kode_instansi ──────────────────────────────────────────────────
//
// kode_instansi dikirim ke aplikasi mobile lewat /api/auth/me sebagai
// identitas. CATATAN: kode ini TIDAK dipakai pemindaian QR — endpoint
// /api/instansi/qr/{kode} mencari QrInstansi.kode_qr, kolom terpisah. Helper
// text lama menyebut sebaliknya.

it('kodeMasihBisaDiubah true untuk instansi yang belum punya karyawan maupun QR', function () {
    $instansi = Instansi::factory()->create();

    expect($instansi->kodeMasihBisaDiubah())->toBeTrue();
});

it('kodeMasihBisaDiubah false begitu sudah ada karyawan', function () {
    $instansi = Instansi::factory()->create();
    Karyawan::factory()->create(['instansi_id' => $instansi->id]);

    expect($instansi->fresh()->kodeMasihBisaDiubah())->toBeFalse();
});

it('kodeMasihBisaDiubah false begitu sudah ada QR', function () {
    $instansi = Instansi::factory()->create();
    QrInstansi::factory()->create(['instansi_id' => $instansi->id]);

    expect($instansi->fresh()->kodeMasihBisaDiubah())->toBeFalse();
});

it('field kode_instansi terkunci di form kalau sudah ada karyawan', function () {
    $instansi = Instansi::factory()->create();
    Karyawan::factory()->create(['instansi_id' => $instansi->id]);

    livewire(EditInstansi::class, ['record' => $instansi->getRouteKey()])
        ->assertFormFieldDisabled('kode_instansi');
});

it('field kode_instansi masih bisa diubah kalau instansi belum dipakai', function () {
    $instansi = Instansi::factory()->create();

    livewire(EditInstansi::class, ['record' => $instansi->getRouteKey()])
        ->assertFormFieldEnabled('kode_instansi');
});

// ── Guard hapus ──────────────────────────────────────────────────────────
//
// Lima tabel menggantung ke instansi_id, dan lewat karyawan seluruh riwayat
// absensi & pengajuan ikut (semua FK karyawan_id ON DELETE CASCADE).

it('sedangDipakai true kalau sudah ada karyawan', function () {
    $instansi = Instansi::factory()->create();
    Karyawan::factory()->create(['instansi_id' => $instansi->id]);

    expect($instansi->fresh()->sedangDipakai())->toBeTrue();
});

it('sedangDipakai true kalau sudah ada hari libur', function () {
    $instansi = Instansi::factory()->create();
    HariLibur::factory()->create(['instansi_id' => $instansi->id]);

    expect($instansi->fresh()->sedangDipakai())->toBeTrue();
});

it('sedangDipakai false untuk instansi yang masih kosong', function () {
    $instansi = Instansi::factory()->create();

    expect($instansi->sedangDipakai())->toBeFalse();
});

it('tombol hapus disembunyikan untuk instansi yang sudah dipakai', function () {
    $instansi = Instansi::factory()->create();
    Karyawan::factory()->create(['instansi_id' => $instansi->id]);

    livewire(ListInstansis::class)
        ->assertTableActionHidden('delete', $instansi);
});

it('tombol hapus muncul untuk instansi yang masih kosong', function () {
    $instansi = Instansi::factory()->create();

    livewire(ListInstansis::class)
        ->assertTableActionVisible('delete', $instansi);
});

it('hapus massal dibatalkan kalau ada instansi yang masih dipakai', function () {
    $dipakai = Instansi::factory()->create();
    $kosong = Instansi::factory()->create();

    Karyawan::factory()->create(['instansi_id' => $dipakai->id]);

    livewire(ListInstansis::class)
        ->callTableBulkAction('delete', [$dipakai, $kosong]);

    expect(Instansi::find($dipakai->id))->not->toBeNull()
        ->and(Instansi::find($kosong->id))->not->toBeNull();
});

// ── Halaman View ─────────────────────────────────────────────────────────

it('halaman View instansi bisa dibuka', function () {
    $instansi = Instansi::factory()->create();

    livewire(ViewInstansi::class, ['record' => $instansi->getRouteKey()])
        ->assertSuccessful();
});

it('ViewAction muncul di tabel', function () {
    $instansi = Instansi::factory()->create();

    livewire(ListInstansis::class)
        ->assertTableActionVisible('view', $instansi);
});

// ── Perhitungan jarak (dipakai validasi absen) ───────────────────────────

it('dalamRadius benar untuk titik persis di pusat instansi', function () {
    $instansi = Instansi::factory()->create([
        'latitude' => -7.0333,
        'longitude' => 110.4167,
        'radius_meter' => 100,
    ]);

    expect($instansi->dalamRadius(-7.0333, 110.4167))->toBeTrue();
});

it('dalamRadius false untuk titik jauh di luar radius', function () {
    $instansi = Instansi::factory()->create([
        'latitude' => -7.0333,
        'longitude' => 110.4167,
        'radius_meter' => 100,
    ]);

    // Jakarta, ~400 km dari Semarang.
    expect($instansi->dalamRadius(-6.2088, 106.8456))->toBeFalse();
});
