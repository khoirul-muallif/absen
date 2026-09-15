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


// ============================================================================
// TAMBAHAN untuk tests/Feature/Filament/JadwalResourceTest.php
//
// Tempel di akhir file. beforeEach yang sudah ada ($this->karyawan,
// $this->shift, $this->instansi) dipakai ulang.
// ============================================================================

// ── Kolom `sumber` (inti batch A) ────────────────────────────────────────
//
// `sumber` menentukan apakah sebuah baris bertahan saat
// `jadwal:generate-rotasi --overwrite-generate` dijalankan, tapi sebelumnya
// tidak muncul di form maupun tabel — dan jadwal yang dibuat admin lewat
// Filament memakai default DB 'generate', sehingga ikut terhapus oleh
// generator. Itu membatalkan alasan kolom ini dibuat di fase 15.

it('jadwal yang dibuat lewat Filament default-nya sumber manual', function () {
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
        ->whereDate('tanggal', '2026-09-01')
        ->first())
        ->sumber->toBe('manual');
});

it('admin bisa mengatur sumber ke generate secara sadar', function () {
    livewire(CreateJadwal::class)
        ->fillForm([
            'karyawan_id' => $this->karyawan->id,
            'tanggal' => '2026-09-02',
            'jenis' => 'reguler',
            'shift_id' => $this->shift->id,
            'sumber' => 'generate',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Jadwal::where('karyawan_id', $this->karyawan->id)
        ->whereDate('tanggal', '2026-09-02')
        ->first())
        ->sumber->toBe('generate');
});

// REGRESSION GUARD untuk keputusan "opsi 3": mengedit baris TIDAK mengubah
// sumber secara otomatis. Admin yang ingin suntingannya dilindungi harus
// mengatur sendiri — supaya tidak ada opt-out senyap yang bikin makin banyak
// baris lepas dari kendali generator tanpa disadari.

it('mengedit baris sumber=generate TIDAK otomatis mengubahnya jadi manual', function () {
    $jadwal = Jadwal::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'shift_id' => $this->shift->id,
        'tanggal' => '2026-09-10',
        'jenis' => 'reguler',
        'sumber' => 'generate',
    ]);

    livewire(EditJadwal::class, ['record' => $jadwal->getRouteKey()])
        ->fillForm(['keterangan' => 'Koreksi typo'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($jadwal->fresh())
        ->keterangan->toBe('Koreksi typo')
        ->sumber->toBe('generate');
});

it('admin bisa mengubah sumber jadi manual lewat form edit', function () {
    $jadwal = Jadwal::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'shift_id' => $this->shift->id,
        'tanggal' => '2026-09-11',
        'jenis' => 'reguler',
        'sumber' => 'generate',
    ]);

    livewire(EditJadwal::class, ['record' => $jadwal->getRouteKey()])
        ->fillForm(['sumber' => 'manual'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($jadwal->fresh()->sumber)->toBe('manual');
});

// ── shift_id tidak lagi required untuk cuti/dinas ────────────────────────
//
// Sebelumnya shift_id required untuk SEMUA jenis kecuali 'libur'. Baris hasil
// sinkronisasi punya jenis cuti/dinas dengan shift_id null, jadi begitu admin
// membukanya dan menekan simpan, form menuntut shift diisi — padahal
// mengisinya justru merusak data.

it('baris cuti hasil sinkronisasi bisa disimpan tanpa shift', function () {
    $jadwal = Jadwal::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'shift_id' => null,
        'tanggal' => '2026-09-15',
        'jenis' => 'cuti',
        'sumber' => 'generate',
    ]);

    livewire(EditJadwal::class, ['record' => $jadwal->getRouteKey()])
        ->fillForm(['keterangan' => 'Dikonfirmasi ke SDM'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($jadwal->fresh())
        ->keterangan->toBe('Dikonfirmasi ke SDM')
        ->jenis->toBe('cuti')
        ->shift_id->toBeNull();
});

// ── Guard diperluas: bukan cuma field `jenis` ────────────────────────────

it('karyawan, tanggal, dan sumber ikut dikunci untuk baris hasil sinkronisasi', function () {
    $jadwal = Jadwal::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'shift_id' => null,
        'tanggal' => '2026-09-16',
        'jenis' => 'dinas',
        'sumber' => 'generate',
    ]);

    livewire(EditJadwal::class, ['record' => $jadwal->getRouteKey()])
        ->assertFormFieldIsDisabled('karyawan_id')
        ->assertFormFieldIsDisabled('tanggal')
        ->assertFormFieldIsDisabled('jenis')
        ->assertFormFieldIsDisabled('sumber');
});

it('baris reguler biasa semua fieldnya tetap bisa diubah', function () {
    $jadwal = Jadwal::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'shift_id' => $this->shift->id,
        'tanggal' => '2026-09-17',
        'jenis' => 'reguler',
        'sumber' => 'manual',
    ]);

    livewire(EditJadwal::class, ['record' => $jadwal->getRouteKey()])
        ->assertFormFieldIsEnabled('karyawan_id')
        ->assertFormFieldIsEnabled('tanggal')
        ->assertFormFieldIsEnabled('jenis')
        ->assertFormFieldIsEnabled('sumber');
});

// ── Opsi cuti/dinas tidak bisa dipilih saat create ───────────────────────
//
// Baris berjenis cuti/dinas lahir dari approval, bukan diketik admin. Versi
// lama menampilkan kedua opsi itu di form create DAN memakai state hidup
// untuk guard disabled — begitu admin memilih "Cuti", field langsung mengunci
// dirinya sendiri dan admin terjebak tidak bisa membatalkan.

it('jenis cuti tidak bisa dipilih saat membuat jadwal baru', function () {
    livewire(CreateJadwal::class)
        ->fillForm([
            'karyawan_id' => $this->karyawan->id,
            'tanggal' => '2026-09-20',
            'jenis' => 'cuti',
        ])
        ->call('create')
        ->assertHasFormErrors(['jenis']);

    expect(Jadwal::where('karyawan_id', $this->karyawan->id)
        ->whereDate('tanggal', '2026-09-20')->exists())->toBeFalse();
});
