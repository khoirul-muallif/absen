<?php

// tests/Feature/Filament/KaryawanResourceTest.php
//
// File baru — KaryawanResource belum pernah punya test Filament sama sekali
// sampai fase 34, padahal ini Resource dengan konsekuensi penghapusan paling
// besar (semua FK ke karyawan_id ON DELETE CASCADE).

use App\Filament\Resources\Karyawans\Pages\CreateKaryawan;
use App\Filament\Resources\Karyawans\Pages\EditKaryawan;
use App\Filament\Resources\Karyawans\Pages\ListKaryawans;
use App\Filament\Resources\Karyawans\Pages\ViewKaryawan;
use App\Models\Absensi;
use App\Models\Instansi;
use App\Models\Karyawan;
use App\Models\KaryawanPolaRotasi;
use App\Models\KaryawanShift;
use App\Models\PolaRotasi;
use App\Models\Shift;

use function Pest\Livewire\livewire;

beforeEach(function () {
    actingAsAdmin();
    $this->instansi = Instansi::factory()->create();
});

// ── List & create dasar ──────────────────────────────────────────────────

it('menampilkan daftar karyawan', function () {
    $records = Karyawan::factory()->count(3)->create(['instansi_id' => $this->instansi->id]);

    livewire(ListKaryawans::class)
        ->assertCanSeeTableRecords($records);
});

it('bisa membuat karyawan umum dengan data valid', function () {
    livewire(CreateKaryawan::class)
        ->fillForm([
            'instansi_id' => $this->instansi->id,
            'nama' => 'Budi Santoso',
            'nip' => 'NIP-99001',
            'email' => 'budi.baru@rsb.com',
            'password' => 'rahasia123',
            'status_pegawai' => 'tetap',
            'role' => 'karyawan',
            'tipe_jadwal' => Karyawan::TIPE_UMUM,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Karyawan::where('nip', 'NIP-99001')->first())
        ->not->toBeNull()
        ->tipe_jadwal->toBe(Karyawan::TIPE_UMUM);
});

it('password tersimpan dalam bentuk hash, bukan teks polos', function () {
    livewire(CreateKaryawan::class)
        ->fillForm([
            'instansi_id' => $this->instansi->id,
            'nama' => 'Uji Hash',
            'nip' => 'NIP-99002',
            'email' => 'hash@rsb.com',
            'password' => 'rahasia123',
            'status_pegawai' => 'tetap',
            'role' => 'karyawan',
            'tipe_jadwal' => Karyawan::TIPE_UMUM,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $karyawan = Karyawan::where('nip', 'NIP-99002')->first();

    // Hash::make() di form dihapus karena model sudah punya cast 'hashed'.
    // Test ini memastikan penghapusan itu tidak bikin password tersimpan polos.
    expect($karyawan->password)->not->toBe('rahasia123')
        ->and(\Illuminate\Support\Facades\Hash::check('rahasia123', $karyawan->password))->toBeTrue();
});

it('menolak NIP dan email yang duplikat', function () {
    Karyawan::factory()->create([
        'instansi_id' => $this->instansi->id,
        'nip' => 'NIP-99003',
        'email' => 'duplikat@rsb.com',
    ]);

    livewire(CreateKaryawan::class)
        ->fillForm([
            'instansi_id' => $this->instansi->id,
            'nama' => 'Kembar',
            'nip' => 'NIP-99003',
            'email' => 'duplikat@rsb.com',
            'password' => 'rahasia123',
            'status_pegawai' => 'tetap',
            'role' => 'karyawan',
            'tipe_jadwal' => Karyawan::TIPE_UMUM,
        ])
        ->call('create')
        ->assertHasFormErrors(['nip', 'email']);
});

// ── unit_kerja wajib untuk tipe rotasi ───────────────────────────────────
//
// Sejak fase 33, assignment pola rotasi ditolak kalau unit_kerja karyawan
// tidak sama persis dengan unit pola. Karyawan rotasi tanpa unit_kerja tidak
// akan pernah cocok dengan pola mana pun, jadi tidak bisa dijadwalkan.

it('mewajibkan unit_kerja untuk karyawan rotasi', function () {
    livewire(CreateKaryawan::class)
        ->fillForm([
            'instansi_id' => $this->instansi->id,
            'nama' => 'Rotasi Tanpa Unit',
            'nip' => 'NIP-99004',
            'email' => 'rotasi@rsb.com',
            'password' => 'rahasia123',
            'status_pegawai' => 'tetap',
            'role' => 'karyawan',
            'tipe_jadwal' => Karyawan::TIPE_ROTASI,
            'unit_kerja' => null,
        ])
        ->call('create')
        ->assertHasFormErrors(['unit_kerja']);
});

it('tidak mewajibkan unit_kerja untuk karyawan umum', function () {
    livewire(CreateKaryawan::class)
        ->fillForm([
            'instansi_id' => $this->instansi->id,
            'nama' => 'Umum Tanpa Unit',
            'nip' => 'NIP-99005',
            'email' => 'umum@rsb.com',
            'password' => 'rahasia123',
            'status_pegawai' => 'tetap',
            'role' => 'karyawan',
            'tipe_jadwal' => Karyawan::TIPE_UMUM,
            'unit_kerja' => null,
        ])
        ->call('create')
        ->assertHasNoFormErrors();
});

// ── Guard perubahan tipe_jadwal ──────────────────────────────────────────
//
// Mengubah tipe saat assignment masih ada meninggalkan baris yang menurut
// guard fase 18 seharusnya mustahil. Anomali itu selama ini baru terdeteksi
// belakangan oleh karyawan:cek-tipe-jadwal (fase 13).

it('menolak ubah tipe_jadwal kalau masih punya penugasan shift periode', function () {
    $karyawan = Karyawan::factory()->umum()->create([
        'instansi_id' => $this->instansi->id,
        'unit_kerja' => 'IGD',
    ]);

    $shift = Shift::factory()->create(['instansi_id' => $this->instansi->id]);

    KaryawanShift::factory()->create([
        'karyawan_id' => $karyawan->id,
        'shift_id' => $shift->id,
        'tanggal_berlaku' => '2026-08-01',
    ]);

    livewire(EditKaryawan::class, ['record' => $karyawan->getRouteKey()])
        ->fillForm(['tipe_jadwal' => Karyawan::TIPE_ROTASI])
        ->call('save')
        ->assertHasFormErrors(['tipe_jadwal']);

    expect($karyawan->fresh()->tipe_jadwal)->toBe(Karyawan::TIPE_UMUM);
});

it('menolak ubah tipe_jadwal kalau masih punya assignment pola rotasi', function () {
    $karyawan = Karyawan::factory()->rotasi()->create([
        'instansi_id' => $this->instansi->id,
        'unit_kerja' => 'IGD',
    ]);

    $pola = PolaRotasi::factory()->create([
        'instansi_id' => $this->instansi->id,
        'unit_kerja' => 'IGD',
        'langkah' => [['shift_id' => null, 'libur' => true]],
    ]);

    KaryawanPolaRotasi::factory()->create([
        'karyawan_id' => $karyawan->id,
        'pola_rotasi_id' => $pola->id,
        'tanggal_mulai' => '2026-08-01',
    ]);

    livewire(EditKaryawan::class, ['record' => $karyawan->getRouteKey()])
        ->fillForm(['tipe_jadwal' => Karyawan::TIPE_UMUM])
        ->call('save')
        ->assertHasFormErrors(['tipe_jadwal']);

    expect($karyawan->fresh()->tipe_jadwal)->toBe(Karyawan::TIPE_ROTASI);
});

it('MENGIZINKAN ubah tipe_jadwal kalau belum punya penugasan apa pun', function () {
    $karyawan = Karyawan::factory()->umum()->create([
        'instansi_id' => $this->instansi->id,
        'unit_kerja' => 'IGD',
    ]);

    livewire(EditKaryawan::class, ['record' => $karyawan->getRouteKey()])
        ->fillForm(['tipe_jadwal' => Karyawan::TIPE_ROTASI])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($karyawan->fresh()->tipe_jadwal)->toBe(Karyawan::TIPE_ROTASI);
});

it('menyimpan perubahan lain tanpa terhalang guard tipe_jadwal', function () {
    $karyawan = Karyawan::factory()->umum()->create([
        'instansi_id' => $this->instansi->id,
        'unit_kerja' => 'IGD',
    ]);

    $shift = Shift::factory()->create(['instansi_id' => $this->instansi->id]);

    KaryawanShift::factory()->create([
        'karyawan_id' => $karyawan->id,
        'shift_id' => $shift->id,
        'tanggal_berlaku' => '2026-08-01',
    ]);

    // Guard cuma berlaku kalau tipe_jadwal-nya benar-benar BERUBAH.
    livewire(EditKaryawan::class, ['record' => $karyawan->getRouteKey()])
        ->fillForm(['jabatan' => 'Kepala Unit'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($karyawan->fresh()->jabatan)->toBe('Kepala Unit');
});

// ── Guard hapus (ON DELETE CASCADE) ──────────────────────────────────────

it('punyaRiwayat true kalau karyawan sudah punya absensi', function () {
    $karyawan = Karyawan::factory()->create(['instansi_id' => $this->instansi->id]);

    Absensi::factory()->create([
        'karyawan_id' => $karyawan->id,
        'tanggal' => '2026-08-01',
    ]);

    expect($karyawan->fresh()->punyaRiwayat())->toBeTrue();
});

it('punyaRiwayat false untuk karyawan yang belum punya data apa pun', function () {
    $karyawan = Karyawan::factory()->create(['instansi_id' => $this->instansi->id]);

    expect($karyawan->punyaRiwayat())->toBeFalse();
});

it('tombol hapus disembunyikan untuk karyawan yang sudah punya riwayat', function () {
    $karyawan = Karyawan::factory()->create(['instansi_id' => $this->instansi->id]);

    Absensi::factory()->create([
        'karyawan_id' => $karyawan->id,
        'tanggal' => '2026-08-02',
    ]);

    livewire(ListKaryawans::class)
        ->assertTableActionHidden('delete', $karyawan);
});

it('tombol hapus muncul untuk karyawan yang belum punya riwayat', function () {
    $karyawan = Karyawan::factory()->create(['instansi_id' => $this->instansi->id]);

    livewire(ListKaryawans::class)
        ->assertTableActionVisible('delete', $karyawan);
});

it('hapus massal dibatalkan kalau ada karyawan yang punya riwayat', function () {
    $punyaRiwayat = Karyawan::factory()->create(['instansi_id' => $this->instansi->id]);
    $bersih = Karyawan::factory()->create(['instansi_id' => $this->instansi->id]);

    Absensi::factory()->create([
        'karyawan_id' => $punyaRiwayat->id,
        'tanggal' => '2026-08-03',
    ]);

    livewire(ListKaryawans::class)
        ->callTableBulkAction('delete', [$punyaRiwayat, $bersih]);

    expect(Karyawan::find($punyaRiwayat->id))->not->toBeNull()
        ->and(Karyawan::find($bersih->id))->not->toBeNull();
});

// ── Halaman View & filter ────────────────────────────────────────────────

it('halaman View karyawan bisa dibuka', function () {
    $karyawan = Karyawan::factory()->create(['instansi_id' => $this->instansi->id]);

    livewire(ViewKaryawan::class, ['record' => $karyawan->getRouteKey()])
        ->assertSuccessful();
});

it('ViewAction muncul di tabel', function () {
    $karyawan = Karyawan::factory()->create(['instansi_id' => $this->instansi->id]);

    livewire(ListKaryawans::class)
        ->assertTableActionVisible('view', $karyawan);
});

it('filter tipe_jadwal memisahkan karyawan umum dari rotasi', function () {
    $umum = Karyawan::factory()->umum()->create(['instansi_id' => $this->instansi->id]);
    $rotasi = Karyawan::factory()->rotasi()->create(['instansi_id' => $this->instansi->id]);

    livewire(ListKaryawans::class)
        ->filterTable('tipe_jadwal', Karyawan::TIPE_ROTASI)
        ->assertCanSeeTableRecords([$rotasi])
        ->assertCanNotSeeTableRecords([$umum]);
});
