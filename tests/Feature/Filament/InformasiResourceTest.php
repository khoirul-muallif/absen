<?php

use App\Filament\Resources\Informasis\Pages\CreateInformasi;
use App\Models\Informasi;
use App\Models\Instansi;
use App\Models\User;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->actingAs($this->admin);

    $this->instansi = Instansi::factory()->create();
});

it('created_by terisi otomatis dari admin yang login, bukan dari form', function () {
    livewire(CreateInformasi::class)
        ->fillForm([
            'instansi_id' => $this->instansi->id,
            'judul' => 'Pemberlakuan Absensi',
            'isi' => 'Diberitahukan kepada seluruh karyawan...',
            'tanggal_terbit' => '2026-11-09',
            'is_aktif' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Informasi::first()->created_by)->toBe($this->admin->id);
});

it('created_by tidak berubah saat pengumuman diedit admin lain', function () {
    $pembuatAsli = User::factory()->create();
    $informasi = Informasi::factory()->for($this->instansi)->create([
        'created_by' => $pembuatAsli->id,
    ]);

    $informasi->update(['judul' => 'Judul direvisi']);

    expect($informasi->fresh()->created_by)->toBe($pembuatAsli->id);
});
