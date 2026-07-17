<?php

use App\Models\Absensi;
use App\Models\Cuti;
use App\Models\Instansi;
use App\Models\JenisCuti;
use App\Models\Karyawan;
use App\Models\KuotaCuti;
use App\Models\Shift;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->instansi = Instansi::factory()->create();
    $this->karyawan = Karyawan::factory()->for($this->instansi)->create();
});

it('approve mensinkronkan absensi untuk setiap tanggal dalam rentang cuti', function () {
    $jenisCuti = JenisCuti::factory()->create();
    $cuti = Cuti::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'jenis_cuti_id' => $jenisCuti->id,
        'tanggal_mulai' => '2026-08-01',
        'tanggal_selesai' => '2026-08-03',
        'jumlah_hari' => 3,
    ]);

    $cuti->approve($this->admin);

    $absensi = Absensi::where('karyawan_id', $this->karyawan->id)
        ->whereBetween('tanggal', ['2026-08-01', '2026-08-03'])
        ->get();

    expect($absensi)->toHaveCount(3);
    expect($absensi->every(fn ($a) => $a->status === 'cuti'))->toBeTrue();
});

it('tidak menimpa absensi yang sudah punya waktu_masuk asli', function () {
    $shift = Shift::factory()->for($this->instansi)->create();
    $jenisCuti = JenisCuti::factory()->create();

    // Karyawan sudah absen fisik di hari kedua sebelum cuti diajukan
    // (mis. sempat masuk sebentar lalu ternyata sakit, atau data historis)
    Absensi::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'shift_id' => $shift->id,
        'tanggal' => '2026-08-02',
        'waktu_masuk' => '2026-08-02 07:30:00',
        'status' => 'tepat_waktu',
    ]);

    $cuti = Cuti::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'jenis_cuti_id' => $jenisCuti->id,
        'tanggal_mulai' => '2026-08-01',
        'tanggal_selesai' => '2026-08-03',
        'jumlah_hari' => 3,
    ]);

    $cuti->approve($this->admin);

    $absensiHari2 = Absensi::where('karyawan_id', $this->karyawan->id)
        ->whereDate('tanggal', '2026-08-02')->first();
    $absensiHari1 = Absensi::where('karyawan_id', $this->karyawan->id)
        ->whereDate('tanggal', '2026-08-01')->first();
    $absensiHari3 = Absensi::where('karyawan_id', $this->karyawan->id)
        ->whereDate('tanggal', '2026-08-03')->first();

    expect($absensiHari2->status)->toBe('tepat_waktu') // tidak ditimpa
        ->and($absensiHari1->status)->toBe('cuti')
        ->and($absensiHari3->status)->toBe('cuti');
});

it('menimpa absensi existing yang belum ada waktu_masuk', function () {
    $jenisCuti = JenisCuti::factory()->create();

    // Row Absensi sudah ada (mis. dibuat command rekap-harian sebagai alpha)
    // tapi belum ada waktu_masuk - harus tetap ditimpa jadi cuti
    Absensi::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'tanggal' => '2026-08-01',
        'waktu_masuk' => null,
        'status' => 'alpha',
    ]);

    $cuti = Cuti::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'jenis_cuti_id' => $jenisCuti->id,
        'tanggal_mulai' => '2026-08-01',
        'tanggal_selesai' => '2026-08-01',
        'jumlah_hari' => 1,
    ]);

    $cuti->approve($this->admin);

    $absensi = Absensi::where('karyawan_id', $this->karyawan->id)
        ->whereDate('tanggal', '2026-08-01')->first();

    expect($absensi->status)->toBe('cuti');
});

it('memotong kuota jika jenis_cuti potong_kuota true', function () {
    $jenisCuti = JenisCuti::factory()->create(['potong_kuota' => true]);
    KuotaCuti::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'jenis_cuti_id' => $jenisCuti->id,
        'tahun' => 2026,
        'kuota' => 12,
        'terpakai' => 2,
    ]);

    $cuti = Cuti::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'jenis_cuti_id' => $jenisCuti->id,
        'tanggal_mulai' => '2026-08-01',
        'tanggal_selesai' => '2026-08-03',
        'jumlah_hari' => 3,
    ]);

    $cuti->approve($this->admin);

    $kuota = KuotaCuti::where('karyawan_id', $this->karyawan->id)
        ->where('jenis_cuti_id', $jenisCuti->id)
        ->where('tahun', 2026)
        ->first();

    expect($kuota->terpakai)->toBe(5); // 2 + 3
});

it('tidak memotong kuota jika jenis_cuti potong_kuota false', function () {
    $jenisCuti = JenisCuti::factory()->tanpaPotongKuota()->create();
    KuotaCuti::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'jenis_cuti_id' => $jenisCuti->id,
        'tahun' => 2026,
        'kuota' => 12,
        'terpakai' => 2,
    ]);

    $cuti = Cuti::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'jenis_cuti_id' => $jenisCuti->id,
        'tanggal_mulai' => '2026-08-01',
        'tanggal_selesai' => '2026-08-03',
        'jumlah_hari' => 3,
    ]);

    $cuti->approve($this->admin);

    $kuota = KuotaCuti::where('karyawan_id', $this->karyawan->id)
        ->where('jenis_cuti_id', $jenisCuti->id)
        ->where('tahun', 2026)
        ->first();

    expect($kuota->terpakai)->toBe(2); // tidak berubah
});

it('tidak error kalau belum ada row KuotaCuti untuk karyawan/jenis/tahun tersebut', function () {
    // Sengaja TIDAK bikin KuotaCuti sama sekali - increment() pada query
    // kosong tidak akan menimpa row apapun, tapi juga tidak boleh throw.
    $jenisCuti = JenisCuti::factory()->create(['potong_kuota' => true]);

    $cuti = Cuti::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'jenis_cuti_id' => $jenisCuti->id,
        'tanggal_mulai' => '2026-08-01',
        'tanggal_selesai' => '2026-08-01',
        'jumlah_hari' => 1,
    ]);

    expect(fn () => $cuti->approve($this->admin))->not->toThrow(\Throwable::class);
    expect(KuotaCuti::count())->toBe(0);
});
