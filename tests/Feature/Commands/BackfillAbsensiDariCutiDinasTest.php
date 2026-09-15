<?php

// tests/Feature/Commands/BackfillAbsensiDariCutiDinasTest.php

use App\Models\Absensi;
use App\Models\Cuti;
use App\Models\Dinas;
use App\Models\Jadwal;
use App\Models\JenisCuti;
use App\Models\Karyawan;
use App\Models\KuotaCuti;

beforeEach(function () {
    $this->karyawan = Karyawan::factory()->create();
    $this->jenisCuti = JenisCuti::factory()->create(['potong_kuota' => true]);
});

it('mensinkronkan Absensi & Jadwal untuk setiap tanggal cuti approved', function () {
    Cuti::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'jenis_cuti_id' => $this->jenisCuti->id,
        'tanggal_mulai' => '2026-08-01',
        'tanggal_selesai' => '2026-08-03',
        'jumlah_hari' => 3,
        'status' => 'approved',
    ]);

    $this->artisan('absensi:backfill-cuti-dinas --force')->assertSuccessful();

    foreach (['2026-08-01', '2026-08-02', '2026-08-03'] as $tanggal) {
        expect(Absensi::where('karyawan_id', $this->karyawan->id)->where('tanggal', $tanggal)->first())
            ->not->toBeNull()
            ->status->toBe('cuti');

        expect(Jadwal::where('karyawan_id', $this->karyawan->id)->where('tanggal', $tanggal)->first())
            ->not->toBeNull()
            ->jenis->toBe('cuti');
    }
});

it('mensinkronkan Absensi & Jadwal untuk dinas approved', function () {
    Dinas::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'tanggal_mulai' => '2026-09-01',
        'tanggal_selesai' => '2026-09-02',
        'status' => 'approved',
    ]);

    $this->artisan('absensi:backfill-cuti-dinas --force')->assertSuccessful();

    expect(Absensi::where('karyawan_id', $this->karyawan->id)->where('tanggal', '2026-09-01')->first())
        ->not->toBeNull()
        ->status->toBe('dinas');
});

// --- REGRESSION GUARD: inti dari perbaikan command ini ---
//
// Sebelumnya command memanggil afterApprove() dalam loop. Sejak fase 22
// afterApprove() menaikkan KuotaCuti.terpakai, jadi menjalankan command ini
// menghitung ganda seluruh kuota terpakai — tanpa error apa pun.

it('TIDAK menyentuh KuotaCuti.terpakai sama sekali', function () {
    $kuota = KuotaCuti::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'jenis_cuti_id' => $this->jenisCuti->id,
        'tahun' => 2026,
        'kuota' => 12,
        'terpakai' => 3, // sudah mencerminkan cuti di bawah
    ]);

    Cuti::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'jenis_cuti_id' => $this->jenisCuti->id,
        'tanggal_mulai' => '2026-08-01',
        'tanggal_selesai' => '2026-08-03',
        'jumlah_hari' => 3,
        'status' => 'approved',
    ]);

    $this->artisan('absensi:backfill-cuti-dinas --force')->assertSuccessful();

    expect($kuota->fresh()->terpakai)->toBe(3); // bukan 6
});

it('idempoten — dijalankan dua kali hasilnya sama', function () {
    $kuota = KuotaCuti::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'jenis_cuti_id' => $this->jenisCuti->id,
        'tahun' => 2026,
        'kuota' => 12,
        'terpakai' => 2,
    ]);

    Cuti::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'jenis_cuti_id' => $this->jenisCuti->id,
        'tanggal_mulai' => '2026-08-01',
        'tanggal_selesai' => '2026-08-02',
        'jumlah_hari' => 2,
        'status' => 'approved',
    ]);

    $this->artisan('absensi:backfill-cuti-dinas --force')->assertSuccessful();
    $this->artisan('absensi:backfill-cuti-dinas --force')->assertSuccessful();

    expect($kuota->fresh()->terpakai)->toBe(2);
    expect(Absensi::where('karyawan_id', $this->karyawan->id)->count())->toBe(2);
    expect(Jadwal::where('karyawan_id', $this->karyawan->id)->count())->toBe(2);
});

it('tidak menyentuh cuti yang masih pending atau sudah ditolak', function () {
    Cuti::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'jenis_cuti_id' => $this->jenisCuti->id,
        'tanggal_mulai' => '2026-08-01',
        'tanggal_selesai' => '2026-08-02',
        'jumlah_hari' => 2,
        'status' => 'pending',
    ]);

    Cuti::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'jenis_cuti_id' => $this->jenisCuti->id,
        'tanggal_mulai' => '2026-08-10',
        'tanggal_selesai' => '2026-08-11',
        'jumlah_hari' => 2,
        'status' => 'rejected',
    ]);

    $this->artisan('absensi:backfill-cuti-dinas --force')->assertSuccessful();

    expect(Absensi::where('karyawan_id', $this->karyawan->id)->count())->toBe(0);
    expect(Jadwal::where('karyawan_id', $this->karyawan->id)->count())->toBe(0);
});

it('menimpa Jadwal bersumber manual di tanggal yang sama (perilaku yang disengaja)', function () {
    Jadwal::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'tanggal' => '2026-08-01',
        'jenis' => 'reguler',
        'sumber' => 'manual',
    ]);

    Cuti::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'jenis_cuti_id' => $this->jenisCuti->id,
        'tanggal_mulai' => '2026-08-01',
        'tanggal_selesai' => '2026-08-01',
        'jumlah_hari' => 1,
        'status' => 'approved',
    ]);

    $this->artisan('absensi:backfill-cuti-dinas --force')->assertSuccessful();

    expect(Jadwal::where('karyawan_id', $this->karyawan->id)->where('tanggal', '2026-08-01')->first())
        ->jenis->toBe('cuti')
        ->sumber->toBe('generate');
});
