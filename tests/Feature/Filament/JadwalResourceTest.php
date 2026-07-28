<?php

use App\Filament\Resources\Jadwals\Pages\CreateJadwal;
use App\Filament\Resources\Jadwals\Pages\EditJadwal;
use App\Filament\Resources\Jadwals\Pages\ListJadwals;
use App\Models\Cuti;
use App\Models\Dinas;
use App\Models\Instansi;
use App\Models\Jadwal;
use App\Models\Karyawan;
use App\Models\Shift;

use function Pest\Livewire\livewire;

beforeEach(function () {
    actingAsAdmin();
    $this->instansi = Instansi::factory()->create();
    $this->karyawan = Karyawan::factory()->create(['instansi_id' => $this->instansi->id]);
    $this->shift = Shift::factory()->create(['instansi_id' => $this->instansi->id]);
});

// ── List page ────────────────────────────────────────────────────────────

it('menampilkan daftar jadwal', function () {
    $records = Jadwal::factory()->count(3)->create();

    livewire(ListJadwals::class)
        ->assertCanSeeTableRecords($records);
});

// ── Field jenis: disabled kalau hasil sync cuti/dinas ───────────────────

it('field jenis disabled saat edit jadwal yang jenisnya cuti', function () {
    $jadwal = Jadwal::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'shift_id' => null,
        'jenis' => 'cuti',
        'sumber' => 'generate',
    ]);

    livewire(EditJadwal::class, ['record' => $jadwal->getRouteKey()])
        ->assertFormFieldIsDisabled('jenis');
});

it('field jenis disabled saat edit jadwal yang jenisnya dinas', function () {
    $jadwal = Jadwal::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'shift_id' => null,
        'jenis' => 'dinas',
        'sumber' => 'generate',
    ]);

    livewire(EditJadwal::class, ['record' => $jadwal->getRouteKey()])
        ->assertFormFieldIsDisabled('jenis');
});

it('field jenis tetap enabled untuk jadwal reguler/piket/libur biasa', function () {
    $jadwal = Jadwal::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'shift_id' => $this->shift->id,
        'jenis' => 'reguler',
        'sumber' => 'manual',
    ]);

    livewire(EditJadwal::class, ['record' => $jadwal->getRouteKey()])
        ->assertFormFieldIsEnabled('jenis');
});

// ── Create: validasi bentrok cuti/dinas approved yang sudah ada ─────────

it('menolak membuat jadwal manual di tanggal yang bentrok cuti approved', function () {
    Cuti::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'status' => 'approved',
        'tanggal_mulai' => '2026-08-01',
        'tanggal_selesai' => '2026-08-03',
    ]);

    livewire(CreateJadwal::class)
        ->fillForm([
            'karyawan_id' => $this->karyawan->id,
            'tanggal' => '2026-08-02',
            'jenis' => 'reguler',
            'shift_id' => $this->shift->id,
        ])
        ->call('create')
        ->assertHasFormErrors(['tanggal']);

    expect(Jadwal::where('karyawan_id', $this->karyawan->id)
        ->whereDate('tanggal', '2026-08-02')->exists())->toBeFalse();
});

it('menolak membuat jadwal manual di tanggal yang bentrok dinas approved', function () {
    Dinas::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'status' => 'approved',
        'tanggal_mulai' => '2026-08-10',
        'tanggal_selesai' => '2026-08-10',
    ]);

    livewire(CreateJadwal::class)
        ->fillForm([
            'karyawan_id' => $this->karyawan->id,
            'tanggal' => '2026-08-10',
            'jenis' => 'reguler',
            'shift_id' => $this->shift->id,
        ])
        ->call('create')
        ->assertHasFormErrors(['tanggal']);
});

it('bisa membuat jadwal manual biasa tanpa bentrok', function () {
    livewire(CreateJadwal::class)
        ->fillForm([
            'karyawan_id' => $this->karyawan->id,
            'tanggal' => '2026-09-01',
            'jenis' => 'reguler',
            'shift_id' => $this->shift->id,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Jadwal::where('karyawan_id', $this->karyawan->id)
        ->whereDate('tanggal', '2026-09-01')->exists())->toBeTrue();
});
