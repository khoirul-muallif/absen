<?php

use App\Filament\Resources\Cutis\Pages\CreateCuti;
use App\Filament\Resources\Cutis\Pages\ListCutis;
use App\Models\Cuti;
use App\Models\Dinas;
use App\Models\Instansi;
use App\Models\JenisCuti;
use App\Models\Karyawan;
use App\Models\KuotaCuti;
use App\Models\User;
use App\Exceptions\KuotaCutiTidakCukupException;

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

// --- Sisi Approval ---

it('approve cuti berhasil dan memotong kuota', function () {
    $jenisCuti = JenisCuti::factory()->create(['potong_kuota' => true]);
    $kuota = KuotaCuti::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'jenis_cuti_id' => $jenisCuti->id,
        'tahun' => 2026,
        'kuota' => 12,
        'terpakai' => 0,
    ]);

    $cuti = Cuti::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'jenis_cuti_id' => $jenisCuti->id,
        'tanggal_mulai' => '2026-08-01',
        'tanggal_selesai' => '2026-08-03',
        'jumlah_hari' => 3,
        'status' => 'pending',
    ]);

    $cuti->approve(User::first());

    expect($cuti->fresh()->status)->toBe('approved');
    expect($kuota->fresh()->terpakai)->toBe(3);
});

it('approve gagal & throw exception saat kuota benar-benar tidak cukup', function () {
    $jenisCuti = JenisCuti::factory()->create(['potong_kuota' => true]);
    KuotaCuti::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'jenis_cuti_id' => $jenisCuti->id,
        'tahun' => 2026,
        'kuota' => 5,
        'terpakai' => 4, // sisa cuma 1
    ]);

    $cuti = Cuti::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'jenis_cuti_id' => $jenisCuti->id,
        'tanggal_mulai' => '2026-08-01',
        'tanggal_selesai' => '2026-08-05', // 5 hari, sisa cuma 1
        'jumlah_hari' => 5,
        'status' => 'pending',
    ]);

    expect(fn () => $cuti->approve(User::first()))
        ->toThrow(KuotaCutiTidakCukupException::class);

    expect($cuti->fresh()->status)->toBe('pending'); // rollback, tidak berubah
});

// --- Boundary: sisa kuota pas-pasan (bukan lebih/kurang) ---

it('mengizinkan pengajuan cuti saat jumlah_hari tepat sama dengan sisa kuota', function () {
    $jenisCuti = JenisCuti::factory()->create(['potong_kuota' => true]);
    KuotaCuti::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'jenis_cuti_id' => $jenisCuti->id,
        'tahun' => 2026,
        'kuota' => 5,
        'terpakai' => 2, // sisa tepat 3
    ]);

    livewire(CreateCuti::class)
        ->fillForm([
            'karyawan_id' => $this->karyawan->id,
            'jenis_cuti_id' => $jenisCuti->id,
            'tanggal_mulai' => '2026-08-01',
            'tanggal_selesai' => '2026-08-03', // pas 3 hari
            'alasan' => 'Tes kuota pas-pasan',
            'jumlah_hari' => 3,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Cuti::where('karyawan_id', $this->karyawan->id)->exists())->toBeTrue();
});

// --- Regression guard: kebijakan fase 22 (kuota belum ada row = lolos) ---

it('mengizinkan pengajuan cuti besar walau KuotaCuti belum ada row sama sekali (kebijakan fase 22)', function () {
    $jenisCuti = JenisCuti::factory()->create(['potong_kuota' => true]);
    // sengaja TIDAK bikin KuotaCuti sama sekali

    livewire(CreateCuti::class)
        ->fillForm([
            'karyawan_id' => $this->karyawan->id,
            'jenis_cuti_id' => $jenisCuti->id,
            'tanggal_mulai' => '2026-08-01',
            'tanggal_selesai' => '2026-08-10', // 10 hari, tanpa row kuota
            'alasan' => 'Tes kuota belum ada row',
            'jumlah_hari' => 10,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Cuti::where('karyawan_id', $this->karyawan->id)->exists())->toBeTrue();
});

// --- EditAction visibility ---

it('EditAction visible untuk cuti pending, hidden untuk approved', function () {
    $pending = Cuti::factory()->create(['karyawan_id' => $this->karyawan->id, 'status' => 'pending']);

    livewire(ListCutis::class)
        ->assertTableActionVisible('edit', $pending);
});

it('EditAction hidden untuk cuti approved', function () {
    $approved = Cuti::factory()->create(['karyawan_id' => $this->karyawan->id, 'status' => 'approved']);

    livewire(ListCutis::class)
        ->assertTableActionHidden('edit', $approved);
});

// --- Lampiran conditional required ---

it('mewajibkan lampiran kalau jenis cuti perlu_lampiran = true', function () {
    $jenisCuti = JenisCuti::factory()->create(['perlu_lampiran' => true]);

    livewire(CreateCuti::class)
        ->fillForm([
            'karyawan_id' => $this->karyawan->id,
            'jenis_cuti_id' => $jenisCuti->id,
            'tanggal_mulai' => '2026-08-01',
            'tanggal_selesai' => '2026-08-02',
            'alasan' => 'Tes lampiran wajib',
            'jumlah_hari' => 2,
            'lampiran' => null,
        ])
        ->call('create')
        ->assertHasFormErrors(['lampiran']);
});

it('tidak mewajibkan lampiran kalau jenis cuti perlu_lampiran = false', function () {
    $jenisCuti = JenisCuti::factory()->create(['perlu_lampiran' => false]);

    livewire(CreateCuti::class)
        ->fillForm([
            'karyawan_id' => $this->karyawan->id,
            'jenis_cuti_id' => $jenisCuti->id,
            'tanggal_mulai' => '2026-08-01',
            'tanggal_selesai' => '2026-08-02',
            'alasan' => 'Tes lampiran tidak wajib',
            'jumlah_hari' => 2,
            'lampiran' => null,
        ])
        ->call('create')
        ->assertHasNoFormErrors();
});
