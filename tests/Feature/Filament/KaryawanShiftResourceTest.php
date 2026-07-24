<?php

use App\Filament\Resources\KaryawanShifts\Pages\CreateKaryawanShift;
use App\Filament\Resources\KaryawanShifts\Pages\ListKaryawanShifts;
use App\Models\Karyawan;
use App\Models\KaryawanShift;
use App\Models\Shift;

use function Pest\Livewire\livewire;

test('menampilkan daftar assignment shift karyawan umum', function () {
    KaryawanShift::factory()->count(3)->create();

    livewire(ListKaryawanShifts::class)
        ->assertSuccessful();
});

test('bisa membuat assignment shift dengan data valid', function () {
    $karyawan = Karyawan::factory()->umum()->create();
    $shift = Shift::factory()->create();

    livewire(CreateKaryawanShift::class)
        ->fillForm([
            'karyawan_id' => $karyawan->id,
            'shift_id' => $shift->id,
            'tanggal_berlaku' => '2026-08-01',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(KaryawanShift::where('karyawan_id', $karyawan->id)->where('shift_id', $shift->id)->exists())->toBeTrue();
});

test('menolak tanggal_berakhir sebelum tanggal_berlaku', function () {
    $karyawan = Karyawan::factory()->umum()->create();
    $shift = Shift::factory()->create();

    livewire(CreateKaryawanShift::class)
        ->fillForm([
            'karyawan_id' => $karyawan->id,
            'shift_id' => $shift->id,
            'tanggal_berlaku' => '2026-08-10',
            'tanggal_berakhir' => '2026-08-05',
        ])
        ->call('create')
        ->assertHasFormErrors(['tanggal_berakhir']);
});

test('menolak karyawan_id kosong', function () {
    $shift = Shift::factory()->create();

    livewire(CreateKaryawanShift::class)
        ->fillForm([
            'karyawan_id' => null,
            'shift_id' => $shift->id,
            'tanggal_berlaku' => '2026-08-01',
        ])
        ->call('create')
        ->assertHasFormErrors(['karyawan_id']);
});

test('menolak karyawan_id yang bertipe rotasi walau ID valid (guard server-side)', function () {
    $karyawanRotasi = Karyawan::factory()->rotasi()->create();
    $shift = Shift::factory()->create();

    livewire(CreateKaryawanShift::class)
        ->fillForm([
            'karyawan_id' => $karyawanRotasi->id,
            'shift_id' => $shift->id,
            'tanggal_berlaku' => '2026-08-01',
        ])
        ->call('create')
        ->assertHasFormErrors(['karyawan_id']);

    expect(KaryawanShift::where('karyawan_id', $karyawanRotasi->id)->exists())->toBeFalse();
});
