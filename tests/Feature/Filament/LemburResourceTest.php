<?php

// tests/Feature/Filament/LemburResourceTest.php

use App\Filament\Resources\Lemburs\Pages\CreateLembur;
use App\Filament\Resources\Lemburs\Pages\ListLemburs;
use App\Filament\Resources\Lemburs\Pages\ViewLembur;
use App\Models\Karyawan;
use App\Models\Lembur;
use App\Models\User;

use function Pest\Livewire\livewire;

beforeEach(function () {
    actingAsAdmin();
    $this->karyawan = Karyawan::factory()->create();
});

it('bisa membuat lembur tanpa alasan (nullable)', function () {
    livewire(CreateLembur::class)
        ->fillForm([
            'karyawan_id' => $this->karyawan->id,
            'tanggal' => '2026-08-01',
            'jam_mulai' => '16:00',
            'jam_selesai' => '21:00',
            'alasan' => null,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Lembur::first())
        ->karyawan_id->toBe($this->karyawan->id)
        ->alasan->toBeNull();
});

it('menolak jam_selesai sama dengan jam_mulai (durasi nol)', function () {
    livewire(CreateLembur::class)
        ->fillForm([
            'karyawan_id' => $this->karyawan->id,
            'tanggal' => '2026-08-01',
            'jam_mulai' => '16:00',
            'jam_selesai' => '16:00',
            'alasan' => 'Lembur proyek',
        ])
        ->call('create')
        ->assertHasFormErrors(['jam_selesai']);

    expect(Lembur::count())->toBe(0);
});

it('menerima jam_selesai lebih kecil dari jam_mulai (lintas tengah malam)', function () {
    livewire(CreateLembur::class)
        ->fillForm([
            'karyawan_id' => $this->karyawan->id,
            'tanggal' => '2026-08-01',
            'jam_mulai' => '22:00',
            'jam_selesai' => '02:00',
            'alasan' => 'Lembur shift malam',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Lembur::first())
        ->jam_mulai->not->toBeNull()
        ->jam_selesai->not->toBeNull();
});

// --- EditAction visibility: tabel ---

it('EditAction visible untuk lembur pending', function () {
    $lemburPending = Lembur::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'status' => 'pending',
    ]);

    livewire(ListLemburs::class)
        ->assertTableActionVisible('edit', $lemburPending);
});

it('EditAction hidden untuk lembur approved', function () {
    $lemburApproved = Lembur::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'status' => 'approved',
    ]);

    livewire(ListLemburs::class)
        ->assertTableActionHidden('edit', $lemburApproved);
});

// --- EditAction visibility: halaman View ---
//
// Dua test di bawah ini yang sebenarnya jadi alasan ViewLembur.php diaudit.
// Di fase 25, ViewCuti/ViewDinas/ViewTukarJadwal ketahuan punya EditAction
// TANPA guard visible() padahal tabelnya sudah benar — dan test tabel yang
// ada sama sekali tidak menangkapnya. ViewLembur ternyata sudah benar, tapi
// tanpa test ini tidak ada yang mencegahnya berubah diam-diam.

it('EditAction di halaman View visible untuk lembur pending', function () {
    $lembur = Lembur::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'status' => 'pending',
    ]);

    livewire(ViewLembur::class, ['record' => $lembur->getRouteKey()])
        ->assertActionVisible('edit');
});

it('EditAction di halaman View hidden untuk lembur approved', function () {
    $lembur = Lembur::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'status' => 'approved',
    ]);

    livewire(ViewLembur::class, ['record' => $lembur->getRouteKey()])
        ->assertActionHidden('edit');
});

// --- Approve/reject lewat action Filament ---
//
// Sebelumnya test ini memanggil $lembur->approve() langsung di model, jadi
// Notification::make() di LembursTable tidak pernah ter-cover walau judul
// test-nya menyebut notifikasi. Notifikasi approve/reject baru ditambahkan
// di fase 25 — sekarang benar-benar diuji lewat jalur yang dipakai admin.

it('approve lewat action tabel mengubah status dan mengirim notifikasi', function () {
    $lembur = Lembur::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'status' => 'pending',
    ]);

    livewire(ListLemburs::class)
        ->callTableAction('approve', $lembur)
        ->assertNotified('Lembur disetujui');

    $admin = User::first();

    expect($lembur->fresh())
        ->status->toBe('approved')
        ->approved_by->toBe($admin->id)
        ->approved_at->not->toBeNull();
});

it('reject lewat action tabel menyimpan catatan penolakan dan mengirim notifikasi', function () {
    $lembur = Lembur::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'status' => 'pending',
    ]);

    livewire(ListLemburs::class)
        ->callTableAction('reject', $lembur, ['catatan_approval' => 'Beban kerja tidak mendesak'])
        ->assertNotified('Lembur ditolak');

    expect($lembur->fresh())
        ->status->toBe('rejected')
        ->catatan_approval->toBe('Beban kerja tidak mendesak');
});

it('field alasan tidak wajib diisi di database (nullable)', function () {
    $lembur = Lembur::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'alasan' => null,
    ]);

    expect($lembur->alasan)->toBeNull();
});
