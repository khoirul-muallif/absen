<?php

use App\Filament\Resources\PolaRotasis\Pages\CreatePolaRotasi;
use App\Filament\Resources\PolaRotasis\Pages\EditPolaRotasi;
use App\Filament\Resources\PolaRotasis\Pages\ListPolaRotasis;
use App\Models\Instansi;
use App\Models\Karyawan;
use App\Models\KaryawanPolaRotasi;
use App\Models\PolaRotasi;
use App\Models\Shift;

use function Pest\Livewire\livewire;

beforeEach(function () {
    actingAsAdmin();
    $this->instansi = Instansi::factory()->create();
});

// ── List page ────────────────────────────────────────────────────────────

it('menampilkan daftar pola rotasi', function () {
    $records = PolaRotasi::factory()->count(3)->create();

    livewire(ListPolaRotasis::class)
        ->assertCanSeeTableRecords($records);
});

// ── Create ───────────────────────────────────────────────────────────────

it('bisa membuat pola rotasi dengan langkah campuran shift & libur', function () {
    $shiftPagi = Shift::factory()->create(['instansi_id' => $this->instansi->id]);
    $shiftMalam = Shift::factory()->create(['instansi_id' => $this->instansi->id]);

    livewire(CreatePolaRotasi::class)
        ->fillForm([
            'instansi_id' => $this->instansi->id,
            'unit_kerja' => 'IGD',
            'nama_pola' => 'Rotasi IGD 3 Hari',
            'berlaku_saat_libur_nasional' => true,
            'is_active' => true,
            'langkah' => [
                ['shift_id' => $shiftPagi->id, 'libur' => false],
                ['shift_id' => $shiftMalam->id, 'libur' => false],
                ['shift_id' => null, 'libur' => true],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $record = PolaRotasi::where('nama_pola', 'Rotasi IGD 3 Hari')->first();

    expect($record)->not->toBeNull()
        ->and($record->unit_kerja)->toBe('IGD')
        ->and(count($record->langkah))->toBe(3)
        ->and($record->langkah[0]['shift_id'])->toBe($shiftPagi->id)
        ->and($record->langkah[2]['libur'])->toBeTrue();
});

it('menolak nama_pola kosong', function () {
    livewire(CreatePolaRotasi::class)
        ->fillForm([
            'instansi_id' => $this->instansi->id,
            'unit_kerja' => 'IGD',
            'nama_pola' => '',
            'langkah' => [
                ['shift_id' => null, 'libur' => true],
            ],
        ])
        ->call('create')
        ->assertHasFormErrors(['nama_pola' => 'required']);
});

it('menolak langkah non-libur tanpa shift_id', function () {
    livewire(CreatePolaRotasi::class)
        ->fillForm([
            'instansi_id' => $this->instansi->id,
            'unit_kerja' => 'IGD',
            'nama_pola' => 'Rotasi Tanpa Shift',
            'langkah' => [
                ['shift_id' => null, 'libur' => false], // invalid: bukan libur tapi shift kosong
            ],
        ])
        ->call('create')
        ->assertHasFormErrors(['langkah.0.shift_id' => 'required']);
});

it('mengizinkan langkah libur tanpa shift_id', function () {
    livewire(CreatePolaRotasi::class)
        ->fillForm([
            'instansi_id' => $this->instansi->id,
            'unit_kerja' => 'IGD',
            'nama_pola' => 'Rotasi Libur Saja',
            'langkah' => [
                ['shift_id' => null, 'libur' => true],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();
});

// ── Guard instansi pada shift di Repeater ────────────────────────────────
//
// Sebelumnya dropdown mengambil SEMUA shift dari semua instansi
// (Shift::query()->pluck(...)) tanpa filter maupun guard server-side, padahal
// pola_rotasis punya kolom instansi_id. Bug yang sama dengan KaryawanShift
// di fase 31, tapi di sini tanpa guard apa pun sebagai pembanding.

it('menolak langkah yang memakai shift milik instansi lain', function () {
    $instansiLain = Instansi::factory()->create();
    $shiftLain = Shift::factory()->create(['instansi_id' => $instansiLain->id]);

    livewire(CreatePolaRotasi::class)
        ->fillForm([
            'instansi_id' => $this->instansi->id,
            'unit_kerja' => 'IGD',
            'nama_pola' => 'Rotasi Salah Instansi',
            'langkah' => [
                ['shift_id' => $shiftLain->id, 'libur' => false],
            ],
        ])
        ->call('create')
        ->assertHasFormErrors(['langkah.0.shift_id']);

    expect(PolaRotasi::where('nama_pola', 'Rotasi Salah Instansi')->exists())->toBeFalse();
});

// ── Unique nama_pola per (instansi, unit_kerja) ──────────────────────────
//
// KEPUTUSAN fase 32: beda dari nama_shift yang sengaja boleh duplikat (fase
// 30), di sini unit_kerja sudah jadi pembeda tersendiri — jadi nama kembar
// tidak punya alasan struktural, dan cuma bikin dropdown Shift Karyawan
// Rotasi ambigu.

it('menolak nama pola yang sama dalam unit kerja yang sama', function () {
    PolaRotasi::factory()->create([
        'instansi_id' => $this->instansi->id,
        'unit_kerja' => 'IGD',
        'nama_pola' => 'Rotasi 3 Shift',
    ]);

    livewire(CreatePolaRotasi::class)
        ->fillForm([
            'instansi_id' => $this->instansi->id,
            'unit_kerja' => 'IGD',
            'nama_pola' => 'Rotasi 3 Shift',
            'langkah' => [
                ['shift_id' => null, 'libur' => true],
            ],
        ])
        ->call('create')
        ->assertHasFormErrors(['nama_pola']);

    expect(PolaRotasi::where('nama_pola', 'Rotasi 3 Shift')->count())->toBe(1);
});

it('MENGIZINKAN nama pola yang sama di unit kerja berbeda', function () {
    PolaRotasi::factory()->create([
        'instansi_id' => $this->instansi->id,
        'unit_kerja' => 'IGD',
        'nama_pola' => 'Rotasi 3 Shift',
    ]);

    livewire(CreatePolaRotasi::class)
        ->fillForm([
            'instansi_id' => $this->instansi->id,
            'unit_kerja' => 'ICU', // unit berbeda
            'nama_pola' => 'Rotasi 3 Shift',
            'langkah' => [
                ['shift_id' => null, 'libur' => true],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(PolaRotasi::where('nama_pola', 'Rotasi 3 Shift')->count())->toBe(2);
});

it('edit tidak kena aturan unique nama pola dirinya sendiri', function () {
    $pola = PolaRotasi::factory()->create([
        'instansi_id' => $this->instansi->id,
        'unit_kerja' => 'IGD',
        'nama_pola' => 'Rotasi 3 Shift',
        'langkah' => [['shift_id' => null, 'libur' => true]],
    ]);

    livewire(EditPolaRotasi::class, ['record' => $pola->getRouteKey()])
        ->fillForm(['is_active' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($pola->fresh()->is_active)->toBeFalse();
});

// ── Pola tanpa langkah ───────────────────────────────────────────────────
//
// Kolom `langkah` NOT NULL di DB, jadi null tidak mungkin terjadi —
// panjangSiklus() yang null-safe murni defensif. Yang BISA terjadi adalah
// array kosong: minItems(1) mencegahnya lewat form, tapi tidak lewat seeder
// atau insert langsung. Pola seperti itu akan dilewati generator tanpa pesan
// apa pun, jadi di tabel ditandai badge merah.

it('panjangSiklus mengembalikan 0 kalau langkah array kosong', function () {
    $pola = PolaRotasi::factory()->create([
        'instansi_id' => $this->instansi->id,
        'langkah' => [],
    ]);

    expect($pola->panjangSiklus())->toBe(0);
});

it('tabel tetap bisa dirender walau ada pola tanpa langkah', function () {
    $normal = PolaRotasi::factory()->create([
        'instansi_id' => $this->instansi->id,
        'langkah' => [['shift_id' => null, 'libur' => true]],
    ]);

    $kosong = PolaRotasi::factory()->create([
        'instansi_id' => $this->instansi->id,
        'langkah' => [],
    ]);

    livewire(ListPolaRotasis::class)
        ->assertCanSeeTableRecords([$normal, $kosong]);
});

// ── Guard hapus ──────────────────────────────────────────────────────────
//
// Perilaku FK karyawan_pola_rotasis.pola_rotasi_id tidak disebut di
// SCHEMA.md — kalau CASCADE, penghapusan menghilangkan assignment diam-diam;
// kalau RESTRICT, muncul QueryException 1451 mentah. Guard menutup keduanya.

it('sedangDipakai true kalau pola masih di-assign ke karyawan', function () {
    $pola = PolaRotasi::factory()->create(['instansi_id' => $this->instansi->id]);
    $karyawan = Karyawan::factory()->rotasi()->create(['instansi_id' => $this->instansi->id]);

    KaryawanPolaRotasi::factory()->create([
        'karyawan_id' => $karyawan->id,
        'pola_rotasi_id' => $pola->id,
        'tanggal_mulai' => '2026-08-01',
    ]);

    expect($pola->fresh()->sedangDipakai())->toBeTrue();
});

it('sedangDipakai false untuk pola yang belum di-assign', function () {
    $pola = PolaRotasi::factory()->create(['instansi_id' => $this->instansi->id]);

    expect($pola->sedangDipakai())->toBeFalse();
});

it('tombol hapus disembunyikan untuk pola yang masih di-assign', function () {
    $pola = PolaRotasi::factory()->create(['instansi_id' => $this->instansi->id]);
    $karyawan = Karyawan::factory()->rotasi()->create(['instansi_id' => $this->instansi->id]);

    KaryawanPolaRotasi::factory()->create([
        'karyawan_id' => $karyawan->id,
        'pola_rotasi_id' => $pola->id,
        'tanggal_mulai' => '2026-08-01',
    ]);

    livewire(ListPolaRotasis::class)
        ->assertTableActionHidden('delete', $pola);
});

it('tombol hapus muncul untuk pola yang belum di-assign', function () {
    $pola = PolaRotasi::factory()->create(['instansi_id' => $this->instansi->id]);

    livewire(ListPolaRotasis::class)
        ->assertTableActionVisible('delete', $pola);
});

it('hapus massal dibatalkan kalau ada pola yang masih di-assign', function () {
    $dipakai = PolaRotasi::factory()->create(['instansi_id' => $this->instansi->id]);
    $bebas = PolaRotasi::factory()->create(['instansi_id' => $this->instansi->id]);

    $karyawan = Karyawan::factory()->rotasi()->create(['instansi_id' => $this->instansi->id]);

    KaryawanPolaRotasi::factory()->create([
        'karyawan_id' => $karyawan->id,
        'pola_rotasi_id' => $dipakai->id,
        'tanggal_mulai' => '2026-08-01',
    ]);

    livewire(ListPolaRotasis::class)
        ->callTableBulkAction('delete', [$dipakai, $bebas]);

    expect(PolaRotasi::find($dipakai->id))->not->toBeNull()
        ->and(PolaRotasi::find($bebas->id))->not->toBeNull();
});
