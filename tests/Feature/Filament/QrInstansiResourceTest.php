<?php

// tests/Feature/Filament/QrInstansiResourceTest.php

use App\Filament\Resources\QrInstansis\Pages\CreateQrInstansi;
use App\Filament\Resources\QrInstansis\Pages\EditQrInstansi;
use App\Filament\Resources\QrInstansis\Pages\ListQrInstansis;
use App\Filament\Resources\QrInstansis\Pages\ViewQrInstansi;
use App\Models\Absensi;
use App\Models\Instansi;
use App\Models\Karyawan;
use App\Models\QrInstansi;

use function Pest\Livewire\livewire;

beforeEach(function () {
    actingAsAdmin();
    $this->instansi = Instansi::factory()->create();
});

// ── List & create dasar ──────────────────────────────────────────────────

it('menampilkan daftar QR instansi', function () {
    $records = QrInstansi::factory()->count(3)->create(['instansi_id' => $this->instansi->id]);

    livewire(ListQrInstansis::class)
        ->assertCanSeeTableRecords($records);
});

it('bisa membuat QR dengan kode ter-generate otomatis', function () {
    livewire(CreateQrInstansi::class)
        ->fillForm([
            'instansi_id' => $this->instansi->id,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $qr = QrInstansi::where('instansi_id', $this->instansi->id)->latest('id')->first();

    // Helper text lama menyuruh "kosongkan dan simpan untuk generate otomatis",
    // padahal field-nya required. Yang benar: kodenya sudah terisi default.
    expect($qr)->not->toBeNull()
        ->and(strlen($qr->kode_qr))->toBe(32);
});

it('menolak kode_qr yang duplikat', function () {
    $qr = QrInstansi::factory()->create([
        'instansi_id' => $this->instansi->id,
        'kode_qr' => 'KODEDUPLIKAT123',
    ]);

    livewire(CreateQrInstansi::class)
        ->fillForm([
            'instansi_id' => $this->instansi->id,
            'kode_qr' => 'KODEDUPLIKAT123',
        ])
        ->call('create')
        ->assertHasFormErrors(['kode_qr']);
});

// ── Status validitas ─────────────────────────────────────────────────────
//
// Tabel sebelumnya cuma menampilkan is_active, jadi QR yang expired_at-nya
// sudah lewat tetap bercentang hijau padahal pemindaiannya ditolak. isValid()
// sudah ada sejak fase 1 tapi tidak pernah dipakai di UI.

it('status Berlaku untuk QR aktif tanpa kadaluarsa', function () {
    $qr = QrInstansi::factory()->create([
        'instansi_id' => $this->instansi->id,
        'is_active' => true,
        'expired_at' => null,
    ]);

    expect($qr->statusValiditas())->toBe('Berlaku')
        ->and($qr->isValid())->toBeTrue();
});

it('status Kedaluwarsa untuk QR aktif yang tanggalnya sudah lewat', function () {
    $qr = QrInstansi::factory()->create([
        'instansi_id' => $this->instansi->id,
        'is_active' => true,
        'expired_at' => now()->subDay(),
    ]);

    // Masih is_active = true, tapi tidak bisa dipakai absen.
    expect($qr->statusValiditas())->toBe('Kedaluwarsa')
        ->and($qr->isValid())->toBeFalse();
});

it('status Nonaktif menang atas kadaluarsa', function () {
    $qr = QrInstansi::factory()->create([
        'instansi_id' => $this->instansi->id,
        'is_active' => false,
        'expired_at' => now()->subDay(),
    ]);

    expect($qr->statusValiditas())->toBe('Nonaktif');
});

it('kolom status di tabel menampilkan Kedaluwarsa, bukan sekadar aktif', function () {
    $qr = QrInstansi::factory()->create([
        'instansi_id' => $this->instansi->id,
        'is_active' => true,
        'expired_at' => now()->subDay(),
    ]);

    livewire(ListQrInstansis::class)
        ->assertTableColumnStateSet('status_validitas', 'Kedaluwarsa', $qr);
});

it('filter masih berlaku menyembunyikan QR kedaluwarsa & nonaktif', function () {
    $berlaku = QrInstansi::factory()->create([
        'instansi_id' => $this->instansi->id,
        'is_active' => true,
        'expired_at' => null,
    ]);

    $kedaluwarsa = QrInstansi::factory()->create([
        'instansi_id' => $this->instansi->id,
        'is_active' => true,
        'expired_at' => now()->subDay(),
    ]);

    $nonaktif = QrInstansi::factory()->create([
        'instansi_id' => $this->instansi->id,
        'is_active' => false,
        'expired_at' => null,
    ]);

    livewire(ListQrInstansis::class)
        ->filterTable('masih_berlaku')
        ->assertCanSeeTableRecords([$berlaku])
        ->assertCanNotSeeTableRecords([$kedaluwarsa, $nonaktif]);
});

// ── Kunci kode_qr setelah dipakai ────────────────────────────────────────
//
// Begitu QR pernah dipakai absen, fisiknya sudah dicetak dan ditempel.
// Mengubah kodenya membuat semua QR terpasang jadi tidak valid — karyawan
// tidak bisa absen dan tidak ada yang tahu penyebabnya.

it('kodeMasihBisaDiubah true untuk QR yang belum pernah dipakai', function () {
    $qr = QrInstansi::factory()->create(['instansi_id' => $this->instansi->id]);

    expect($qr->kodeMasihBisaDiubah())->toBeTrue();
});

it('kodeMasihBisaDiubah false begitu sudah dipakai absen', function () {
    $qr = QrInstansi::factory()->create(['instansi_id' => $this->instansi->id]);
    $karyawan = Karyawan::factory()->create(['instansi_id' => $this->instansi->id]);

    Absensi::factory()->create([
        'karyawan_id' => $karyawan->id,
        'qr_instansi_id' => $qr->id,
        'tanggal' => '2026-08-01',
    ]);

    expect($qr->fresh()->kodeMasihBisaDiubah())->toBeFalse();
});

it('field kode_qr terkunci di form kalau QR sudah dipakai absen', function () {
    $qr = QrInstansi::factory()->create(['instansi_id' => $this->instansi->id]);
    $karyawan = Karyawan::factory()->create(['instansi_id' => $this->instansi->id]);

    Absensi::factory()->create([
        'karyawan_id' => $karyawan->id,
        'qr_instansi_id' => $qr->id,
        'tanggal' => '2026-08-02',
    ]);

    livewire(EditQrInstansi::class, ['record' => $qr->getRouteKey()])
        ->assertFormFieldDisabled('kode_qr');
});

it('field kode_qr masih bisa diubah kalau QR belum dipakai', function () {
    $qr = QrInstansi::factory()->create(['instansi_id' => $this->instansi->id]);

    livewire(EditQrInstansi::class, ['record' => $qr->getRouteKey()])
        ->assertFormFieldEnabled('kode_qr');
});

// ── Guard hapus (FK RESTRICT) ────────────────────────────────────────────

it('tombol hapus disembunyikan untuk QR yang sudah dipakai absen', function () {
    $qr = QrInstansi::factory()->create(['instansi_id' => $this->instansi->id]);
    $karyawan = Karyawan::factory()->create(['instansi_id' => $this->instansi->id]);

    Absensi::factory()->create([
        'karyawan_id' => $karyawan->id,
        'qr_instansi_id' => $qr->id,
        'tanggal' => '2026-08-03',
    ]);

    livewire(ListQrInstansis::class)
        ->assertTableActionHidden('delete', $qr);
});

it('tombol hapus muncul untuk QR yang belum pernah dipakai', function () {
    $qr = QrInstansi::factory()->create(['instansi_id' => $this->instansi->id]);

    livewire(ListQrInstansis::class)
        ->assertTableActionVisible('delete', $qr);
});

it('hapus massal dibatalkan kalau ada QR yang sudah dipakai', function () {
    $dipakai = QrInstansi::factory()->create(['instansi_id' => $this->instansi->id]);
    $bebas = QrInstansi::factory()->create(['instansi_id' => $this->instansi->id]);

    $karyawan = Karyawan::factory()->create(['instansi_id' => $this->instansi->id]);

    Absensi::factory()->create([
        'karyawan_id' => $karyawan->id,
        'qr_instansi_id' => $dipakai->id,
        'tanggal' => '2026-08-04',
    ]);

    livewire(ListQrInstansis::class)
        ->callTableBulkAction('delete', [$dipakai, $bebas]);

    expect(QrInstansi::find($dipakai->id))->not->toBeNull()
        ->and(QrInstansi::find($bebas->id))->not->toBeNull();
});

// ── Halaman View ─────────────────────────────────────────────────────────

it('halaman View QR bisa dibuka', function () {
    $qr = QrInstansi::factory()->create(['instansi_id' => $this->instansi->id]);

    livewire(ViewQrInstansi::class, ['record' => $qr->getRouteKey()])
        ->assertSuccessful();
});

it('ViewAction muncul di tabel', function () {
    $qr = QrInstansi::factory()->create(['instansi_id' => $this->instansi->id]);

    livewire(ListQrInstansis::class)
        ->assertTableActionVisible('view', $qr);
});
