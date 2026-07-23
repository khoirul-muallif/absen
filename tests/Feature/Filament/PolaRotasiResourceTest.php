<?php

use App\Filament\Resources\PolaRotasis\Pages\CreatePolaRotasi;
use App\Filament\Resources\PolaRotasis\Pages\ListPolaRotasis;
use App\Models\Instansi;
use App\Models\PolaRotasi;
use App\Models\Shift;

use function Pest\Livewire\livewire;

beforeEach(function () {
    actingAsAdmin();
});

// ── List page ────────────────────────────────────────────────────────────

it('menampilkan daftar pola rotasi', function () {
    $records = PolaRotasi::factory()->count(3)->create();

    livewire(ListPolaRotasis::class)
        ->assertCanSeeTableRecords($records);
});

// ── Create ───────────────────────────────────────────────────────────────

it('bisa membuat pola rotasi dengan langkah campuran shift & libur', function () {
    $instansi = Instansi::factory()->create();
    $shiftPagi = Shift::factory()->create(['instansi_id' => $instansi->id]);
    $shiftMalam = Shift::factory()->create(['instansi_id' => $instansi->id]);

    livewire(CreatePolaRotasi::class)
        ->fillForm([
            'instansi_id' => $instansi->id,
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
    $instansi = Instansi::factory()->create();

    livewire(CreatePolaRotasi::class)
        ->fillForm([
            'instansi_id' => $instansi->id,
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
    $instansi = Instansi::factory()->create();

    livewire(CreatePolaRotasi::class)
        ->fillForm([
            'instansi_id' => $instansi->id,
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
    $instansi = Instansi::factory()->create();

    livewire(CreatePolaRotasi::class)
        ->fillForm([
            'instansi_id' => $instansi->id,
            'unit_kerja' => 'IGD',
            'nama_pola' => 'Rotasi Libur Saja',
            'langkah' => [
                ['shift_id' => null, 'libur' => true],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();
});
