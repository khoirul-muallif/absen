<?php

use App\Filament\Resources\Izins\Pages\CreateIzin;
use App\Filament\Resources\Izins\Pages\ListIzins;
use App\Models\Instansi;
use App\Models\Izin;
use App\Models\Karyawan;

use function Pest\Livewire\livewire;

beforeEach(function () {
    actingAsAdmin();
    $this->instansi = Instansi::factory()->create();
    $this->karyawan = Karyawan::factory()->create(['instansi_id' => $this->instansi->id]);
});

it('menampilkan daftar izin', function () {
    $records = Izin::factory()->count(3)->create();

    livewire(ListIzins::class)
        ->assertCanSeeTableRecords($records);
});

it('bisa membuat izin dengan jam_kembali kosong (belum balik)', function () {
    livewire(CreateIzin::class)
        ->fillForm([
            'karyawan_id' => $this->karyawan->id,
            'tanggal' => today()->toDateString(),
            'jam_keluar' => '13:00',
            'jam_kembali' => null,
            'keperluan' => 'Urusan keluarga mendadak',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Izin::where('karyawan_id', $this->karyawan->id)->first())
        ->jam_kembali->toBeNull();
});

it('menolak jam_kembali sebelum atau sama dengan jam_keluar', function () {
    livewire(CreateIzin::class)
        ->fillForm([
            'karyawan_id' => $this->karyawan->id,
            'tanggal' => today()->toDateString(),
            'jam_keluar' => '13:00',
            'jam_kembali' => '12:00',
            'keperluan' => 'Tes validasi',
        ])
        ->call('create')
        ->assertHasFormErrors(['jam_kembali']);

    expect(Izin::where('karyawan_id', $this->karyawan->id)->exists())->toBeFalse();
});

it('menerima jam_kembali setelah jam_keluar', function () {
    livewire(CreateIzin::class)
        ->fillForm([
            'karyawan_id' => $this->karyawan->id,
            'tanggal' => today()->toDateString(),
            'jam_keluar' => '13:00',
            'jam_kembali' => '15:00',
            'keperluan' => 'Tes validasi normal',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Izin::where('karyawan_id', $this->karyawan->id)->exists())->toBeTrue();
});

it('EditAction tetap visible walau status sudah approved (khusus Izin, beda dari Cuti/Lembur)', function () {
    $izinApproved = Izin::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'status' => 'approved',
    ]);

    livewire(ListIzins::class)
        ->assertTableActionVisible('edit', $izinApproved);
});

it('approve izin pending berhasil tanpa exception', function () {
    $izin = Izin::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'status' => 'pending',
    ]);

    $admin = \App\Models\User::first();

    $izin->approve($admin);

    expect($izin->fresh())
        ->status->toBe('approved')
        ->approved_by->toBe($admin->id);
});
