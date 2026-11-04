<?php

use App\Filament\Resources\TukarJadwals\Pages\CreateTukarJadwal;
use App\Filament\Resources\TukarJadwals\Pages\ListTukarJadwals;
use App\Models\Instansi;
use App\Models\Jadwal;
use App\Models\Karyawan;
use App\Models\Shift;
use App\Models\TukarJadwal;
use App\Models\User;

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
        ->and($record->status)->toBe('menunggu_rekan');
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
        'status' => 'menunggu_admin',
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
        ->assertHasFormErrors(['karyawan_tujuan_filter']);
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

    // Tujuan pindah harus benar-benar berstatus Libur milik karyawan sendiri
    // (bukan sekadar "belum ada Jadwal sama sekali") -- lihat bug fix 3 Nov 2026
    // di rule() tanggal_baru: tanpa baris ini, fixture ini sebelumnya numpang
    // di bug yang sama (tanggal kosong total ikut lolos validasi).
    Jadwal::factory()->create([
        'karyawan_id' => $pengaju->id,
        'shift_id' => null,
        'jenis' => Jadwal::JENIS_LIBUR,
        'tanggal' => $tanggalBaru,
    ]);


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

it('menolak tukar kalau pengaju sedang cuti/dinas approved di tanggal asalnya sendiri', function () {
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

    \App\Models\Cuti::factory()->create([
        'karyawan_id' => $pengaju->id,
        'status' => 'approved',
        'tanggal_mulai' => $jadwalAsal->tanggal,
        'tanggal_selesai' => $jadwalAsal->tanggal,
    ]);

    livewire(CreateTukarJadwal::class)
        ->fillForm([
            'mode' => 'tukar',
            'karyawan_pengaju_filter' => $pengaju->id,
            'jadwal_id' => $jadwalAsal->id,
            'karyawan_tujuan_filter' => $tujuan->id,
            'jadwal_tujuan_id' => $jadwalTujuan->id,
            'alasan' => 'Test bentrok cuti pengaju',
        ])
        ->call('create')
        ->assertHasFormErrors(['karyawan_tujuan_filter']);
});

it('menolak tukar kalau karyawan tujuan sedang dinas approved di tanggal tujuannya sendiri', function () {
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

    \App\Models\Dinas::factory()->create([
        'karyawan_id' => $tujuan->id,
        'status' => 'approved',
        'tanggal_mulai' => $jadwalTujuan->tanggal,
        'tanggal_selesai' => $jadwalTujuan->tanggal,
    ]);

    livewire(CreateTukarJadwal::class)
        ->fillForm([
            'mode' => 'tukar',
            'karyawan_pengaju_filter' => $pengaju->id,
            'jadwal_id' => $jadwalAsal->id,
            'karyawan_tujuan_filter' => $tujuan->id,
            'jadwal_tujuan_id' => $jadwalTujuan->id,
            'alasan' => 'Test bentrok dinas tujuan',
        ])
        ->call('create')
        ->assertHasFormErrors(['karyawan_tujuan_filter']);
});

it('opsiJadwal tidak error untuk karyawan rotasi yang punya Jadwal libur', function () {
    $instansi = \App\Models\Instansi::factory()->create();
    $karyawan = \App\Models\Karyawan::factory()->rotasi()->create(['instansi_id' => $instansi->id]);
    \App\Models\Jadwal::factory()->create([
        'karyawan_id' => $karyawan->id,
        'shift_id' => null,
        'tanggal' => '2026-10-06',
        'jenis' => 'libur',
    ]);

    $map = (new \ReflectionMethod(\App\Filament\Resources\TukarJadwals\Schemas\TukarJadwalForm::class, 'tanggalJadwalMap'))
        ->invoke(null, $karyawan->id);

    expect($map)->toHaveCount(1)
        ->and(array_values($map)[0]['label'])->toBe('Libur');
});

it('approveAndSwap menandai kedua Jadwal sumber=manual, tidak tertimpa generator', function () {
    $instansi = \App\Models\Instansi::factory()->create();
    $shift = \App\Models\Shift::factory()->create(['instansi_id' => $instansi->id]);
    $admin = \App\Models\User::factory()->create();

    $pengaju = \App\Models\Karyawan::factory()->rotasi()->create(['instansi_id' => $instansi->id]);
    $tujuan  = \App\Models\Karyawan::factory()->rotasi()->create(['instansi_id' => $instansi->id]);

    $jadwalA = \App\Models\Jadwal::factory()->create([
        'karyawan_id' => $pengaju->id, 'shift_id' => $shift->id,
        'tanggal' => '2026-10-10', 'jenis' => 'piket', 'sumber' => 'generate',
    ]);
    $jadwalB = \App\Models\Jadwal::factory()->create([
        'karyawan_id' => $tujuan->id, 'shift_id' => $shift->id,
        'tanggal' => '2026-10-11', 'jenis' => 'piket', 'sumber' => 'generate',
    ]);

    $tukar = \App\Models\TukarJadwal::create([
        'jadwal_id' => $jadwalA->id,
        'karyawan_pengaju_id' => $pengaju->id,
        'tanggal_asal' => $jadwalA->tanggal,
        'shift_asal_id' => $shift->id,
        'jadwal_tujuan_id' => $jadwalB->id,
        'karyawan_tujuan_id' => $tujuan->id,
        'tanggal_tujuan' => $jadwalB->tanggal,
        'shift_tujuan_id' => $shift->id,
        'alasan' => 'uji',
        'status' => 'menunggu_admin',
    ]);

    $tukar->approveAndSwap($admin);

    expect($jadwalA->fresh()->sumber)->toBe('manual')
        ->and($jadwalB->fresh()->sumber)->toBe('manual');
});

it('tombol batalkan menunggu rekan hanya muncul untuk status menunggu_rekan', function () {
    $menungguRekan = TukarJadwal::factory()
        ->modeTukar()
        ->create(['status' => 'menunggu_rekan']);

    $menungguAdmin = TukarJadwal::factory()
        ->modePindah()
        ->create(['status' => 'menunggu_admin']);

    livewire(ListTukarJadwals::class)
        ->assertTableActionVisible('batalkanMenungguRekan', $menungguRekan)
        ->assertTableActionHidden('batalkanMenungguRekan', $menungguAdmin);
});

it('admin bisa membatalkan pengajuan menunggu_rekan lewat tabel Filament', function () {
    $tukarJadwal = TukarJadwal::factory()
        ->modeTukar()
        ->create(['status' => 'menunggu_rekan']);

    livewire(ListTukarJadwals::class)
        ->callTableAction('batalkanMenungguRekan', $tukarJadwal, data: [
            'catatan' => 'rekan resign, pengajuan tidak relevan lagi',
        ])
        ->assertHasNoTableActionErrors();

    $tukarJadwal->refresh();
    expect($tukarJadwal->status)->toBe('rejected')
        ->and($tukarJadwal->catatan_approval)->toContain('rekan resign, pengajuan tidak relevan lagi');
});


it('admin bisa membatalkan pengajuan yang macet di menunggu_rekan', function () {
    $admin = \App\Models\User::factory()->create();

    $jadwalAsal = Jadwal::factory()->create();
    $jadwalTujuan = Jadwal::factory()->create();

    $tukarJadwal = TukarJadwal::factory()
        ->modeTukar($jadwalTujuan->id)
        ->create([
            'jadwal_id' => $jadwalAsal->id,
            'status' => 'menunggu_rekan',
        ]);

    $tukarJadwal->batalkanOlehAdmin($admin, 'rekan tidak merespons 2 minggu');

    $tukarJadwal->refresh();
    expect($tukarJadwal->status)->toBe('rejected')
        ->and($tukarJadwal->approved_by)->toBe($admin->id)
        ->and($tukarJadwal->catatan_approval)->toContain('rekan tidak merespons 2 minggu');

    // Jadwal kedua karyawan TIDAK berubah sama sekali — menunggu_rekan murni
    // status flag, tidak ada reservasi yang perlu di-rollback.
    expect($jadwalAsal->fresh()->karyawan_id)->toBe($jadwalAsal->karyawan_id)
        ->and($jadwalTujuan->fresh()->karyawan_id)->toBe($jadwalTujuan->karyawan_id);
});

it('menolak batalkan admin kalau status sudah bukan menunggu_rekan', function () {
    $admin = \App\Models\User::factory()->create();

    $tukarJadwal = TukarJadwal::factory()
        ->modePindah()
        ->create(['status' => 'menunggu_admin']);

    expect(fn () => $tukarJadwal->batalkanOlehAdmin($admin, 'test'))
        ->toThrow(\Exception::class);

    expect($tukarJadwal->fresh()->status)->toBe('menunggu_admin'); // tidak berubah
});

it('opsiJadwal mengecualikan jadwal berjenis cuti dan dinas, tapi tetap menampilkan libur', function () {
    $instansi = \App\Models\Instansi::factory()->create();
    $shift = \App\Models\Shift::factory()->create(['instansi_id' => $instansi->id]);
    $karyawan = Karyawan::factory()->create(['instansi_id' => $instansi->id]);

    $jadwalKerja = Jadwal::factory()->create([
        'karyawan_id' => $karyawan->id, 'shift_id' => $shift->id,
        'tanggal' => '2026-11-01', 'jenis' => 'reguler',
    ]);
    $jadwalLibur = Jadwal::factory()->create([
        'karyawan_id' => $karyawan->id, 'shift_id' => null,
        'tanggal' => '2026-11-02', 'jenis' => 'libur',
    ]);
    $jadwalCuti = Jadwal::factory()->create([
        'karyawan_id' => $karyawan->id, 'shift_id' => null,
        'tanggal' => '2026-11-03', 'jenis' => 'cuti',
    ]);
    $jadwalDinas = Jadwal::factory()->create([
        'karyawan_id' => $karyawan->id, 'shift_id' => null,
        'tanggal' => '2026-11-04', 'jenis' => 'dinas',
    ]);

    $map = (new \ReflectionMethod(\App\Filament\Resources\TukarJadwals\Schemas\TukarJadwalForm::class, 'tanggalJadwalMap'))
        ->invoke(null, $karyawan->id);

    $idTersimpan = collect($map)->pluck('id');

    expect($map)->toHaveCount(2)
        ->and($idTersimpan)->toContain($jadwalKerja->id)
        ->and($idTersimpan)->toContain($jadwalLibur->id)
        ->and($idTersimpan)->not->toContain($jadwalCuti->id)
        ->and($idTersimpan)->not->toContain($jadwalDinas->id);
});

it('bisa menukar jadwal libur milik satu karyawan dengan jadwal kerja rekan', function () {
    $instansi = \App\Models\Instansi::factory()->create();
    $shift = \App\Models\Shift::factory()->create(['instansi_id' => $instansi->id]);

    $pengaju = Karyawan::factory()->rotasi()->create(['instansi_id' => $instansi->id]);
    $tujuan  = Karyawan::factory()->rotasi()->create(['instansi_id' => $instansi->id]);

    $jadwalAsal = Jadwal::factory()->create([
        'karyawan_id' => $pengaju->id, 'shift_id' => null,
        'tanggal' => today()->addDays(3), 'jenis' => 'libur',
    ]);
    $jadwalTujuan = Jadwal::factory()->create([
        'karyawan_id' => $tujuan->id, 'shift_id' => $shift->id,
        'tanggal' => today()->addDays(5), 'jenis' => 'piket',
    ]);

    livewire(CreateTukarJadwal::class)
        ->fillForm([
            'mode' => 'tukar',
            'karyawan_pengaju_filter' => $pengaju->id,
            'jadwal_id' => $jadwalAsal->id,
            'karyawan_tujuan_filter' => $tujuan->id,
            'jadwal_tujuan_id' => $jadwalTujuan->id,
            'alasan' => 'Tes tukar libur dengan kerja',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $record = TukarJadwal::where('jadwal_id', $jadwalAsal->id)->first();
    expect($record)->not->toBeNull()
        ->and($record->shift_asal_id)->toBeNull(); // snapshot shift libur = null
});

it('menolak memindahkan jadwal libur sendiri (mode pindah cuma untuk hari kerja)', function () {
    $instansi = \App\Models\Instansi::factory()->create();
    $pengaju = Karyawan::factory()->rotasi()->create(['instansi_id' => $instansi->id]);

    $jadwalLibur = Jadwal::factory()->create([
        'karyawan_id' => $pengaju->id, 'shift_id' => null,
        'tanggal' => today()->addDays(2), 'jenis' => 'libur',
    ]);

    $tanggalLiburLain = today()->addDays(10);
    Jadwal::factory()->create([
        'karyawan_id' => $pengaju->id, 'shift_id' => null,
        'tanggal' => $tanggalLiburLain, 'jenis' => 'libur',
    ]);

    livewire(CreateTukarJadwal::class)
        ->fillForm([
            'mode' => 'pindah',
            'karyawan_pengaju_filter' => $pengaju->id,
            'jadwal_id' => $jadwalLibur->id,
            'tanggal_baru' => $tanggalLiburLain->toDateString(),
            'alasan' => 'Tes pindah libur sendiri — harus ditolak',
        ])
        ->call('create')
        ->assertHasFormErrors(['jadwal_id']);

    expect(TukarJadwal::where('jadwal_id', $jadwalLibur->id)->exists())->toBeFalse();
});

it('bisa memindahkan hari kerja ke tanggal libur milik sendiri', function () {
    $instansi = \App\Models\Instansi::factory()->create();
    $shift = \App\Models\Shift::factory()->create(['instansi_id' => $instansi->id]);
    $pengaju = Karyawan::factory()->rotasi()->create(['instansi_id' => $instansi->id]);

    $jadwalKerja = Jadwal::factory()->create([
        'karyawan_id' => $pengaju->id, 'shift_id' => $shift->id,
        'tanggal' => today()->addDays(2), 'jenis' => 'reguler',
    ]);

    $tanggalLibur = today()->addDays(10);
    Jadwal::factory()->create([
        'karyawan_id' => $pengaju->id, 'shift_id' => null,
        'tanggal' => $tanggalLibur, 'jenis' => 'libur',
    ]);

    livewire(CreateTukarJadwal::class)
        ->fillForm([
            'mode' => 'pindah',
            'karyawan_pengaju_filter' => $pengaju->id,
            'jadwal_id' => $jadwalKerja->id,
            'tanggal_baru' => $tanggalLibur->toDateString(),
            'alasan' => 'Tes pindah hari kerja ke tanggal libur',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $record = TukarJadwal::where('jadwal_id', $jadwalKerja->id)->first();
    expect($record)->not->toBeNull()
        ->and($record->tanggal_baru->toDateString())->toBe($tanggalLibur->toDateString());
});
it('approve tukar beda tanggal menghapus baris libur yang diserap dan menandai sumber=manual', function () {
    $shift = Shift::factory()->create();
    $admin = User::factory()->create();
    $pengaju = Karyawan::factory()->create();
    $tujuan = Karyawan::factory()->create();

    $jadwalAsal = Jadwal::factory()->create([
        'karyawan_id' => $pengaju->id, 'shift_id' => $shift->id,
        'tanggal' => today()->addDays(3), 'jenis' => Jadwal::JENIS_REGULER,
    ]);
    $jadwalTujuan = Jadwal::factory()->create([
        'karyawan_id' => $tujuan->id, 'shift_id' => $shift->id,
        'tanggal' => today()->addDays(5), 'jenis' => Jadwal::JENIS_REGULER,
    ]);
    $liburPengajuDiTujuan = Jadwal::factory()->create([
        'karyawan_id' => $pengaju->id, 'shift_id' => null,
        'jenis' => Jadwal::JENIS_LIBUR, 'tanggal' => today()->addDays(5),
    ]);

    $tukarJadwal = TukarJadwal::factory()->create([
        'jadwal_id' => $jadwalAsal->id,
        'jadwal_tujuan_id' => $jadwalTujuan->id,
        'status' => 'menunggu_admin',
    ]);

    $hasil = $tukarJadwal->approveAndSwap($admin);

    expect($hasil)->toBeTrue();
    $this->assertModelMissing($liburPengajuDiTujuan);

    $this->assertDatabaseHas('jadwals', [
        'id' => $jadwalAsal->id,
        'karyawan_id' => $tujuan->id,
        'tanggal' => today()->addDays(3)->toDateString(),
        'sumber' => 'manual',
    ]);
    $this->assertDatabaseHas('jadwals', [
        'id' => $jadwalTujuan->id,
        'karyawan_id' => $pengaju->id,
        'tanggal' => today()->addDays(5)->toDateString(),
        'sumber' => 'manual',
    ]);
});

it('approve ditolak dan rollback penuh kalau baris libur yang mau diserap dipakai pengajuan lain', function () {
    $shift = Shift::factory()->create();
    $admin = User::factory()->create();
    $pengaju = Karyawan::factory()->create();
    $tujuan = Karyawan::factory()->create();

    $jadwalAsal = Jadwal::factory()->create([
        'karyawan_id' => $pengaju->id, 'shift_id' => $shift->id,
        'tanggal' => today()->addDays(3), 'jenis' => Jadwal::JENIS_REGULER,
    ]);
    $jadwalTujuan = Jadwal::factory()->create([
        'karyawan_id' => $tujuan->id, 'shift_id' => $shift->id,
        'tanggal' => today()->addDays(5), 'jenis' => Jadwal::JENIS_REGULER,
    ]);
    $liburRebutan = Jadwal::factory()->create([
        'karyawan_id' => $pengaju->id, 'shift_id' => null,
        'jenis' => Jadwal::JENIS_LIBUR, 'tanggal' => today()->addDays(5),
    ]);

    $tukarJadwal = TukarJadwal::factory()->create([
        'jadwal_id' => $jadwalAsal->id,
        'jadwal_tujuan_id' => $jadwalTujuan->id,
        'status' => 'menunggu_admin',
    ]);

    TukarJadwal::factory()->create([
        'jadwal_id' => $liburRebutan->id,
        'status' => 'menunggu_rekan',
    ]);

    expect(fn () => $tukarJadwal->approveAndSwap($admin))->toThrow(\App\Exceptions\TukarJadwalKonflikException::class);
    $this->assertModelExists($liburRebutan);
    $this->assertDatabaseHas('jadwals', ['id' => $jadwalAsal->id, 'karyawan_id' => $pengaju->id]);
    $this->assertDatabaseHas('jadwals', ['id' => $jadwalTujuan->id, 'karyawan_id' => $tujuan->id]);
    expect($tukarJadwal->fresh()->status)->toBe('menunggu_admin');
});

it('approve ditolak kalau baris yang tadinya libur sudah berubah jadi kerja sejak pengajuan dibuat', function () {
    $shift = Shift::factory()->create();
    $admin = User::factory()->create();
    $pengaju = Karyawan::factory()->create();
    $tujuan = Karyawan::factory()->create();

    $jadwalAsal = Jadwal::factory()->create([
        'karyawan_id' => $pengaju->id, 'shift_id' => $shift->id,
        'tanggal' => today()->addDays(3), 'jenis' => Jadwal::JENIS_REGULER,
    ]);
    $jadwalTujuan = Jadwal::factory()->create([
        'karyawan_id' => $tujuan->id, 'shift_id' => $shift->id,
        'tanggal' => today()->addDays(5), 'jenis' => Jadwal::JENIS_REGULER,
    ]);
    $tukarJadwal = TukarJadwal::factory()->create([
        'jadwal_id' => $jadwalAsal->id,
        'jadwal_tujuan_id' => $jadwalTujuan->id,
        'status' => 'menunggu_admin',
    ]);

    Jadwal::factory()->create([
        'karyawan_id' => $pengaju->id, 'shift_id' => $shift->id,
        'jenis' => Jadwal::JENIS_REGULER, 'tanggal' => today()->addDays(5),
    ]);

    expect(fn () => $tukarJadwal->approveAndSwap($admin))->toThrow(\App\Exceptions\TukarJadwalKonflikException::class);    expect($tukarJadwal->fresh()->status)->toBe('menunggu_admin');
});

it('regression: approve tukar tanggal sama (karyawan umum) tetap jalan seperti sebelumnya', function () {
    $shift = Shift::factory()->create();
    $admin = User::factory()->create();
    $pengaju = Karyawan::factory()->create();
    $tujuan = Karyawan::factory()->create();

    $tanggalSama = today()->addDays(4);
    $jadwalAsal = Jadwal::factory()->create([
        'karyawan_id' => $pengaju->id, 'shift_id' => $shift->id, 'tanggal' => $tanggalSama,
    ]);
    $jadwalTujuan = Jadwal::factory()->create([
        'karyawan_id' => $tujuan->id, 'shift_id' => $shift->id, 'tanggal' => $tanggalSama,
    ]);

    $tukarJadwal = TukarJadwal::factory()->create([
        'jadwal_id' => $jadwalAsal->id,
        'jadwal_tujuan_id' => $jadwalTujuan->id,
        'status' => 'menunggu_admin',
    ]);

    expect($tukarJadwal->approveAndSwap($admin))->toBeTrue();
    $this->assertDatabaseHas('jadwals', ['id' => $jadwalAsal->id, 'karyawan_id' => $tujuan->id]);
    $this->assertDatabaseHas('jadwals', ['id' => $jadwalTujuan->id, 'karyawan_id' => $pengaju->id]);
});

it('menolak pindah ke tanggal yang tidak punya Jadwal sama sekali (bukan libur, beneran kosong)', function () {
    $instansi = \App\Models\Instansi::factory()->create();
    $shift = \App\Models\Shift::factory()->create(['instansi_id' => $instansi->id]);
    $pengaju = Karyawan::factory()->rotasi()->create(['instansi_id' => $instansi->id]);

    $jadwalKerja = Jadwal::factory()->create([
        'karyawan_id' => $pengaju->id, 'shift_id' => $shift->id,
        'tanggal' => today()->addDays(2), 'jenis' => 'reguler',
    ]);

    // Tanggal jauh di luar jendela RENTANG_BULAN_SEBELUM/SESUDAH (-1/+3 bulan) --
    // tanggalJadwalMap() tidak pernah memuat tanggal ini, jadi TIDAK ADA baris
    // Jadwal apa pun yang match, beda dari "libur" yang beneran ada barisnya.
    $tanggalJauhDiLuarJendela = today()->addYears(1);

    livewire(CreateTukarJadwal::class)
        ->fillForm([
            'mode' => 'pindah',
            'karyawan_pengaju_filter' => $pengaju->id,
            'jadwal_id' => $jadwalKerja->id,
            'tanggal_baru' => $tanggalJauhDiLuarJendela->toDateString(),
            'alasan' => 'Tes pindah ke tanggal kosong total — harus ditolak',
        ])
        ->call('create')
        ->assertHasFormErrors(['tanggal_baru']);

    expect(TukarJadwal::where('jadwal_id', $jadwalKerja->id)->exists())->toBeFalse();
});

it('tanggal_baru dibatasi minDate/maxDate sesuai jendela Jadwal karyawan pengaju', function () {
    $instansi = \App\Models\Instansi::factory()->create();
    $shift = \App\Models\Shift::factory()->create(['instansi_id' => $instansi->id]);
    $pengaju = Karyawan::factory()->rotasi()->create(['instansi_id' => $instansi->id]);

    $tanggalPalingAwal = today()->startOfMonth()->subMonthNoOverflow();
    $tanggalPalingAkhir = today()->startOfMonth()->addMonthsNoOverflow(3)->endOfMonth();

    Jadwal::factory()->create([
        'karyawan_id' => $pengaju->id, 'shift_id' => $shift->id,
        'tanggal' => $tanggalPalingAwal, 'jenis' => 'reguler',
    ]);
    Jadwal::factory()->create([
        'karyawan_id' => $pengaju->id, 'shift_id' => $shift->id,
        'tanggal' => $tanggalPalingAkhir, 'jenis' => 'reguler',
    ]);

    $map = (new \ReflectionMethod(\App\Filament\Resources\TukarJadwals\Schemas\TukarJadwalForm::class, 'tanggalPalingAwal'))
        ->invoke(null, $pengaju->id);
    $mapAkhir = (new \ReflectionMethod(\App\Filament\Resources\TukarJadwals\Schemas\TukarJadwalForm::class, 'tanggalPalingAkhir'))
        ->invoke(null, $pengaju->id);

    expect($map)->toBe($tanggalPalingAwal->toDateString())
        ->and($mapAkhir)->toBe($tanggalPalingAkhir->toDateString());
});

it('admin bisa membuat pengajuan tukar beda tanggal lewat form kalau sisi existing libur', function () {
    $instansi = \App\Models\Instansi::factory()->create();
    $shift = \App\Models\Shift::factory()->create(['instansi_id' => $instansi->id]);

    $pengaju = Karyawan::factory()->rotasi()->create(['instansi_id' => $instansi->id]);
    $tujuan = Karyawan::factory()->rotasi()->create(['instansi_id' => $instansi->id]);

    $jadwalAsal = Jadwal::factory()->create([
        'karyawan_id' => $pengaju->id, 'shift_id' => $shift->id,
        'tanggal' => today()->addDays(3), 'jenis' => 'reguler',
    ]);
    $jadwalTujuan = Jadwal::factory()->create([
        'karyawan_id' => $tujuan->id, 'shift_id' => $shift->id,
        'tanggal' => today()->addDays(10), 'jenis' => 'reguler',
    ]);

    // Pengaju kebetulan LIBUR di tanggal tujuan (10), tujuan kebetulan LIBUR
    // di tanggal asal (3) -- dua-duanya rotasi, jadi otomatis punya baris
    // di SETIAP tanggal termasuk liburnya sendiri.
    Jadwal::factory()->create([
        'karyawan_id' => $pengaju->id, 'shift_id' => null,
        'jenis' => 'libur', 'tanggal' => today()->addDays(10),
    ]);
    Jadwal::factory()->create([
        'karyawan_id' => $tujuan->id, 'shift_id' => null,
        'jenis' => 'libur', 'tanggal' => today()->addDays(3),
    ]);

    livewire(CreateTukarJadwal::class)
        ->fillForm([
            'mode' => 'tukar',
            'karyawan_pengaju_filter' => $pengaju->id,
            'jadwal_id' => $jadwalAsal->id,
            'karyawan_tujuan_filter' => $tujuan->id,
            'jadwal_tujuan_id' => $jadwalTujuan->id,
            'alasan' => 'Tes form admin tukar beda tanggal, sisi existing libur',
        ])
        ->call('create')
        ->assertHasNoFormErrors();
});

it('pesan error konflik tukar beda tanggal menyebut nama, tanggal, dan shift yang bentrok', function () {
    $instansi = \App\Models\Instansi::factory()->create();
    $shift = \App\Models\Shift::factory()->create(['instansi_id' => $instansi->id, 'nama_shift' => 'Piket']);
    $pengaju = Karyawan::factory()->rotasi()->create(['instansi_id' => $instansi->id, 'nama' => 'Si Pengaju']);
    $tujuan = Karyawan::factory()->rotasi()->create(['instansi_id' => $instansi->id]);

    $jadwalAsal = Jadwal::factory()->create([
        'karyawan_id' => $pengaju->id, 'shift_id' => $shift->id,
        'tanggal' => today()->addDays(3), 'jenis' => 'reguler',
    ]);
    $jadwalTujuan = Jadwal::factory()->create([
        'karyawan_id' => $tujuan->id, 'shift_id' => $shift->id,
        'tanggal' => today()->addDays(10), 'jenis' => 'reguler',
    ]);

    // Pengaju sendiri sudah punya shift KERJA di tanggal tujuan (10)
    Jadwal::factory()->create([
        'karyawan_id' => $pengaju->id, 'shift_id' => $shift->id,
        'jenis' => 'piket', 'tanggal' => today()->addDays(10),
    ]);

    livewire(CreateTukarJadwal::class)
        ->fillForm([
            'mode' => 'tukar',
            'karyawan_pengaju_filter' => $pengaju->id,
            'jadwal_id' => $jadwalAsal->id,
            'karyawan_tujuan_filter' => $tujuan->id,
            'jadwal_tujuan_id' => $jadwalTujuan->id,
            'alasan' => 'Tes pesan error spesifik',
        ])
        ->call('create')
        ->assertHasFormErrors(['karyawan_tujuan_filter']);

    // Isi pesan dicek lewat livewire errors bag, bukan cuma field-nya
});
