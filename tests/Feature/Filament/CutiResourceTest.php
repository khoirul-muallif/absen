<?php

use App\Filament\Resources\Cutis\Pages\CreateCuti;
use App\Filament\Resources\Cutis\Pages\ListCutis;
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
    $this->jenisCuti = JenisCuti::factory()->create();
});

it('menampilkan daftar cuti', function () {
    $records = Cuti::factory()->count(3)->create();

    livewire(ListCutis::class)
        ->assertCanSeeTableRecords($records);
});

it('admin boleh membuat cuti untuk tanggal yang sudah lewat', function () {
    livewire(CreateCuti::class)
        ->fillForm([
            'karyawan_id' => $this->karyawan->id,
            'jenis_cuti_id' => $this->jenisCuti->id,
            'tanggal_mulai' => today()->subDays(5)->toDateString(),
            'tanggal_selesai' => today()->subDays(3)->toDateString(),
            'alasan' => 'Entri susulan oleh admin',
            'jumlah_hari' => 3,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Cuti::where('karyawan_id', $this->karyawan->id)->exists())->toBeTrue();
});

it('menolak tanggal_selesai sebelum tanggal_mulai (rentang terbalik)', function () {
    livewire(CreateCuti::class)
        ->fillForm([
            'karyawan_id' => $this->karyawan->id,
            'jenis_cuti_id' => $this->jenisCuti->id,
            'tanggal_mulai' => '2026-08-10',
            'tanggal_selesai' => '2026-08-05',
            'alasan' => 'Tes rentang terbalik',
            'jumlah_hari' => 1,
        ])
        ->call('create')
        ->assertHasFormErrors(['tanggal_selesai']);

    expect(Cuti::where('karyawan_id', $this->karyawan->id)->exists())->toBeFalse();
});

it('menolak rentang cuti yang melintasi pergantian tahun', function () {
    livewire(CreateCuti::class)
        ->fillForm([
            'karyawan_id' => $this->karyawan->id,
            'jenis_cuti_id' => $this->jenisCuti->id,
            'tanggal_mulai' => '2026-12-30',
            'tanggal_selesai' => '2027-01-02',
            'alasan' => 'Tes lintas tahun',
            'jumlah_hari' => 4,
        ])
        ->call('create')
        ->assertHasFormErrors(['tanggal_selesai']);

    expect(Cuti::where('karyawan_id', $this->karyawan->id)->exists())->toBeFalse();
});

it('menolak cuti yang bentrok dengan dinas approved milik karyawan yang sama', function () {
    Dinas::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'tanggal_mulai' => '2026-08-01',
        'tanggal_selesai' => '2026-08-05',
        'status' => 'approved',
    ]);

    livewire(CreateCuti::class)
        ->fillForm([
            'karyawan_id' => $this->karyawan->id,
            'jenis_cuti_id' => $this->jenisCuti->id,
            'tanggal_mulai' => '2026-08-03',
            'tanggal_selesai' => '2026-08-07',
            'alasan' => 'Tes bentrok dinas',
            'jumlah_hari' => 5,
        ])
        ->call('create')
        ->assertHasFormErrors(['tanggal_selesai']);

    expect(Cuti::where('karyawan_id', $this->karyawan->id)->exists())->toBeFalse();
});
