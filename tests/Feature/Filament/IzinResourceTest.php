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

it('menolak izin tanpa rencana jam kembali', function () {
    livewire(CreateIzin::class)
        ->fillForm([
            'karyawan_id' => $this->karyawan->id,
            'tanggal' => today()->toDateString(),
            'jam_keluar' => '13:00',
            'jam_kembali_rencana' => null,
            'keperluan' => 'Urusan keluarga mendadak',
        ])
        ->call('create')
        ->assertHasFormErrors(['jam_kembali_rencana']);
});

it('menolak jam_kembali sebelum atau sama dengan jam_keluar', function () {
    livewire(CreateIzin::class)
        ->fillForm([
            'karyawan_id' => $this->karyawan->id,
            'tanggal' => today()->toDateString(),
            'jam_keluar' => '13:00',
            'jam_kembali_rencana' => '12:00',
            'keperluan' => 'Tes validasi',
        ])
        ->call('create')
        ->assertHasFormErrors(['jam_kembali_rencana']);

    expect(Izin::where('karyawan_id', $this->karyawan->id)->exists())->toBeFalse();
});

it('menerima jam_kembali setelah jam_keluar', function () {
    livewire(CreateIzin::class)
        ->fillForm([
            'karyawan_id' => $this->karyawan->id,
            'tanggal' => today()->toDateString(),
            'jam_keluar' => '13:00',
            'jam_kembali_rencana' => '15:00',
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

it('menyimpan jam_kembali_rencana ke DB, bukan membuangnya diam-diam', function () {
    livewire(CreateIzin::class)
        ->fillForm([
            'karyawan_id' => $this->karyawan->id,
            'tanggal' => today()->toDateString(),
            'jam_keluar' => '13:00',
            'jam_kembali_rencana' => '15:00',
            'keperluan' => 'Tes simpan',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    // assertHasNoFormErrors saja TIDAK cukup: sebelum fase ini, form
    // menulis ke kolom yang sudah tidak ada di $fillable dan nilainya
    // hilang tanpa error apa pun. Testnya tetap hijau.
    expect(Izin::where('karyawan_id', $this->karyawan->id)->first())
        ->jam_kembali_rencana->toBe('15:00:00')
        ->jam_kembali_aktual->toBeNull();
});
