<?php

use App\Filament\Resources\KaryawanPolaRotasis\Pages\CreateKaryawanPolaRotasi;
use App\Filament\Resources\KaryawanPolaRotasis\Pages\ListKaryawanPolaRotasis;
use App\Models\Instansi;
use App\Models\Karyawan;
use App\Models\KaryawanPolaRotasi;
use App\Models\PolaRotasi;
use App\Models\Shift;

use function Pest\Livewire\livewire;

beforeEach(function () {
    actingAsAdmin();
});

// ── List page ────────────────────────────────────────────────────────────

it('menampilkan daftar assignment pola rotasi karyawan', function () {
    $records = KaryawanPolaRotasi::factory()->count(3)->create();

    livewire(ListKaryawanPolaRotasis::class)
        ->assertCanSeeTableRecords($records);
});

// ── Create ───────────────────────────────────────────────────────────────

it('bisa membuat assignment pola rotasi dengan data valid', function () {
    $instansi = Instansi::factory()->create();
    $shift = Shift::factory()->create(['instansi_id' => $instansi->id]);
    $pola = PolaRotasi::factory()->create([
        'instansi_id' => $instansi->id,
        'langkah' => [['shift_id' => $shift->id, 'libur' => false]],
    ]);
    $karyawan = Karyawan::factory()->rotasi()->create(['instansi_id' => $instansi->id]);

    $tanggalMulai = today()->addDays(3);

    livewire(CreateKaryawanPolaRotasi::class)
        ->fillForm([
            'karyawan_id' => $karyawan->id,
            'pola_rotasi_id' => $pola->id,
            'tanggal_mulai' => $tanggalMulai->toDateString(),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $record = KaryawanPolaRotasi::where('karyawan_id', $karyawan->id)->first();

    expect($record)->not->toBeNull()
        ->and($record->pola_rotasi_id)->toBe($pola->id)
        ->and($record->tanggal_mulai->toDateString())->toBe($tanggalMulai->toDateString())
        ->and($record->tanggal_berakhir)->toBeNull();
});

it('menolak tanggal_berakhir sebelum tanggal_mulai', function () {
    $instansi = Instansi::factory()->create();
    $pola = PolaRotasi::factory()->create(['instansi_id' => $instansi->id]);
    $karyawan = Karyawan::factory()->rotasi()->create(['instansi_id' => $instansi->id]);

    livewire(CreateKaryawanPolaRotasi::class)
        ->fillForm([
            'karyawan_id' => $karyawan->id,
            'pola_rotasi_id' => $pola->id,
            'tanggal_mulai' => today()->addDays(10)->toDateString(),
            'tanggal_berakhir' => today()->addDays(5)->toDateString(),
        ])
        ->call('create')
        ->assertHasFormErrors(['tanggal_berakhir' => 'after']);
});

it('menolak karyawan_id kosong', function () {
    $instansi = Instansi::factory()->create();
    $pola = PolaRotasi::factory()->create(['instansi_id' => $instansi->id]);

    livewire(CreateKaryawanPolaRotasi::class)
        ->fillForm([
            'karyawan_id' => null,
            'pola_rotasi_id' => $pola->id,
            'tanggal_mulai' => today()->toDateString(),
        ])
        ->call('create')
        ->assertHasFormErrors(['karyawan_id' => 'required']);
});

test('menolak karyawan_id yang bertipe umum walau ID valid (guard server-side)', function () {
    $karyawanUmum = Karyawan::factory()->umum()->create();
    $pola = PolaRotasi::factory()->create();

    livewire(CreateKaryawanPolaRotasi::class)
        ->fillForm([
            'karyawan_id' => $karyawanUmum->id,
            'pola_rotasi_id' => $pola->id,
            'tanggal_mulai' => '2026-08-01',
        ])
        ->call('create')
        ->assertHasFormErrors(['karyawan_id']);

    expect(KaryawanPolaRotasi::where('karyawan_id', $karyawanUmum->id)->exists())->toBeFalse();
});

