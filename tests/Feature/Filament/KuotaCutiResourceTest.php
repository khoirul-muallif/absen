<?php

// tests/Feature/Filament/KuotaCutiResourceTest.php

use App\Filament\Resources\KuotaCutis\Pages\CreateKuotaCuti;
use App\Filament\Resources\KuotaCutis\Pages\ListKuotaCutis;
use App\Models\JenisCuti;
use App\Models\Karyawan;
use App\Models\KuotaCuti;

use function Pest\Livewire\livewire;

beforeEach(function () {
    actingAsAdmin();
    $this->karyawan = Karyawan::factory()->create();
    $this->jenisCuti = JenisCuti::factory()->create(['default_kuota' => 12]);
});

it('menampilkan daftar kuota cuti', function () {
    $records = KuotaCuti::factory()->count(3)->create();

    livewire(ListKuotaCutis::class)
        ->assertCanSeeTableRecords($records);
});

it('bisa membuat kuota cuti dengan data valid', function () {
    livewire(CreateKuotaCuti::class)
        ->fillForm([
            'karyawan_id' => $this->karyawan->id,
            'jenis_cuti_id' => $this->jenisCuti->id,
            'tahun' => 2026,
            'kuota' => 12,
            'terpakai' => 0,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(KuotaCuti::where('karyawan_id', $this->karyawan->id)->exists())->toBeTrue();
});

it('menolak duplikat kuota untuk karyawan+jenis_cuti+tahun yang sama', function () {
    KuotaCuti::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'jenis_cuti_id' => $this->jenisCuti->id,
        'tahun' => 2026,
    ]);

    livewire(CreateKuotaCuti::class)
        ->fillForm([
            'karyawan_id' => $this->karyawan->id,
            'jenis_cuti_id' => $this->jenisCuti->id,
            'tahun' => 2026,
            'kuota' => 12,
            'terpakai' => 0,
        ])
        ->call('create')
        ->assertHasFormErrors(['tahun']);

    expect(KuotaCuti::where('karyawan_id', $this->karyawan->id)
        ->where('jenis_cuti_id', $this->jenisCuti->id)
        ->where('tahun', 2026)
        ->count())->toBe(1);
});

it('mengizinkan kuota sama untuk tahun yang berbeda', function () {
    KuotaCuti::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'jenis_cuti_id' => $this->jenisCuti->id,
        'tahun' => 2025,
    ]);

    livewire(CreateKuotaCuti::class)
        ->fillForm([
            'karyawan_id' => $this->karyawan->id,
            'jenis_cuti_id' => $this->jenisCuti->id,
            'tahun' => 2026, // tahun beda, harus boleh
            'kuota' => 12,
            'terpakai' => 0,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(KuotaCuti::where('karyawan_id', $this->karyawan->id)
        ->where('jenis_cuti_id', $this->jenisCuti->id)
        ->count())->toBe(2);
});

it('mengizinkan edit record tanpa kena unique constraint dirinya sendiri', function () {
    $kuota = KuotaCuti::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'jenis_cuti_id' => $this->jenisCuti->id,
        'tahun' => 2026,
        'kuota' => 12,
        'terpakai' => 0,
    ]);

    livewire(\App\Filament\Resources\KuotaCutis\Pages\EditKuotaCuti::class, [
        'record' => $kuota->id,
    ])
        ->fillForm([
            'karyawan_id' => $this->karyawan->id,
            'jenis_cuti_id' => $this->jenisCuti->id,
            'tahun' => 2026, // sama persis, harusnya boleh (edit diri sendiri)
            'kuota' => 15,
            'terpakai' => 2,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($kuota->fresh())->kuota->toBe(15)->and($kuota->fresh()->terpakai)->toBe(2);
});
