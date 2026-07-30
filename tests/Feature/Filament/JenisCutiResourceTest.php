<?php

// tests/Feature/Filament/JenisCutiResourceTest.php

use App\Filament\Resources\JenisCutis\Pages\CreateJenisCuti;
use App\Filament\Resources\JenisCutis\Pages\ListJenisCutis;
use App\Models\JenisCuti;

use function Pest\Livewire\livewire;

beforeEach(function () {
    actingAsAdmin();
});

it('menampilkan daftar jenis cuti', function () {
    $records = JenisCuti::factory()->count(3)->create();

    livewire(ListJenisCutis::class)
        ->assertCanSeeTableRecords($records);
});

it('bisa membuat jenis cuti dengan data valid', function () {
    livewire(CreateJenisCuti::class)
        ->fillForm([
            'nama' => 'Cuti Melahirkan',
            'default_kuota' => 90,
            'is_tahunan' => false,
            'potong_kuota' => false,
            'perlu_lampiran' => true,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(JenisCuti::where('nama', 'Cuti Melahirkan')->exists())->toBeTrue();
});

it('menolak nama jenis cuti yang duplikat', function () {
    JenisCuti::factory()->create(['nama' => 'Cuti Tahunan']);

    livewire(CreateJenisCuti::class)
        ->fillForm([
            'nama' => 'Cuti Tahunan',
            'default_kuota' => 12,
            'is_tahunan' => true,
            'potong_kuota' => true,
            'perlu_lampiran' => false,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasFormErrors(['nama']);

    expect(JenisCuti::where('nama', 'Cuti Tahunan')->count())->toBe(1);
});

it('mengizinkan edit record tanpa kena unique constraint dirinya sendiri', function () {
    $jenisCuti = JenisCuti::factory()->create(['nama' => 'Cuti Tahunan']);

    livewire(\App\Filament\Resources\JenisCutis\Pages\EditJenisCuti::class, [
        'record' => $jenisCuti->id,
    ])
        ->fillForm([
            'nama' => 'Cuti Tahunan', // nama sama, harusnya boleh (edit diri sendiri)
            'default_kuota' => 15,
            'is_tahunan' => true,
            'potong_kuota' => true,
            'perlu_lampiran' => false,
            'is_active' => true,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($jenisCuti->fresh()->default_kuota)->toBe(15);
});
