<?php

use App\Filament\Resources\Absensis\Pages\CreateAbsensi;
use App\Filament\Resources\Absensis\Pages\EditAbsensi;
use App\Filament\Resources\Absensis\Pages\ListAbsensis;
use App\Filament\Resources\Absensis\Pages\ViewAbsensi;
use App\Models\Absensi;
use App\Models\Cuti;
use App\Models\JenisCuti;
use App\Models\Karyawan;
use App\Models\QrInstansi;
use App\Models\Shift;
use App\Models\User;


use function Pest\Livewire\livewire;

beforeEach(function () {
    actingAsAdmin();
});

// ── List page ────────────────────────────────────────────────────────────

it('menampilkan daftar absensi', function () {
    $records = Absensi::factory()->count(3)->create();

    livewire(ListAbsensis::class)
        ->assertCanSeeTableRecords($records);
});

// ── Create ───────────────────────────────────────────────────────────────

it('bisa membuat data absensi manual dengan status dipilih langsung', function () {
    $karyawan = Karyawan::factory()->create();
    $shift = Shift::factory()->create();
    $qr = QrInstansi::factory()->create();

    livewire(CreateAbsensi::class)
        ->fillForm([
            'karyawan_id' => $karyawan->id,
            'shift_id' => $shift->id,
            'qr_instansi_id' => $qr->id,
            'tanggal' => today()->toDateString(),
            'status' => 'izin',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Absensi::where('karyawan_id', $karyawan->id)->where('status', 'izin')->exists())->toBeTrue();
});

it('menolak submit tanpa karyawan', function () {
    livewire(CreateAbsensi::class)
        ->fillForm([
            'tanggal' => today()->toDateString(),
        ])
        ->call('create')
        ->assertHasFormErrors(['karyawan_id']);
});

// ── shift_id & qr_instansi_id: required kondisional ───────────────────────
//
// Sebelumnya keduanya required tanpa syarat, padahal nullable di DB dan
// sinkronisasi Cuti/Dinas justru mengosongkan qr_instansi_id. Memaksa admin
// memilih QR untuk baris cuti/libur cuma bikin data berbohong.

it('tidak mewajibkan shift & QR untuk baris non-kehadiran', function () {
    $karyawan = Karyawan::factory()->create();

    livewire(CreateAbsensi::class)
        ->fillForm([
            'karyawan_id' => $karyawan->id,
            'tanggal' => today()->toDateString(),
            'status' => 'libur',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Absensi::where('karyawan_id', $karyawan->id)->first())
        ->shift_id->toBeNull()
        ->qr_instansi_id->toBeNull();
});

it('mewajibkan shift kalau waktu_masuk diisi', function () {
    $karyawan = Karyawan::factory()->create();

    livewire(CreateAbsensi::class)
        ->fillForm([
            'karyawan_id' => $karyawan->id,
            'tanggal' => today()->toDateString(),
            'waktu_masuk' => today()->setTime(8, 5)->toDateTimeString(),
            'status' => 'terlambat',
        ])
        ->call('create')
        ->assertHasFormErrors(['shift_id']);
});

// ── Unique (karyawan_id, tanggal) ─────────────────────────────────────────
//
// Constraint-nya sudah ada di DB sejak fase 1, tapi tidak divalidasi di form
// — submit duplikat baru gagal sebagai QueryException 1062 mentah. Pola yang
// sama dengan bug KuotaCuti fase 25.

it('menolak absensi duplikat untuk karyawan & tanggal yang sama', function () {
    $karyawan = Karyawan::factory()->create();

    Absensi::factory()->create([
        'karyawan_id' => $karyawan->id,
        'tanggal' => '2026-08-01',
    ]);

    livewire(CreateAbsensi::class)
        ->fillForm([
            'karyawan_id' => $karyawan->id,
            'tanggal' => '2026-08-01',
            'status' => 'alpha',
        ])
        ->call('create')
        ->assertHasFormErrors(['tanggal']);

    expect(Absensi::where('karyawan_id', $karyawan->id)->count())->toBe(1);
});

it('mengizinkan tanggal yang sama untuk karyawan berbeda', function () {
    $karyawanA = Karyawan::factory()->create();
    $karyawanB = Karyawan::factory()->create();

    Absensi::factory()->create([
        'karyawan_id' => $karyawanA->id,
        'tanggal' => '2026-08-01',
    ]);

    livewire(CreateAbsensi::class)
        ->fillForm([
            'karyawan_id' => $karyawanB->id,
            'tanggal' => '2026-08-01',
            'status' => 'alpha',
        ])
        ->call('create')
        ->assertHasNoFormErrors();
});

it('edit tidak kena unique constraint dirinya sendiri', function () {
    $karyawan = Karyawan::factory()->create();

    $absensi = Absensi::factory()->create([
        'karyawan_id' => $karyawan->id,
        'tanggal' => '2026-08-01',
        'status' => 'alpha',
    ]);

    livewire(EditAbsensi::class, ['record' => $absensi->getRouteKey()])
        ->fillForm(['keterangan' => 'Diperbarui admin'])
        ->call('save')
        ->assertHasNoFormErrors();
});

// ── Keterkaitan tanggal <-> waktu_masuk ───────────────────────────────────
//
// Sebelumnya dua field ini sama sekali tidak terikat. Kondisi
// DATE(waktu_masuk) != tanggal itu persis data cacat yang dibersihkan di
// fase 26 lanjutan: rekap dikelompokkan per `tanggal`, keterlambatan
// dihitung dari `waktu_masuk`.

it('menolak waktu_masuk yang tanggalnya berbeda dari field tanggal', function () {
    $karyawan = Karyawan::factory()->create();
    $shift = Shift::factory()->create(['jam_masuk' => '08:00:00']);

    livewire(CreateAbsensi::class)
        ->fillForm([
            'karyawan_id' => $karyawan->id,
            'shift_id' => $shift->id,
            'tanggal' => '2026-08-01',
            'waktu_masuk' => '2026-08-05 08:05:00',
            'status' => 'terlambat',
        ])
        ->call('create')
        ->assertHasFormErrors(['waktu_masuk']);

    expect(Absensi::where('karyawan_id', $karyawan->id)->exists())->toBeFalse();
});

it('menerima waktu_pulang di tanggal berikutnya (shift malam)', function () {
    $karyawan = Karyawan::factory()->create();
    $shift = Shift::factory()->create(['jam_masuk' => '22:00:00', 'jam_pulang' => '07:00:00']);

    livewire(CreateAbsensi::class)
        ->fillForm([
            'karyawan_id' => $karyawan->id,
            'shift_id' => $shift->id,
            'tanggal' => '2026-08-01',
            'waktu_masuk' => '2026-08-01 22:00:00',
            'waktu_pulang' => '2026-08-02 07:00:00',
            'status' => 'tepat_waktu',
        ])
        ->call('create')
        ->assertHasNoFormErrors();
});

// ── Hitung ulang saat create & edit ───────────────────────────────────────

it('form absensi bisa mengisi waktu_masuk dan menyimpan status hasil hitungan', function () {
    $karyawan = Karyawan::factory()->create();
    $qr = QrInstansi::factory()->create();

    $shift = Shift::factory()->create([
        'jam_masuk' => '08:00:00',
        'toleransi_menit' => 15,
        'mode_toleransi' => 'harian',
    ]);

    $waktuMasuk = today()->setTime(8, 5); // 5 menit lewat jam masuk

    // Sesuai temuan di Unit\Models\ShiftTest: mode harian selalu 'terlambat'
    // begitu lewat 0 menit dari jam masuk, terlepas dari toleransi_menit.
    livewire(CreateAbsensi::class)
        ->fillForm([
            'karyawan_id' => $karyawan->id,
            'shift_id' => $shift->id,
            'qr_instansi_id' => $qr->id,
            'tanggal' => today()->toDateString(),
            'waktu_masuk' => $waktuMasuk->toDateTimeString(),
            'status' => 'terlambat',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Absensi::where('karyawan_id', $karyawan->id)->first())
        ->status->toBe('terlambat')
        ->menit_terlambat->toBe(5);
});

// REGRESSION GUARD: EditAbsensi sebelumnya TIDAK punya hook sama sekali —
// mengubah waktu_masuk menyimpan waktu barunya tapi meninggalkan
// menit_terlambat & status pada nilai lama.

it('edit waktu_masuk ikut menghitung ulang menit_terlambat & status', function () {
    $karyawan = Karyawan::factory()->create();
    $shift = Shift::factory()->create([
        'jam_masuk' => '08:00:00',
        'toleransi_menit' => 15,
        'mode_toleransi' => 'harian',
    ]);

    $absensi = Absensi::factory()->create([
        'karyawan_id' => $karyawan->id,
        'shift_id' => $shift->id,
        'tanggal' => '2026-08-01',
        'waktu_masuk' => '2026-08-01 08:05:00',
        'menit_terlambat' => 5,
        'status' => 'terlambat',
    ]);

    livewire(EditAbsensi::class, ['record' => $absensi->getRouteKey()])
        ->fillForm(['waktu_masuk' => '2026-08-01 08:20:00'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($absensi->fresh()->menit_terlambat)->toBe(20);
});

it('akumulasi bulanan saat edit tidak menghitung record itu sendiri dua kali', function () {
    $karyawan = Karyawan::factory()->create();
    $shift = Shift::factory()->create([
        'jam_masuk' => '08:00:00',
        'toleransi_menit' => 30,
        'mode_toleransi' => 'akumulasi_bulanan',
    ]);

    // Record yang akan diedit: sudah tercatat telat 20 menit.
    $absensi = Absensi::factory()->create([
        'karyawan_id' => $karyawan->id,
        'shift_id' => $shift->id,
        'tanggal' => '2026-08-10',
        'waktu_masuk' => '2026-08-10 08:20:00',
        'menit_terlambat' => 20,
        'status' => 'terlambat',
    ]);

    // Diubah jadi telat 5 menit. Tidak ada absensi lain di bulan itu, jadi
    // akumulasi seharusnya 5 — bukan 25 (20 lama + 5 baru).
    livewire(EditAbsensi::class, ['record' => $absensi->getRouteKey()])
        ->fillForm(['waktu_masuk' => '2026-08-10 08:05:00'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($absensi->fresh())
        ->menit_terlambat->toBe(5)
        ->melebihi_toleransi_bulanan->toBeFalsy(); // 5 menit, jauh di bawah 30
});

it('akumulasi bulanan: absensi kedua di bulan yang sama menjumlahkan menit_terlambat sebelumnya', function () {
    $karyawan = Karyawan::factory()->create();
    $qr = QrInstansi::factory()->create();

    $shift = Shift::factory()->create([
        'jam_masuk' => '08:00:00',
        'toleransi_menit' => 30,
        'mode_toleransi' => 'akumulasi_bulanan',
    ]);

    // Absensi sebelumnya bulan ini, sudah telat 20 menit
    Absensi::factory()->create([
        'karyawan_id' => $karyawan->id,
        'shift_id' => $shift->id,
        'tanggal' => today()->startOfMonth()->addDays(2),
        'menit_terlambat' => 20,
    ]);

    $waktuMasuk = today()->setTime(8, 15); // +15 menit -> total 35, lebih dari toleransi 30

    livewire(CreateAbsensi::class)
        ->fillForm([
            'karyawan_id' => $karyawan->id,
            'shift_id' => $shift->id,
            'qr_instansi_id' => $qr->id,
            'tanggal' => today()->toDateString(),
            'waktu_masuk' => $waktuMasuk->toDateTimeString(),
            'status' => 'terlambat',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    // Sekarang diverifikasi lewat kolom yang benar-benar tersimpan, bukan
    // lewat pemanggilan model langsung seperti versi lama test ini.
    expect(Absensi::where('karyawan_id', $karyawan->id)->where('tanggal', today()->toDateString())->first())
        ->menit_terlambat->toBe(15)
        ->melebihi_toleransi_bulanan->toBeTruthy();
});


// ============================================================================
// TAMBAHAN untuk tests/Feature/Filament/AbsensiResourceTest.php
//
// Tempel di akhir file. Tambahkan import berikut di atas kalau belum ada:
//   use App\Filament\Resources\Absensis\Pages\ViewAbsensi;
//   use App\Models\Cuti;
//   use App\Models\JenisCuti;
//   use App\Models\User;
// ============================================================================

// ── Halaman View ─────────────────────────────────────────────────────────
//
// Sebelumnya getPages() cuma index/create/edit — untuk melihat foto masuk/
// pulang & koordinat GPS, admin harus masuk ke mode edit. Semua modul
// approval sudah punya halaman View read-only sejak fase 25.

it('halaman View absensi bisa dibuka', function () {
    $absensi = Absensi::factory()->create();

    livewire(ViewAbsensi::class, ['record' => $absensi->getRouteKey()])
        ->assertSuccessful();
});

it('ViewAction muncul di tabel', function () {
    $absensi = Absensi::factory()->create();

    livewire(ListAbsensis::class)
        ->assertTableActionVisible('view', $absensi);
});

// ── Guard baris hasil sinkronisasi Cuti/Dinas ────────────────────────────
//
// Baris berstatus cuti/dinas dibuat oleh
// HasApprovalWorkflow::sinkronisasiJadwalDanAbsensi(), bukan entri manual.
// Mengubah status/waktu absennya bertentangan dengan pengajuan yang masih
// approved, dan tertimpa lagi saat sinkronisasi dijalankan ulang.

it('field status & waktu absen dikunci untuk baris hasil sinkronisasi cuti', function () {
    $karyawan = Karyawan::factory()->create();

    $absensi = Absensi::factory()->create([
        'karyawan_id' => $karyawan->id,
        'tanggal' => '2026-08-01',
        'status' => 'cuti',
        'waktu_masuk' => null,
    ]);

    livewire(EditAbsensi::class, ['record' => $absensi->getRouteKey()])
        ->assertFormFieldDisabled('status')
        ->assertFormFieldDisabled('waktu_masuk')
        ->assertFormFieldDisabled('waktu_pulang');
});

it('field status & waktu absen dikunci untuk baris hasil sinkronisasi dinas', function () {
    $absensi = Absensi::factory()->create([
        'tanggal' => '2026-08-01',
        'status' => 'dinas',
        'waktu_masuk' => null,
    ]);

    livewire(EditAbsensi::class, ['record' => $absensi->getRouteKey()])
        ->assertFormFieldDisabled('status');
});

// Status lain yang juga ditulis sistem (alpha & libur dari RekapHarian)
// SENGAJA tidak ikut dikunci — tidak ada record pengajuan di baliknya, dan
// mengoreksinya manual itu pekerjaan admin yang wajar.

it('baris alpha dari RekapHarian TETAP bisa diedit', function () {
    $absensi = Absensi::factory()->create([
        'tanggal' => '2026-08-01',
        'status' => 'alpha',
        'waktu_masuk' => null,
    ]);

    livewire(EditAbsensi::class, ['record' => $absensi->getRouteKey()])
        ->assertFormFieldEnabled('status')
        ->assertFormFieldEnabled('waktu_masuk');
});

it('baris libur TETAP bisa diedit', function () {
    $absensi = Absensi::factory()->create([
        'tanggal' => '2026-08-01',
        'status' => 'libur',
        'waktu_masuk' => null,
    ]);

    livewire(EditAbsensi::class, ['record' => $absensi->getRouteKey()])
        ->assertFormFieldEnabled('status');
});

it('keterangan tetap bisa diisi pada baris hasil sinkronisasi, status tidak berubah', function () {
    $absensi = Absensi::factory()->create([
        'tanggal' => '2026-08-01',
        'status' => 'cuti',
        'waktu_masuk' => null,
        'keterangan' => null,
    ]);

    livewire(EditAbsensi::class, ['record' => $absensi->getRouteKey()])
        ->fillForm(['keterangan' => 'Dikonfirmasi ke bagian SDM'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($absensi->fresh())
        ->keterangan->toBe('Dikonfirmasi ke bagian SDM')
        ->status->toBe('cuti')      // tidak ikut berubah
        ->waktu_masuk->toBeNull();
});

// REGRESSION GUARD: baris hasil approve Cuti yang sebenarnya (bukan factory),
// memastikan status yang ditulis afterApprove() memang memicu guard-nya.

it('baris Absensi hasil approve Cuti sungguhan ikut terkunci', function () {
    $karyawan = Karyawan::factory()->create();
    $jenisCuti = JenisCuti::factory()->create(['potong_kuota' => false]);

    $cuti = Cuti::factory()->create([
        'karyawan_id' => $karyawan->id,
        'jenis_cuti_id' => $jenisCuti->id,
        'tanggal_mulai' => '2026-08-01',
        'tanggal_selesai' => '2026-08-01',
        'jumlah_hari' => 1,
        'status' => 'pending',
    ]);

    $cuti->approve(User::first());

    $absensi = Absensi::where('karyawan_id', $karyawan->id)
        ->where('tanggal', '2026-08-01')
        ->firstOrFail();

    expect($absensi->status)->toBe('cuti');

    livewire(EditAbsensi::class, ['record' => $absensi->getRouteKey()])
        ->assertFormFieldDisabled('status');
});
