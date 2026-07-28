<?php

use App\Filament\Resources\Dinas\Pages\CreateDinas;
use App\Filament\Resources\Dinas\Pages\ListDinas;
use App\Models\Cuti;
use App\Models\Dinas;
use App\Models\Instansi;
use App\Models\JenisCuti;
use App\Models\Karyawan;

use function Pest\Livewire\livewire;

beforeEach(function () {
    actingAsAdmin();
    $this->instansi = Instansi::factory()->create();
    $this->karyawan = Karyawan::factory()->create(['instansi_id' => $this->instansi->id]);
});

it('menampilkan daftar dinas', function () {
    $records = Dinas::factory()->count(3)->create();

    livewire(ListDinas::class)
        ->assertCanSeeTableRecords($records);
});

it('admin boleh membuat dinas untuk tanggal yang sudah lewat', function () {
    livewire(CreateDinas::class)
        ->fillForm([
            'karyawan_id' => $this->karyawan->id,
            'tanggal_mulai' => today()->subDays(5)->toDateString(),
            'tanggal_selesai' => today()->subDays(3)->toDateString(),
            'tujuan' => 'Semarang',
            'keperluan' => 'Entri susulan oleh admin',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Dinas::where('karyawan_id', $this->karyawan->id)->exists())->toBeTrue();
});

it('menolak tanggal_selesai sebelum tanggal_mulai (rentang terbalik)', function () {
    livewire(CreateDinas::class)
        ->fillForm([
            'karyawan_id' => $this->karyawan->id,
            'tanggal_mulai' => '2026-08-10',
            'tanggal_selesai' => '2026-08-05',
            'tujuan' => 'Jakarta',
            'keperluan' => 'Tes rentang terbalik',
        ])
        ->call('create')
        ->assertHasFormErrors(['tanggal_selesai']);

    expect(Dinas::where('karyawan_id', $this->karyawan->id)->exists())->toBeFalse();
});

it('menolak rentang dinas yang melintasi pergantian tahun', function () {
    livewire(CreateDinas::class)
        ->fillForm([
            'karyawan_id' => $this->karyawan->id,
            'tanggal_mulai' => '2026-12-30',
            'tanggal_selesai' => '2027-01-02',
            'tujuan' => 'Jakarta',
            'keperluan' => 'Tes lintas tahun',
        ])
        ->call('create')
        ->assertHasFormErrors(['tanggal_selesai']);

    expect(Dinas::where('karyawan_id', $this->karyawan->id)->exists())->toBeFalse();
});

it('menolak dinas yang bentrok dengan cuti approved milik karyawan yang sama', function () {
    $jenisCuti = JenisCuti::factory()->create();
    Cuti::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'jenis_cuti_id' => $jenisCuti->id,
        'tanggal_mulai' => '2026-08-01',
        'tanggal_selesai' => '2026-08-05',
        'status' => 'approved',
    ]);

    livewire(CreateDinas::class)
        ->fillForm([
            'karyawan_id' => $this->karyawan->id,
            'tanggal_mulai' => '2026-08-03',
            'tanggal_selesai' => '2026-08-07',
            'tujuan' => 'Jakarta',
            'keperluan' => 'Tes bentrok cuti',
        ])
        ->call('create')
        ->assertHasFormErrors(['tanggal_selesai']);

    expect(Dinas::where('karyawan_id', $this->karyawan->id)->exists())->toBeFalse();
});
