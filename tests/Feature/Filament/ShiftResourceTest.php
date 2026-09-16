<?php

// tests/Feature/Filament/ShiftResourceTest.php
//
// File baru — ShiftResource belum pernah punya test Filament sama sekali
// sampai fase 30. Yang ada cuma Tests\Unit\Models\ShiftTest (logika model).

use App\Filament\Resources\Shifts\Pages\CreateShift;
use App\Filament\Resources\Shifts\Pages\EditShift;
use App\Filament\Resources\Shifts\Pages\ListShifts;
use App\Filament\Resources\Shifts\Pages\ViewShift;
use App\Models\Absensi;
use App\Models\Instansi;
use App\Models\Karyawan;
use App\Models\Shift;

use function Pest\Livewire\livewire;

beforeEach(function () {
    actingAsAdmin();
    $this->instansi = Instansi::factory()->create();
});

// ── List & create dasar ──────────────────────────────────────────────────

it('menampilkan daftar shift', function () {
    $records = Shift::factory()->count(3)->create(['instansi_id' => $this->instansi->id]);

    livewire(ListShifts::class)
        ->assertCanSeeTableRecords($records);
});

it('bisa membuat shift dengan data valid', function () {
    livewire(CreateShift::class)
        ->fillForm([
            'instansi_id' => $this->instansi->id,
            'nama_shift' => 'Pagi',
            'jam_masuk' => '07:00',
            'jam_pulang' => '14:00',
            'toleransi_menit' => 15,
            'mode_toleransi' => 'harian',
            'hari_kerja' => [1, 2, 3, 4, 5],
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Shift::where('nama_shift', 'Pagi')->first())
        ->not->toBeNull()
        ->instansi_id->toBe($this->instansi->id);
});

// ── Durasi shift ─────────────────────────────────────────────────────────
//
// Shift malam (pulang di dini hari keesokan harinya) SAH, jadi aturannya
// bukan "pulang harus setelah masuk". Yang ditolak cuma durasi nol —
// keputusan yang sama sudah diambil untuk Lembur di fase 25.

it('menolak jam_pulang yang sama dengan jam_masuk (durasi nol)', function () {
    livewire(CreateShift::class)
        ->fillForm([
            'instansi_id' => $this->instansi->id,
            'nama_shift' => 'Nol',
            'jam_masuk' => '07:00',
            'jam_pulang' => '07:00',
            'toleransi_menit' => 15,
            'mode_toleransi' => 'harian',
        ])
        ->call('create')
        ->assertHasFormErrors(['jam_pulang']);

    expect(Shift::where('nama_shift', 'Nol')->exists())->toBeFalse();
});

it('menerima shift malam yang jam pulangnya lebih awal dari jam masuk', function () {
    livewire(CreateShift::class)
        ->fillForm([
            'instansi_id' => $this->instansi->id,
            'nama_shift' => 'Malam',
            'jam_masuk' => '22:00',
            'jam_pulang' => '07:00',
            'toleransi_menit' => 15,
            'mode_toleransi' => 'harian',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Shift::where('nama_shift', 'Malam')->exists())->toBeTrue();
});

// ── Nama shift boleh duplikat kalau jamnya beda ──────────────────────────
//
// Tabel `shift` TIDAK punya kolom unit_kerja, jadi satu-satunya cara
// merepresentasikan jam masuk berbeda antar unit (IGD 07:00, Rawat Jalan
// 08:00) adalah dua baris yang sama-sama bernama "Pagi". Itu data sah —
// yang diperbaiki adalah label dropdown-nya, bukan datanya.

it('mengizinkan nama shift yang sama di instansi sama asalkan jamnya berbeda', function () {
    Shift::factory()->create([
        'instansi_id' => $this->instansi->id,
        'nama_shift' => 'Pagi',
        'jam_masuk' => '07:00:00',
        'jam_pulang' => '14:00:00',
    ]);

    livewire(CreateShift::class)
        ->fillForm([
            'instansi_id' => $this->instansi->id,
            'nama_shift' => 'Pagi',
            'jam_masuk' => '08:00',
            'jam_pulang' => '15:00',
            'toleransi_menit' => 15,
            'mode_toleransi' => 'harian',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Shift::where('nama_shift', 'Pagi')->count())->toBe(2);
});

it('labelLengkap membedakan dua shift bernama sama', function () {
    $pagiIgd = Shift::factory()->create([
        'instansi_id' => $this->instansi->id,
        'nama_shift' => 'Pagi',
        'jam_masuk' => '07:00:00',
        'jam_pulang' => '14:00:00',
    ]);

    $pagiRajal = Shift::factory()->create([
        'instansi_id' => $this->instansi->id,
        'nama_shift' => 'Pagi',
        'jam_masuk' => '08:00:00',
        'jam_pulang' => '15:00:00',
    ]);

    expect($pagiIgd->labelLengkap())->toBe('Pagi (07:00–14:00)')
        ->and($pagiRajal->labelLengkap())->toBe('Pagi (08:00–15:00)')
        ->and($pagiIgd->labelLengkap())->not->toBe($pagiRajal->labelLengkap());
});

// ── Guard hapus (FK RESTRICT) ────────────────────────────────────────────
//
// absensi.shift_id memakai ON DELETE RESTRICT. Tanpa guard, menghapus shift
// yang pernah dipakai absensi melempar QueryException 1451 mentah ke layar —
// kelas bug yang sama dengan 1062 di KuotaCuti (fase 25) & Absensi (fase 27).

it('sedangDipakai true kalau shift punya absensi', function () {
    $shift = Shift::factory()->create(['instansi_id' => $this->instansi->id]);
    $karyawan = Karyawan::factory()->create(['instansi_id' => $this->instansi->id]);

    Absensi::factory()->create([
        'karyawan_id' => $karyawan->id,
        'shift_id' => $shift->id,
        'tanggal' => '2026-08-01',
    ]);

    expect($shift->fresh()->sedangDipakai())->toBeTrue();
});

it('sedangDipakai false untuk shift yang belum pernah dipakai', function () {
    $shift = Shift::factory()->create(['instansi_id' => $this->instansi->id]);

    expect($shift->sedangDipakai())->toBeFalse();
});

it('tombol hapus disembunyikan untuk shift yang masih dipakai absensi', function () {
    $shift = Shift::factory()->create(['instansi_id' => $this->instansi->id]);
    $karyawan = Karyawan::factory()->create(['instansi_id' => $this->instansi->id]);

    Absensi::factory()->create([
        'karyawan_id' => $karyawan->id,
        'shift_id' => $shift->id,
        'tanggal' => '2026-08-01',
    ]);

    livewire(ListShifts::class)
        ->assertTableActionHidden('delete', $shift);
});

it('tombol hapus muncul untuk shift yang belum pernah dipakai', function () {
    $shift = Shift::factory()->create(['instansi_id' => $this->instansi->id]);

    livewire(ListShifts::class)
        ->assertTableActionVisible('delete', $shift);
});

it('tombol hapus di halaman Edit ikut disembunyikan untuk shift yang dipakai', function () {
    $shift = Shift::factory()->create(['instansi_id' => $this->instansi->id]);
    $karyawan = Karyawan::factory()->create(['instansi_id' => $this->instansi->id]);

    Absensi::factory()->create([
        'karyawan_id' => $karyawan->id,
        'shift_id' => $shift->id,
        'tanggal' => '2026-08-02',
    ]);

    livewire(EditShift::class, ['record' => $shift->getRouteKey()])
        ->assertActionHidden('delete');
});

// ============================================================================
// TAMBAHAN untuk tests/Feature/Filament/ShiftResourceTest.php
//
// Tempel di akhir file. Tambahkan import:
//   use App\Filament\Resources\Shifts\Pages\ViewShift;
// ============================================================================

// ── Halaman View (batch B) ───────────────────────────────────────────────

it('halaman View shift bisa dibuka', function () {
    $shift = Shift::factory()->create(['instansi_id' => $this->instansi->id]);

    livewire(ViewShift::class, ['record' => $shift->getRouteKey()])
        ->assertSuccessful();
});

it('ViewAction muncul di tabel', function () {
    $shift = Shift::factory()->create(['instansi_id' => $this->instansi->id]);

    livewire(ListShifts::class)
        ->assertTableActionVisible('view', $shift);
});

it('tombol hapus di halaman View ikut disembunyikan untuk shift yang dipakai', function () {
    $shift = Shift::factory()->create(['instansi_id' => $this->instansi->id]);
    $karyawan = Karyawan::factory()->create(['instansi_id' => $this->instansi->id]);

    Absensi::factory()->create([
        'karyawan_id' => $karyawan->id,
        'shift_id' => $shift->id,
        'tanggal' => '2026-08-03',
    ]);

    livewire(ViewShift::class, ['record' => $shift->getRouteKey()])
        ->assertActionHidden('delete');
});

// ── Guard hapus massal ───────────────────────────────────────────────────
//
// Sebelumnya guard ini sama sekali tidak tersentuh test, padahal
// DeleteBulkAction::before() + $action->cancel() itu API yang belum pernah
// dipakai di project ini. Tanpa test, error-nya baru muncul saat admin
// benar-benar memakai hapus massal.

it('hapus massal dibatalkan kalau ada shift yang masih dipakai', function () {
    $karyawan = Karyawan::factory()->create(['instansi_id' => $this->instansi->id]);

    $dipakai = Shift::factory()->create(['instansi_id' => $this->instansi->id, 'nama_shift' => 'Dipakai']);
    $bebas = Shift::factory()->create(['instansi_id' => $this->instansi->id, 'nama_shift' => 'Bebas']);

    Absensi::factory()->create([
        'karyawan_id' => $karyawan->id,
        'shift_id' => $dipakai->id,
        'tanggal' => '2026-08-04',
    ]);

    livewire(ListShifts::class)
        ->callTableBulkAction('delete', [$dipakai, $bebas]);

    // Tidak ada yang terhapus — termasuk yang sebenarnya bebas, karena
    // pembatalan berlaku untuk seluruh batch (lebih aman daripada menghapus
    // sebagian tanpa admin sadar).
    expect(Shift::find($dipakai->id))->not->toBeNull()
        ->and(Shift::find($bebas->id))->not->toBeNull();
});

it('hapus massal berjalan kalau semua shift belum pernah dipakai', function () {
    $a = Shift::factory()->create(['instansi_id' => $this->instansi->id]);
    $b = Shift::factory()->create(['instansi_id' => $this->instansi->id]);

    livewire(ListShifts::class)
        ->callTableBulkAction('delete', [$a, $b]);

    expect(Shift::find($a->id))->toBeNull()
        ->and(Shift::find($b->id))->toBeNull();
});

// ── Filter (batch C) ─────────────────────────────────────────────────────

it('filter mode toleransi memisahkan harian dari akumulasi bulanan', function () {
    $harian = Shift::factory()->create([
        'instansi_id' => $this->instansi->id,
        'mode_toleransi' => 'harian',
    ]);

    $akumulasi = Shift::factory()->create([
        'instansi_id' => $this->instansi->id,
        'mode_toleransi' => 'akumulasi_bulanan',
    ]);

    livewire(ListShifts::class)
        ->filterTable('mode_toleransi', 'akumulasi_bulanan')
        ->assertCanSeeTableRecords([$akumulasi])
        ->assertCanNotSeeTableRecords([$harian]);
});

it('filter status aktif memisahkan shift nonaktif', function () {
    $aktif = Shift::factory()->create(['instansi_id' => $this->instansi->id, 'is_active' => true]);
    $nonaktif = Shift::factory()->create(['instansi_id' => $this->instansi->id, 'is_active' => false]);

    livewire(ListShifts::class)
        ->filterTable('is_active', true)
        ->assertCanSeeTableRecords([$aktif])
        ->assertCanNotSeeTableRecords([$nonaktif]);
});
