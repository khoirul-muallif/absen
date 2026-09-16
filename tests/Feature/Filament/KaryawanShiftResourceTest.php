<?php

use App\Filament\Resources\KaryawanShifts\Pages\CreateKaryawanShift;
use App\Filament\Resources\KaryawanShifts\Pages\EditKaryawanShift;
use App\Filament\Resources\KaryawanShifts\Pages\ListKaryawanShifts;
use App\Models\Instansi;
use App\Models\Karyawan;
use App\Models\KaryawanShift;
use App\Models\Shift;

use function Pest\Livewire\livewire;

beforeEach(function () {
    actingAsAdmin();

    // Instansi dibuat eksplisit dan dipakai bersama karyawan + shift.
    // Sejak fase 31, dropdown shift dibatasi ke instansi karyawan, jadi
    // fixture yang instansinya tidak nyambung akan ditolak form.
    $this->instansi = Instansi::factory()->create();
    $this->karyawan = Karyawan::factory()->umum()->create(['instansi_id' => $this->instansi->id]);
    $this->shift = Shift::factory()->create(['instansi_id' => $this->instansi->id]);
});

// ── List & create dasar ──────────────────────────────────────────────────

test('menampilkan daftar assignment shift karyawan umum', function () {
    $records = KaryawanShift::factory()->count(3)->create();

    // Sebelumnya cuma assertSuccessful() — tidak memeriksa isi tabelnya
    // sama sekali, jadi halaman kosong pun lolos.
    livewire(ListKaryawanShifts::class)
        ->assertCanSeeTableRecords($records);
});

test('bisa membuat assignment shift dengan data valid', function () {
    livewire(CreateKaryawanShift::class)
        ->fillForm([
            'karyawan_id' => $this->karyawan->id,
            'shift_id' => $this->shift->id,
            'tanggal_berlaku' => '2026-08-01',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(KaryawanShift::where('karyawan_id', $this->karyawan->id)
        ->where('shift_id', $this->shift->id)
        ->exists())->toBeTrue();
});

test('menolak tanggal_berakhir sebelum tanggal_berlaku', function () {
    livewire(CreateKaryawanShift::class)
        ->fillForm([
            'karyawan_id' => $this->karyawan->id,
            'shift_id' => $this->shift->id,
            'tanggal_berlaku' => '2026-08-10',
            'tanggal_berakhir' => '2026-08-05',
        ])
        ->call('create')
        ->assertHasFormErrors(['tanggal_berakhir']);
});

test('menolak karyawan_id kosong', function () {
    livewire(CreateKaryawanShift::class)
        ->fillForm([
            'karyawan_id' => null,
            'shift_id' => $this->shift->id,
            'tanggal_berlaku' => '2026-08-01',
        ])
        ->call('create')
        ->assertHasFormErrors(['karyawan_id']);
});

test('menolak karyawan_id yang bertipe rotasi walau ID valid (guard server-side)', function () {
    $karyawanRotasi = Karyawan::factory()->rotasi()->create(['instansi_id' => $this->instansi->id]);

    livewire(CreateKaryawanShift::class)
        ->fillForm([
            'karyawan_id' => $karyawanRotasi->id,
            'shift_id' => $this->shift->id,
            'tanggal_berlaku' => '2026-08-01',
        ])
        ->call('create')
        ->assertHasFormErrors(['karyawan_id']);

    expect(KaryawanShift::where('karyawan_id', $karyawanRotasi->id)->exists())->toBeFalse();
});

// ── Guard instansi pada shift ────────────────────────────────────────────
//
// Sebelumnya dropdown shift tidak dibatasi sama sekali — karyawan bisa
// di-assign shift milik instansi lain tanpa ada yang menolak. Sisi shift
// terlewat waktu guard karyawan_id dipasang di fase 18.

test('menolak shift milik instansi lain', function () {
    $instansiLain = Instansi::factory()->create();
    $shiftLain = Shift::factory()->create(['instansi_id' => $instansiLain->id]);

    livewire(CreateKaryawanShift::class)
        ->fillForm([
            'karyawan_id' => $this->karyawan->id,
            'shift_id' => $shiftLain->id,
            'tanggal_berlaku' => '2026-08-01',
        ])
        ->call('create')
        ->assertHasFormErrors(['shift_id']);

    expect(KaryawanShift::where('shift_id', $shiftLain->id)->exists())->toBeFalse();
});

// ── Periode beririsan ────────────────────────────────────────────────────
//
// KEPUTUSAN fase 31: satu karyawan umum tidak boleh punya dua assignment
// yang periodenya beririsan. AbsensiController::masuk() memilih lewat
// latest('tanggal_berlaku')->first(), jadi dua assignment yang beririsan
// bikin shift mana yang dipakai tidak deterministik.

test('menolak assignment yang periodenya beririsan dengan yang sudah ada', function () {
    KaryawanShift::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'shift_id' => $this->shift->id,
        'tanggal_berlaku' => '2026-08-01',
        'tanggal_berakhir' => '2026-08-31',
    ]);

    $shiftLain = Shift::factory()->create(['instansi_id' => $this->instansi->id]);

    livewire(CreateKaryawanShift::class)
        ->fillForm([
            'karyawan_id' => $this->karyawan->id,
            'shift_id' => $shiftLain->id,
            'tanggal_berlaku' => '2026-08-15', // masuk ke dalam periode lama
            'tanggal_berakhir' => '2026-09-15',
        ])
        ->call('create')
        ->assertHasFormErrors(['tanggal_berlaku']);

    expect(KaryawanShift::where('karyawan_id', $this->karyawan->id)->count())->toBe(1);
});

test('MENGIZINKAN transisi berurutan tanpa irisan', function () {
    KaryawanShift::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'shift_id' => $this->shift->id,
        'tanggal_berlaku' => '2026-07-01',
        'tanggal_berakhir' => '2026-07-31',
    ]);

    $shiftLain = Shift::factory()->create(['instansi_id' => $this->instansi->id]);

    livewire(CreateKaryawanShift::class)
        ->fillForm([
            'karyawan_id' => $this->karyawan->id,
            'shift_id' => $shiftLain->id,
            'tanggal_berlaku' => '2026-08-01', // tepat sehari setelah yang lama berakhir
            'tanggal_berakhir' => '2026-08-31',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(KaryawanShift::where('karyawan_id', $this->karyawan->id)->count())->toBe(2);
});

test('menolak assignment baru kalau ada yang open-ended dan belum diakhiri', function () {
    KaryawanShift::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'shift_id' => $this->shift->id,
        'tanggal_berlaku' => '2026-01-01',
        'tanggal_berakhir' => null, // berlaku sampai diganti
    ]);

    $shiftLain = Shift::factory()->create(['instansi_id' => $this->instansi->id]);

    livewire(CreateKaryawanShift::class)
        ->fillForm([
            'karyawan_id' => $this->karyawan->id,
            'shift_id' => $shiftLain->id,
            'tanggal_berlaku' => '2026-08-01',
        ])
        ->call('create')
        ->assertHasFormErrors(['tanggal_berlaku']);
});

test('irisan hanya dicek per karyawan, bukan lintas karyawan', function () {
    $karyawanLain = Karyawan::factory()->umum()->create(['instansi_id' => $this->instansi->id]);

    KaryawanShift::factory()->create([
        'karyawan_id' => $karyawanLain->id,
        'shift_id' => $this->shift->id,
        'tanggal_berlaku' => '2026-08-01',
        'tanggal_berakhir' => '2026-08-31',
    ]);

    livewire(CreateKaryawanShift::class)
        ->fillForm([
            'karyawan_id' => $this->karyawan->id,
            'shift_id' => $this->shift->id,
            'tanggal_berlaku' => '2026-08-01',
            'tanggal_berakhir' => '2026-08-31',
        ])
        ->call('create')
        ->assertHasNoFormErrors();
});

test('edit tidak kena aturan irisan dirinya sendiri', function () {
    $assignment = KaryawanShift::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'shift_id' => $this->shift->id,
        'tanggal_berlaku' => '2026-08-01',
        'tanggal_berakhir' => '2026-08-31',
    ]);

    livewire(EditKaryawanShift::class, ['record' => $assignment->getRouteKey()])
        ->fillForm(['tanggal_berakhir' => '2026-09-30'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($assignment->fresh()->tanggal_berakhir->toDateString())->toBe('2026-09-30');
});
