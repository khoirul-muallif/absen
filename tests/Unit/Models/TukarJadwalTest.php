<?php

use App\Models\Instansi;
use App\Models\Jadwal;
use App\Models\Karyawan;
use App\Models\Shift;
use App\Models\TukarJadwal;
use App\Models\User;

beforeEach(function () {
    $this->instansi = Instansi::factory()->create();
    $this->admin = User::factory()->create();
});

it('mode pindah: mengubah tanggal jadwal milik pengaju saja', function () {
    $karyawan = Karyawan::factory()->for($this->instansi)->create();
    $jadwal = Jadwal::factory()
        ->for($karyawan)
        ->for(Shift::factory()->for($this->instansi))
        ->create(['tanggal' => '2026-08-01']);

    $tukar = TukarJadwal::factory()
        ->create(['jadwal_id' => $jadwal->id, 'tanggal_baru' => '2026-08-05']);

    $tukar->approveAndSwap($this->admin);

    expect($jadwal->fresh()->tanggal->toDateString())->toBe('2026-08-05')
        ->and($jadwal->fresh()->karyawan_id)->toBe($karyawan->id)
        ->and($tukar->fresh()->status)->toBe('approved');
});

it('mode tukar: menukar karyawan_id di kedua jadwal', function () {
    $karyawanA = Karyawan::factory()->for($this->instansi)->create();
    $karyawanB = Karyawan::factory()->for($this->instansi)->create();
    $shift = Shift::factory()->for($this->instansi)->create();

    $jadwalA = Jadwal::factory()->for($karyawanA)->for($shift)->create(['tanggal' => '2026-08-01']);
    $jadwalB = Jadwal::factory()->for($karyawanB)->for($shift)->create(['tanggal' => '2026-08-02']);

    $tukar = TukarJadwal::factory()
        ->create(['jadwal_id' => $jadwalA->id, 'jadwal_tujuan_id' => $jadwalB->id]);

    $tukar->approveAndSwap($this->admin);

    expect($jadwalA->fresh()->karyawan_id)->toBe($karyawanB->id)
        ->and($jadwalB->fresh()->karyawan_id)->toBe($karyawanA->id)
        ->and($jadwalA->fresh()->tanggal->toDateString())->toBe('2026-08-01')
        ->and($jadwalB->fresh()->tanggal->toDateString())->toBe('2026-08-02');
});

it('menolak approve jika kepemilikan jadwal pengaju sudah berubah sejak pengajuan', function () {
    $karyawanAsli = Karyawan::factory()->for($this->instansi)->create();
    $karyawanLain = Karyawan::factory()->for($this->instansi)->create();
    $shift = Shift::factory()->for($this->instansi)->create();

    $jadwal = Jadwal::factory()->for($karyawanAsli)->for($shift)->create(['tanggal' => '2026-08-01']);

    $tukar = TukarJadwal::factory()
        ->create(['jadwal_id' => $jadwal->id, 'tanggal_baru' => '2026-08-05']);

    // kepemilikan berubah SETELAH pengajuan dibuat (snapshot sudah terlanjur simpan karyawanAsli)
    $jadwal->update(['karyawan_id' => $karyawanLain->id]);

    expect(fn () => $tukar->approveAndSwap($this->admin))->toThrow(\Exception::class);

    expect($tukar->fresh()->status)->toBe('pending');
});

it('menolak approve jika kepemilikan jadwal tujuan sudah berubah sejak pengajuan (race condition mode tukar)', function () {
    $karyawanA = Karyawan::factory()->for($this->instansi)->create();
    $karyawanTujuanAsli = Karyawan::factory()->for($this->instansi)->create();
    $karyawanLain = Karyawan::factory()->for($this->instansi)->create();
    $shift = Shift::factory()->for($this->instansi)->create();

    $jadwalA = Jadwal::factory()->for($karyawanA)->for($shift)->create(['tanggal' => '2026-08-01']);
    $jadwalB = Jadwal::factory()->for($karyawanTujuanAsli)->for($shift)->create(['tanggal' => '2026-08-02']);

    $tukar = TukarJadwal::factory()
        ->create(['jadwal_id' => $jadwalA->id, 'jadwal_tujuan_id' => $jadwalB->id]);

    // jadwal tujuan direbut duluan oleh pengajuan lain sebelum ini di-approve
    $jadwalB->update(['karyawan_id' => $karyawanLain->id]);

    expect(fn () => $tukar->approveAndSwap($this->admin))->toThrow(\Exception::class);

    expect($tukar->fresh()->status)->toBe('pending')
        ->and($jadwalA->fresh()->karyawan_id)->toBe($karyawanA->id);
});

it('gagal tukar jika salah satu karyawan sudah punya jadwal sendiri di tanggal pasangannya', function () {
    $karyawanA = Karyawan::factory()->for($this->instansi)->create();
    $karyawanB = Karyawan::factory()->for($this->instansi)->create();
    $shift = Shift::factory()->for($this->instansi)->create();

    $jadwalA = Jadwal::factory()->for($karyawanA)->for($shift)->create(['tanggal' => '2026-08-01']);
    $jadwalB = Jadwal::factory()->for($karyawanB)->for($shift)->create(['tanggal' => '2026-08-02']);

    // karyawanB SUDAH punya jadwal lain di tanggal yang sama dengan jadwalA (2026-08-01)
    // ini bikin unique constraint (karyawan_id, tanggal) bentrok pas swap
    Jadwal::factory()->for($karyawanB)->for($shift)->create(['tanggal' => '2026-08-01']);

    $tukar = TukarJadwal::factory()
        ->create(['jadwal_id' => $jadwalA->id, 'jadwal_tujuan_id' => $jadwalB->id]);

    expect(fn () => $tukar->approveAndSwap($this->admin))->toThrow(\Exception::class);
});

it('snapshot kolom terisi otomatis saat create, tidak perlu diisi manual', function () {
    $karyawan = Karyawan::factory()->for($this->instansi)->create();
    $shift = Shift::factory()->for($this->instansi)->create();
    $jadwal = Jadwal::factory()->for($karyawan)->for($shift)->create(['tanggal' => '2026-08-10']);

    $tukar = TukarJadwal::factory()
        ->create(['jadwal_id' => $jadwal->id, 'tanggal_baru' => '2026-08-15']);

    expect($tukar->karyawan_pengaju_id)->toBe($karyawan->id)
        ->and($tukar->tanggal_asal->toDateString())->toBe('2026-08-10')
        ->and($tukar->shift_asal_id)->toBe($shift->id);
});

it('isPindahSendiri true kalau jadwal_tujuan_id kosong', function () {
    $jadwal = Jadwal::factory()
        ->for(Karyawan::factory()->for($this->instansi))
        ->for(Shift::factory()->for($this->instansi))
        ->create();

    $tukar = TukarJadwal::factory()
        ->create(['jadwal_id' => $jadwal->id, 'tanggal_baru' => '2026-08-20']);

    expect($tukar->isPindahSendiri())->toBeTrue();
});

it('isPindahSendiri false kalau jadwal_tujuan_id terisi', function () {
    $shift = Shift::factory()->for($this->instansi)->create();
    $jadwalA = Jadwal::factory()->for(Karyawan::factory()->for($this->instansi))->for($shift)->create();
    $jadwalB = Jadwal::factory()->for(Karyawan::factory()->for($this->instansi))->for($shift)->create();

    $tukar = TukarJadwal::factory()
        ->create(['jadwal_id' => $jadwalA->id, 'jadwal_tujuan_id' => $jadwalB->id]);

    expect($tukar->isPindahSendiri())->toBeFalse();
});
