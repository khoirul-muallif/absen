<?php

use App\Filament\Resources\TukarJadwals\Pages\CreateTukarJadwal;
use App\Filament\Resources\TukarJadwals\Pages\ListTukarJadwals;
use App\Models\Jadwal;
use App\Models\Karyawan;
use App\Models\Shift;
use App\Models\TukarJadwal;

use function Pest\Livewire\livewire;

beforeEach(function () {
    actingAsAdmin();
});

// ── List page ────────────────────────────────────────────────────────────

it('menampilkan daftar pengajuan tukar jadwal', function () {
    $records = TukarJadwal::factory()->count(3)->create();

    livewire(ListTukarJadwals::class)
        ->assertCanSeeTableRecords($records);
});

// ── Create: mode tukar ──────────────────────────────────────────────────

it('bisa membuat pengajuan mode tukar dengan data valid', function () {
    $shift = Shift::factory()->create();

    $pengaju = Karyawan::factory()->create();
    $tujuan = Karyawan::factory()->create();

    $jadwalAsal = Jadwal::factory()->create([
        'karyawan_id' => $pengaju->id,
        'shift_id' => $shift->id,
        'tanggal' => today()->addDays(3),
    ]);

    $jadwalTujuan = Jadwal::factory()->create([
        'karyawan_id' => $tujuan->id,
        'shift_id' => $shift->id,
        'tanggal' => today()->addDays(5),
    ]);

    livewire(CreateTukarJadwal::class)
        ->fillForm([
            'mode' => 'tukar',
            'karyawan_pengaju_filter' => $pengaju->id,
            'jadwal_id' => $jadwalAsal->id,
            'karyawan_tujuan_filter' => $tujuan->id,
            'jadwal_tujuan_id' => $jadwalTujuan->id,
            'alasan' => 'Ada keperluan keluarga',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(TukarJadwal::where('jadwal_id', $jadwalAsal->id)->exists())->toBeTrue();

    $record = TukarJadwal::where('jadwal_id', $jadwalAsal->id)->first();

    // Snapshot otomatis dari static::creating() harus terisi
    expect($record->karyawan_pengaju_id)->toBe($pengaju->id)
        ->and($record->karyawan_tujuan_id)->toBe($tujuan->id)
        ->and($record->tanggal_asal->toDateString())->toBe($jadwalAsal->tanggal->toDateString())
        ->and($record->tanggal_tujuan->toDateString())->toBe($jadwalTujuan->tanggal->toDateString())
        ->and($record->status)->toBe('pending');
});

it('menolak jadwal tujuan yang sama dengan jadwal asal', function () {
    $shift = Shift::factory()->create();
    $pengaju = Karyawan::factory()->create();

    $jadwal = Jadwal::factory()->create([
        'karyawan_id' => $pengaju->id,
        'shift_id' => $shift->id,
    ]);

    livewire(CreateTukarJadwal::class)
        ->fillForm([
            'mode' => 'tukar',
            'karyawan_pengaju_filter' => $pengaju->id,
            'jadwal_id' => $jadwal->id,
            'karyawan_tujuan_filter' => $pengaju->id,
            'jadwal_tujuan_id' => $jadwal->id,
            'alasan' => 'Test',
        ])
        ->call('create')
        ->assertHasFormErrors(['jadwal_tujuan_id' => 'different']);
});

it('menolak pengajuan kalau jadwal sudah dipakai pengajuan pending lain', function () {
    $shift = Shift::factory()->create();
    $pengaju = Karyawan::factory()->create();
    $tujuan = Karyawan::factory()->create();

    $jadwalAsal = Jadwal::factory()->create([
        'karyawan_id' => $pengaju->id,
        'shift_id' => $shift->id,
    ]);

    $jadwalTujuan = Jadwal::factory()->create([
        'karyawan_id' => $tujuan->id,
        'shift_id' => $shift->id,
    ]);

    // Pengajuan pending pertama yang sudah memakai jadwalAsal
    TukarJadwal::factory()->create([
        'jadwal_id' => $jadwalAsal->id,
        'status' => 'pending',
    ]);

    livewire(CreateTukarJadwal::class)
        ->fillForm([
            'mode' => 'tukar',
            'karyawan_pengaju_filter' => $pengaju->id,
            'jadwal_id' => $jadwalAsal->id,
            'karyawan_tujuan_filter' => $tujuan->id,
            'jadwal_tujuan_id' => $jadwalTujuan->id,
            'alasan' => 'Test rebutan jadwal',
        ])
        ->call('create')
        ->assertHasFormErrors(['jadwal_id']);
});

it('menolak tukar kalau salah satu karyawan sudah punya jadwal di tanggal pasangannya', function () {
    $shift = Shift::factory()->create();
    $pengaju = Karyawan::factory()->create();
    $tujuan = Karyawan::factory()->create();

    $jadwalAsal = Jadwal::factory()->create([
        'karyawan_id' => $pengaju->id,
        'shift_id' => $shift->id,
        'tanggal' => today()->addDays(3),
    ]);

    $jadwalTujuan = Jadwal::factory()->create([
        'karyawan_id' => $tujuan->id,
        'shift_id' => $shift->id,
        'tanggal' => today()->addDays(5),
    ]);

    // Pengaju sudah punya jadwal lain persis di tanggal milik tujuan -> konflik kalau ditukar
    Jadwal::factory()->create([
        'karyawan_id' => $pengaju->id,
        'shift_id' => $shift->id,
        'tanggal' => $jadwalTujuan->tanggal,
    ]);

    livewire(CreateTukarJadwal::class)
        ->fillForm([
            'mode' => 'tukar',
            'karyawan_pengaju_filter' => $pengaju->id,
            'jadwal_id' => $jadwalAsal->id,
            'karyawan_tujuan_filter' => $tujuan->id,
            'jadwal_tujuan_id' => $jadwalTujuan->id,
            'alasan' => 'Test konflik tanggal',
        ])
        ->call('create')
        ->assertHasFormErrors(['jadwal_tujuan_id']);
});

// ── Create: mode pindah ──────────────────────────────────────────────────

it('bisa membuat pengajuan mode pindah sendiri dengan data valid', function () {
    $shift = Shift::factory()->create();
    $pengaju = Karyawan::factory()->create();

    $jadwal = Jadwal::factory()->create([
        'karyawan_id' => $pengaju->id,
        'shift_id' => $shift->id,
        'tanggal' => today()->addDays(2),
    ]);

    $tanggalBaru = today()->addDays(10);

    livewire(CreateTukarJadwal::class)
        ->fillForm([
            'mode' => 'pindah',
            'karyawan_pengaju_filter' => $pengaju->id,
            'jadwal_id' => $jadwal->id,
            'tanggal_baru' => $tanggalBaru->toDateString(),
            'alasan' => 'Ada acara keluarga',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $record = TukarJadwal::where('jadwal_id', $jadwal->id)->first();

    expect($record)->not->toBeNull()
        ->and($record->isPindahSendiri())->toBeTrue()
        ->and($record->tanggal_baru->toDateString())->toBe($tanggalBaru->toDateString())
        ->and($record->jadwal_tujuan_id)->toBeNull();
});

it('menolak pindah ke tanggal yang sudah ada jadwal karyawan itu sendiri', function () {
    $shift = Shift::factory()->create();
    $pengaju = Karyawan::factory()->create();

    $jadwal = Jadwal::factory()->create([
        'karyawan_id' => $pengaju->id,
        'shift_id' => $shift->id,
        'tanggal' => today()->addDays(2),
    ]);

    $tanggalBentrok = today()->addDays(9);

    Jadwal::factory()->create([
        'karyawan_id' => $pengaju->id,
        'shift_id' => $shift->id,
        'tanggal' => $tanggalBentrok,
    ]);

    livewire(CreateTukarJadwal::class)
        ->fillForm([
            'mode' => 'pindah',
            'karyawan_pengaju_filter' => $pengaju->id,
            'jadwal_id' => $jadwal->id,
            'tanggal_baru' => $tanggalBentrok->toDateString(),
            'alasan' => 'Test bentrok',
        ])
        ->call('create')
        ->assertHasFormErrors(['tanggal_baru']);
});

it('menolak pindah ke tanggal yang bentrok dengan cuti/dinas yang sudah disetujui', function () {
    $shift = Shift::factory()->create();
    $pengaju = Karyawan::factory()->create();

    $jadwal = Jadwal::factory()->create([
        'karyawan_id' => $pengaju->id,
        'shift_id' => $shift->id,
        'tanggal' => today()->addDays(2),
    ]);

    $tanggalCuti = today()->addDays(15);

    \App\Models\Cuti::factory()->create([
        'karyawan_id' => $pengaju->id,
        'status' => 'approved',
        'tanggal_mulai' => $tanggalCuti,
        'tanggal_selesai' => $tanggalCuti,
    ]);

    livewire(CreateTukarJadwal::class)
        ->fillForm([
            'mode' => 'pindah',
            'karyawan_pengaju_filter' => $pengaju->id,
            'jadwal_id' => $jadwal->id,
            'tanggal_baru' => $tanggalCuti->toDateString(),
            'alasan' => 'Test bentrok cuti',
        ])
        ->call('create')
        ->assertHasFormErrors(['tanggal_baru']);
});

it('menolak alasan kosong', function () {

    $shift = Shift::factory()->create();
    $pengaju = Karyawan::factory()->create();

    $jadwal = Jadwal::factory()->create([
        'karyawan_id' => $pengaju->id,
        'shift_id' => $shift->id,
    ]);

    livewire(CreateTukarJadwal::class)
        ->fillForm([
            'mode' => 'pindah',
            'karyawan_pengaju_filter' => $pengaju->id,
            'jadwal_id' => $jadwal->id,
            'tanggal_baru' => today()->addDays(20)->toDateString(),
            'alasan' => '',
        ])
        ->call('create')
        ->assertHasFormErrors(['alasan' => 'required']);
});
