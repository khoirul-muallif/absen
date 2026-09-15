<?php

use App\Models\Absensi;
use App\Models\Cuti;
use App\Models\Instansi;
use App\Models\JenisCuti;
use App\Models\Karyawan;
use App\Models\KuotaCuti;
use App\Models\Shift;
use App\Models\User;
use App\Models\Jadwal;

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

it('menimpa absensi yang sudah punya waktu_masuk asli', function () {
    $shift = Shift::factory()->for($this->instansi)->create();
    $jenisCuti = JenisCuti::factory()->create();

    // Karyawan sempat absen fisik sebelum cuti-nya di-approve admin
    Absensi::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'shift_id' => $shift->id,
        'tanggal' => '2026-08-02',
        'waktu_masuk' => '2026-08-02 07:30:00',
        'status' => 'tepat_waktu',
        'menit_terlambat' => 0,
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

    expect($absensiHari2->status)->toBe('cuti')
        ->and($absensiHari2->waktu_masuk)->toBeNull()
        ->and($absensiHari2->menit_terlambat)->toBe(0)
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

it('approve mensinkronkan jadwal jadi jenis cuti untuk setiap tanggal dalam rentang', function () {
    $jenisCuti = JenisCuti::factory()->create();
    $cuti = Cuti::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'jenis_cuti_id' => $jenisCuti->id,
        'tanggal_mulai' => '2026-08-01',
        'tanggal_selesai' => '2026-08-03',
        'jumlah_hari' => 3,
    ]);

    $cuti->approve($this->admin);

    $jadwal = Jadwal::where('karyawan_id', $this->karyawan->id)
        ->whereBetween('tanggal', ['2026-08-01', '2026-08-03'])
        ->get();

    expect($jadwal)->toHaveCount(3);
    expect($jadwal->every(fn ($j) => $j->jenis === 'cuti' && $j->shift_id === null && $j->sumber === 'generate'))
        ->toBeTrue();
});

it('menimpa jadwal manual yang sudah ada sebelum cuti disetujui', function () {
    $shift = Shift::factory()->for($this->instansi)->create();
    $jenisCuti = JenisCuti::factory()->create();

    // Admin sudah input jadwal manual duluan sebelum tau karyawan mau cuti
    Jadwal::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'shift_id' => $shift->id,
        'tanggal' => '2026-08-02',
        'jenis' => 'piket',
        'sumber' => 'manual',
    ]);

    $cuti = Cuti::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'jenis_cuti_id' => $jenisCuti->id,
        'tanggal_mulai' => '2026-08-01',
        'tanggal_selesai' => '2026-08-03',
        'jumlah_hari' => 3,
    ]);

    $cuti->approve($this->admin);

    $jadwalHari2 = Jadwal::where('karyawan_id', $this->karyawan->id)
        ->whereDate('tanggal', '2026-08-02')->first();

    expect($jadwalHari2->jenis)->toBe('cuti')
        ->and($jadwalHari2->shift_id)->toBeNull()
        ->and($jadwalHari2->sumber)->toBe('generate');
});

it('membuat jadwal baru kalau belum ada jadwal sama sekali untuk tanggal cuti', function () {
    $jenisCuti = JenisCuti::factory()->create();

    expect(Jadwal::where('karyawan_id', $this->karyawan->id)->count())->toBe(0);

    $cuti = Cuti::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'jenis_cuti_id' => $jenisCuti->id,
        'tanggal_mulai' => '2026-08-01',
        'tanggal_selesai' => '2026-08-01',
        'jumlah_hari' => 1,
    ]);

    $cuti->approve($this->admin);

    expect(Jadwal::where('karyawan_id', $this->karyawan->id)->count())->toBe(1);
});

it('approve gagal dengan exception kalau kuota tidak cukup, status tetap pending', function () {
    $jenisCuti = JenisCuti::factory()->create(['potong_kuota' => true]);
    KuotaCuti::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'jenis_cuti_id' => $jenisCuti->id,
        'tahun' => 2026,
        'kuota' => 5,
        'terpakai' => 3,
    ]);

    $cuti = Cuti::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'jenis_cuti_id' => $jenisCuti->id,
        'tanggal_mulai' => '2026-08-01',
        'tanggal_selesai' => '2026-08-03',
        'jumlah_hari' => 3, // 3 + 3 (terpakai) = 6 > 5 (kuota)
        'status' => 'pending',
    ]);

    expect(fn () => $cuti->approve($this->admin))
        ->toThrow(\App\Exceptions\KuotaCutiTidakCukupException::class);

    $cuti->refresh();
    $kuota = KuotaCuti::where('karyawan_id', $this->karyawan->id)
        ->where('jenis_cuti_id', $jenisCuti->id)
        ->where('tahun', 2026)
        ->first();

    expect($cuti->status)->toBe('pending')
        ->and($cuti->approved_by)->toBeNull()
        ->and($kuota->terpakai)->toBe(3); // tidak berubah, tetap 3

    // Jadwal/Absensi juga tidak boleh ikut ke-sync karena seluruh transaction rollback
    expect(Jadwal::where('karyawan_id', $this->karyawan->id)->count())->toBe(0);
    expect(Absensi::where('karyawan_id', $this->karyawan->id)->count())->toBe(0);
});

it('approve berhasil kalau kuota pas-pasan cukup (edge case tepat di batas)', function () {
    $jenisCuti = JenisCuti::factory()->create(['potong_kuota' => true]);
    KuotaCuti::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'jenis_cuti_id' => $jenisCuti->id,
        'tahun' => 2026,
        'kuota' => 5,
        'terpakai' => 2,
    ]);

    $cuti = Cuti::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'jenis_cuti_id' => $jenisCuti->id,
        'tanggal_mulai' => '2026-08-01',
        'tanggal_selesai' => '2026-08-03',
        'jumlah_hari' => 3, // 3 + 2 = 5, pas sama dengan kuota
        'status' => 'pending',
    ]);

    $cuti->approve($this->admin);

    $kuota = KuotaCuti::where('karyawan_id', $this->karyawan->id)
        ->where('jenis_cuti_id', $jenisCuti->id)
        ->where('tahun', 2026)
        ->first();

    expect($cuti->fresh()->status)->toBe('approved')
        ->and($kuota->terpakai)->toBe(5);
});

// ============================================================================
// TAMBAHAN untuk tests/Unit/Models/CutiTest.php
//
// Tempel blok di bawah ini di AKHIR file. Cek dulu import yang sudah ada —
// kemungkinan besar Cuti, JenisCuti, Karyawan, KuotaCuti sudah ke-import.
// ============================================================================

// --- Cuti::hariPendingUntuk() ---
//
// Helper ini dipakai di 3 tempat (placeholder CutiForm, CutiInfolist, tooltip
// approve CutisTable) dan di CutiController::ajukan(). Risiko utamanya adalah
// record yang sedang dinilai ikut terhitung sebagai "pending lain" — bikin
// angkanya dobel tanpa error apa pun.

it('hariPendingUntuk menjumlahkan jumlah_hari dari semua pengajuan pending', function () {
    $karyawan = \App\Models\Karyawan::factory()->create();
    $jenisCuti = \App\Models\JenisCuti::factory()->create();

    \App\Models\Cuti::factory()->create([
        'karyawan_id' => $karyawan->id,
        'jenis_cuti_id' => $jenisCuti->id,
        'tanggal_mulai' => '2026-03-01',
        'tanggal_selesai' => '2026-03-02',
        'jumlah_hari' => 2,
        'status' => 'pending',
    ]);

    \App\Models\Cuti::factory()->create([
        'karyawan_id' => $karyawan->id,
        'jenis_cuti_id' => $jenisCuti->id,
        'tanggal_mulai' => '2026-05-01',
        'tanggal_selesai' => '2026-05-03',
        'jumlah_hari' => 3,
        'status' => 'pending',
    ]);

    expect(\App\Models\Cuti::hariPendingUntuk($karyawan->id, $jenisCuti->id, 2026))->toBe(5);
});

it('hariPendingUntuk mengecualikan record yang sedang dinilai', function () {
    $karyawan = \App\Models\Karyawan::factory()->create();
    $jenisCuti = \App\Models\JenisCuti::factory()->create();

    $sedangDinilai = \App\Models\Cuti::factory()->create([
        'karyawan_id' => $karyawan->id,
        'jenis_cuti_id' => $jenisCuti->id,
        'tanggal_mulai' => '2026-03-01',
        'tanggal_selesai' => '2026-03-02',
        'jumlah_hari' => 2,
        'status' => 'pending',
    ]);

    \App\Models\Cuti::factory()->create([
        'karyawan_id' => $karyawan->id,
        'jenis_cuti_id' => $jenisCuti->id,
        'tanggal_mulai' => '2026-05-01',
        'tanggal_selesai' => '2026-05-03',
        'jumlah_hari' => 3,
        'status' => 'pending',
    ]);

    // Tanpa exclude: 5. Dengan exclude record sendiri: 3.
    expect(\App\Models\Cuti::hariPendingUntuk($karyawan->id, $jenisCuti->id, 2026, $sedangDinilai->id))
        ->toBe(3);
});

it('hariPendingUntuk mengabaikan status selain pending', function () {
    $karyawan = \App\Models\Karyawan::factory()->create();
    $jenisCuti = \App\Models\JenisCuti::factory()->create();

    foreach (['approved', 'rejected'] as $status) {
        \App\Models\Cuti::factory()->create([
            'karyawan_id' => $karyawan->id,
            'jenis_cuti_id' => $jenisCuti->id,
            'tanggal_mulai' => '2026-03-01',
            'tanggal_selesai' => '2026-03-04',
            'jumlah_hari' => 4,
            'status' => $status,
        ]);
    }

    expect(\App\Models\Cuti::hariPendingUntuk($karyawan->id, $jenisCuti->id, 2026))->toBe(0);
});

it('hariPendingUntuk terpisah per tahun, jenis cuti, dan karyawan', function () {
    $karyawan = \App\Models\Karyawan::factory()->create();
    $karyawanLain = \App\Models\Karyawan::factory()->create();
    $jenisCuti = \App\Models\JenisCuti::factory()->create();
    $jenisLain = \App\Models\JenisCuti::factory()->create();

    // Tahun berbeda
    \App\Models\Cuti::factory()->create([
        'karyawan_id' => $karyawan->id,
        'jenis_cuti_id' => $jenisCuti->id,
        'tanggal_mulai' => '2025-03-01',
        'tanggal_selesai' => '2025-03-02',
        'jumlah_hari' => 2,
        'status' => 'pending',
    ]);

    // Jenis cuti berbeda
    \App\Models\Cuti::factory()->create([
        'karyawan_id' => $karyawan->id,
        'jenis_cuti_id' => $jenisLain->id,
        'tanggal_mulai' => '2026-03-01',
        'tanggal_selesai' => '2026-03-02',
        'jumlah_hari' => 2,
        'status' => 'pending',
    ]);

    // Karyawan berbeda
    \App\Models\Cuti::factory()->create([
        'karyawan_id' => $karyawanLain->id,
        'jenis_cuti_id' => $jenisCuti->id,
        'tanggal_mulai' => '2026-03-01',
        'tanggal_selesai' => '2026-03-02',
        'jumlah_hari' => 2,
        'status' => 'pending',
    ]);

    expect(\App\Models\Cuti::hariPendingUntuk($karyawan->id, $jenisCuti->id, 2026))->toBe(0);
});
