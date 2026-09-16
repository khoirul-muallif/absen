<?php

use App\Filament\Resources\KaryawanPolaRotasis\Pages\CreateKaryawanPolaRotasi;
use App\Filament\Resources\KaryawanPolaRotasis\Pages\EditKaryawanPolaRotasi;
use App\Filament\Resources\KaryawanPolaRotasis\Pages\ListKaryawanPolaRotasis;
use App\Filament\Resources\KaryawanPolaRotasis\Pages\ViewKaryawanPolaRotasi;
use App\Models\Instansi;
use App\Models\Karyawan;
use App\Models\KaryawanPolaRotasi;
use App\Models\PolaRotasi;

use function Pest\Livewire\livewire;

beforeEach(function () {
    actingAsAdmin();

    $this->instansi = Instansi::factory()->create();

    // Sejak fase 33 pola dibatasi ke instansi DAN unit_kerja karyawan, jadi
    // fixture harus nyambung di kedua kolom itu.
    $this->karyawan = Karyawan::factory()->rotasi()->create([
        'instansi_id' => $this->instansi->id,
        'unit_kerja' => 'IGD',
    ]);

    $this->pola = PolaRotasi::factory()->create([
        'instansi_id' => $this->instansi->id,
        'unit_kerja' => 'IGD',
        'langkah' => [
            ['shift_id' => null, 'libur' => false],
            ['shift_id' => null, 'libur' => true],
        ],
    ]);
});

// ── List & create dasar ──────────────────────────────────────────────────

it('menampilkan daftar assignment pola rotasi karyawan', function () {
    $records = KaryawanPolaRotasi::factory()->count(3)->create();

    livewire(ListKaryawanPolaRotasis::class)
        ->assertCanSeeTableRecords($records);
});

it('bisa membuat assignment pola rotasi dengan data valid', function () {
    livewire(CreateKaryawanPolaRotasi::class)
        ->fillForm([
            'karyawan_id' => $this->karyawan->id,
            'pola_rotasi_id' => $this->pola->id,
            'tanggal_mulai' => '2026-08-01',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(KaryawanPolaRotasi::where('karyawan_id', $this->karyawan->id)->exists())->toBeTrue();
});

it('menolak tanggal_berakhir sebelum tanggal_mulai', function () {
    livewire(CreateKaryawanPolaRotasi::class)
        ->fillForm([
            'karyawan_id' => $this->karyawan->id,
            'pola_rotasi_id' => $this->pola->id,
            'tanggal_mulai' => '2026-08-10',
            'tanggal_berakhir' => '2026-08-05',
        ])
        ->call('create')
        ->assertHasFormErrors(['tanggal_berakhir']);
});

it('menolak karyawan_id kosong', function () {
    livewire(CreateKaryawanPolaRotasi::class)
        ->fillForm([
            'karyawan_id' => null,
            'pola_rotasi_id' => $this->pola->id,
            'tanggal_mulai' => '2026-08-01',
        ])
        ->call('create')
        ->assertHasFormErrors(['karyawan_id']);
});

test('menolak karyawan_id yang bertipe umum walau ID valid (guard server-side)', function () {
    $karyawanUmum = Karyawan::factory()->umum()->create([
        'instansi_id' => $this->instansi->id,
        'unit_kerja' => 'IGD',
    ]);

    livewire(CreateKaryawanPolaRotasi::class)
        ->fillForm([
            'karyawan_id' => $karyawanUmum->id,
            'pola_rotasi_id' => $this->pola->id,
            'tanggal_mulai' => '2026-08-01',
        ])
        ->call('create')
        ->assertHasFormErrors(['karyawan_id']);

    expect(KaryawanPolaRotasi::where('karyawan_id', $karyawanUmum->id)->exists())->toBeFalse();
});

// ── Guard instansi & unit kerja pada pola ────────────────────────────────
//
// Kejadian ketiga berturut-turut untuk masalah dropdown lintas-instansi
// (setelah KaryawanShift fase 31 dan PolaRotasi fase 32). Di sini ditambah
// aturan unit_kerja: pola IGD tidak boleh di-assign ke karyawan Rawat Jalan.

it('menolak pola milik instansi lain', function () {
    $instansiLain = Instansi::factory()->create();
    $polaLain = PolaRotasi::factory()->create([
        'instansi_id' => $instansiLain->id,
        'unit_kerja' => 'IGD',
        'langkah' => [['shift_id' => null, 'libur' => true]],
    ]);

    livewire(CreateKaryawanPolaRotasi::class)
        ->fillForm([
            'karyawan_id' => $this->karyawan->id,
            'pola_rotasi_id' => $polaLain->id,
            'tanggal_mulai' => '2026-08-01',
        ])
        ->call('create')
        ->assertHasFormErrors(['pola_rotasi_id']);
});

it('menolak pola yang unit kerjanya berbeda dari karyawan', function () {
    $polaUnitLain = PolaRotasi::factory()->create([
        'instansi_id' => $this->instansi->id,
        'unit_kerja' => 'Rawat Jalan', // karyawan di IGD
        'langkah' => [['shift_id' => null, 'libur' => true]],
    ]);

    livewire(CreateKaryawanPolaRotasi::class)
        ->fillForm([
            'karyawan_id' => $this->karyawan->id,
            'pola_rotasi_id' => $polaUnitLain->id,
            'tanggal_mulai' => '2026-08-01',
        ])
        ->call('create')
        ->assertHasFormErrors(['pola_rotasi_id']);

    expect(KaryawanPolaRotasi::where('pola_rotasi_id', $polaUnitLain->id)->exists())->toBeFalse();
});

it('menolak pola yang belum punya langkah siklus', function () {
    $polaKosong = PolaRotasi::factory()->create([
        'instansi_id' => $this->instansi->id,
        'unit_kerja' => 'IGD',
        'langkah' => [],
    ]);

    livewire(CreateKaryawanPolaRotasi::class)
        ->fillForm([
            'karyawan_id' => $this->karyawan->id,
            'pola_rotasi_id' => $polaKosong->id,
            'tanggal_mulai' => '2026-08-01',
        ])
        ->call('create')
        ->assertHasFormErrors(['pola_rotasi_id']);
});

// ── Periode beririsan ────────────────────────────────────────────────────
//
// Lebih parah daripada di KaryawanShift: dua assignment beririsan berarti dua
// tanggal_mulai berbeda sebagai anchor, dan anchor itulah yang menentukan
// SELURUH urutan siklus — bukan cuma shift mana yang dipakai.

it('menolak assignment yang periodenya beririsan', function () {
    KaryawanPolaRotasi::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'pola_rotasi_id' => $this->pola->id,
        'tanggal_mulai' => '2026-08-01',
        'tanggal_berakhir' => '2026-08-31',
    ]);

    livewire(CreateKaryawanPolaRotasi::class)
        ->fillForm([
            'karyawan_id' => $this->karyawan->id,
            'pola_rotasi_id' => $this->pola->id,
            'tanggal_mulai' => '2026-08-15',
            'tanggal_berakhir' => '2026-09-15',
        ])
        ->call('create')
        ->assertHasFormErrors(['tanggal_mulai']);

    expect(KaryawanPolaRotasi::where('karyawan_id', $this->karyawan->id)->count())->toBe(1);
});

it('MENGIZINKAN transisi berurutan tanpa irisan', function () {
    KaryawanPolaRotasi::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'pola_rotasi_id' => $this->pola->id,
        'tanggal_mulai' => '2026-07-01',
        'tanggal_berakhir' => '2026-07-31',
    ]);

    livewire(CreateKaryawanPolaRotasi::class)
        ->fillForm([
            'karyawan_id' => $this->karyawan->id,
            'pola_rotasi_id' => $this->pola->id,
            'tanggal_mulai' => '2026-08-01',
            'tanggal_berakhir' => '2026-08-31',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(KaryawanPolaRotasi::where('karyawan_id', $this->karyawan->id)->count())->toBe(2);
});

it('menolak assignment baru kalau ada yang open-ended dan belum diakhiri', function () {
    KaryawanPolaRotasi::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'pola_rotasi_id' => $this->pola->id,
        'tanggal_mulai' => '2026-01-01',
        'tanggal_berakhir' => null,
    ]);

    livewire(CreateKaryawanPolaRotasi::class)
        ->fillForm([
            'karyawan_id' => $this->karyawan->id,
            'pola_rotasi_id' => $this->pola->id,
            'tanggal_mulai' => '2026-08-01',
        ])
        ->call('create')
        ->assertHasFormErrors(['tanggal_mulai']);
});

it('irisan hanya dicek per karyawan, bukan lintas karyawan', function () {
    $karyawanLain = Karyawan::factory()->rotasi()->create([
        'instansi_id' => $this->instansi->id,
        'unit_kerja' => 'IGD',
    ]);

    KaryawanPolaRotasi::factory()->create([
        'karyawan_id' => $karyawanLain->id,
        'pola_rotasi_id' => $this->pola->id,
        'tanggal_mulai' => '2026-08-01',
        'tanggal_berakhir' => '2026-08-31',
    ]);

    livewire(CreateKaryawanPolaRotasi::class)
        ->fillForm([
            'karyawan_id' => $this->karyawan->id,
            'pola_rotasi_id' => $this->pola->id,
            'tanggal_mulai' => '2026-08-01',
            'tanggal_berakhir' => '2026-08-31',
        ])
        ->call('create')
        ->assertHasNoFormErrors();
});

it('edit tidak kena aturan irisan dirinya sendiri', function () {
    $assignment = KaryawanPolaRotasi::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'pola_rotasi_id' => $this->pola->id,
        'tanggal_mulai' => '2026-08-01',
        'tanggal_berakhir' => '2026-08-31',
    ]);

    livewire(EditKaryawanPolaRotasi::class, ['record' => $assignment->getRouteKey()])
        ->fillForm(['tanggal_berakhir' => '2026-09-30'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($assignment->fresh()->tanggal_berakhir->toDateString())->toBe('2026-09-30');
});

// ── Halaman View ─────────────────────────────────────────────────────────

it('halaman View assignment bisa dibuka', function () {
    $assignment = KaryawanPolaRotasi::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'pola_rotasi_id' => $this->pola->id,
        'tanggal_mulai' => '2026-08-01',
    ]);

    livewire(ViewKaryawanPolaRotasi::class, ['record' => $assignment->getRouteKey()])
        ->assertSuccessful();
});

it('halaman View tetap bisa dibuka walau polanya belum punya langkah', function () {
    $polaKosong = PolaRotasi::factory()->create([
        'instansi_id' => $this->instansi->id,
        'unit_kerja' => 'IGD',
        'langkah' => [],
    ]);

    $assignment = KaryawanPolaRotasi::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'pola_rotasi_id' => $polaKosong->id,
        'tanggal_mulai' => '2026-08-01',
    ]);

    livewire(ViewKaryawanPolaRotasi::class, ['record' => $assignment->getRouteKey()])
        ->assertSuccessful();
});

it('ViewAction muncul di tabel', function () {
    $assignment = KaryawanPolaRotasi::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'pola_rotasi_id' => $this->pola->id,
        'tanggal_mulai' => '2026-08-01',
    ]);

    livewire(ListKaryawanPolaRotasis::class)
        ->assertTableActionVisible('view', $assignment);
});

// ── Filter ───────────────────────────────────────────────────────────────

it('filter sedang berlaku menyembunyikan assignment lama & yang belum mulai', function () {
    $karyawanB = Karyawan::factory()->rotasi()->create(['instansi_id' => $this->instansi->id, 'unit_kerja' => 'IGD']);
    $karyawanC = Karyawan::factory()->rotasi()->create(['instansi_id' => $this->instansi->id, 'unit_kerja' => 'IGD']);

    $sedangBerlaku = KaryawanPolaRotasi::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'pola_rotasi_id' => $this->pola->id,
        'tanggal_mulai' => today()->subDays(5)->toDateString(),
        'tanggal_berakhir' => today()->addDays(5)->toDateString(),
    ]);

    $sudahBerakhir = KaryawanPolaRotasi::factory()->create([
        'karyawan_id' => $karyawanB->id,
        'pola_rotasi_id' => $this->pola->id,
        'tanggal_mulai' => today()->subMonths(2)->toDateString(),
        'tanggal_berakhir' => today()->subMonth()->toDateString(),
    ]);

    $belumMulai = KaryawanPolaRotasi::factory()->create([
        'karyawan_id' => $karyawanC->id,
        'pola_rotasi_id' => $this->pola->id,
        'tanggal_mulai' => today()->addMonth()->toDateString(),
        'tanggal_berakhir' => null,
    ]);

    livewire(ListKaryawanPolaRotasis::class)
        ->filterTable('sedang_berlaku')
        ->assertCanSeeTableRecords([$sedangBerlaku])
        ->assertCanNotSeeTableRecords([$sudahBerakhir, $belumMulai]);
});
