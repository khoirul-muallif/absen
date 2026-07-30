<?php

// tests/Feature/Filament/LemburResourceTest.php

use App\Filament\Resources\Lemburs\Pages\CreateLembur;
use App\Filament\Resources\Lemburs\Pages\EditLembur;
use App\Models\Karyawan;
use App\Models\Lembur;
use Livewire\Livewire;
use function Pest\Livewire\livewire;


beforeEach(function () {
    actingAsAdmin();
    $this->karyawan = Karyawan::factory()->create();
});

it('bisa membuat lembur tanpa alasan (nullable)', function () {
    Livewire::test(CreateLembur::class)
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
    Livewire::test(CreateLembur::class)
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
    Livewire::test(CreateLembur::class)
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

it('EditAction visible untuk lembur pending', function () {
    $lemburPending = Lembur::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'status' => 'pending',
    ]);

    livewire(\App\Filament\Resources\Lemburs\Pages\ListLemburs::class)
        ->assertTableActionVisible('edit', $lemburPending);
});

it('EditAction hidden untuk lembur approved', function () {
    $lemburApproved = Lembur::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'status' => 'approved',
    ]);

    livewire(\App\Filament\Resources\Lemburs\Pages\ListLemburs::class)
        ->assertTableActionHidden('edit', $lemburApproved);
});

it('approve lembur pending berhasil tanpa exception dan kirim notifikasi', function () {
    $lembur = Lembur::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'status' => 'pending',
    ]);

    $admin = \App\Models\User::first();

    $lembur->approve($admin);

    expect($lembur->fresh())
        ->status->toBe('approved')
        ->approved_by->toBe($admin->id)
        ->approved_at->not->toBeNull();
});

it('field alasan tidak wajib diisi di database (nullable)', function () {
    $lembur = Lembur::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'alasan' => null,
    ]);

    expect($lembur->alasan)->toBeNull();
});


