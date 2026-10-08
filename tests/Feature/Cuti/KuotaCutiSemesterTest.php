<?php

use App\Models\Cuti;
use App\Models\JenisCuti;
use App\Models\Karyawan;
use App\Models\KuotaCuti;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->karyawan = Karyawan::factory()->create();

    $this->jenisCutiTahunan = JenisCuti::factory()->create([
        'nama' => 'Cuti Tahunan Test',
        'periode_kuota' => JenisCuti::PERIODE_SEMESTERAN,
        'default_kuota' => 6,
        'potong_kuota' => true,
        'perlu_lampiran' => false,
        'is_active' => true,
    ]);

    $this->jenisCutiSakit = JenisCuti::factory()->create([
        'nama' => 'Cuti Sakit Test',
        'periode_kuota' => JenisCuti::PERIODE_TAHUNAN,
        'default_kuota' => 0,
        'potong_kuota' => false,
        'perlu_lampiran' => true,
        'is_active' => true,
    ]);
});

it('approve cuti tahunan di semester 1 membuat row KuotaCuti dengan semester=1', function () {
    $cuti = Cuti::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'jenis_cuti_id' => $this->jenisCutiTahunan->id,
        'tanggal_mulai' => '2026-03-10',
        'tanggal_selesai' => '2026-03-12',
        'jumlah_hari' => 3,
        'status' => 'pending',
    ]);

    $cuti->approve(auth()->user() ?? \App\Models\User::factory()->create());

    $kuota = KuotaCuti::where('karyawan_id', $this->karyawan->id)
        ->where('jenis_cuti_id', $this->jenisCutiTahunan->id)
        ->where('tahun', 2026)
        ->first();

    expect($kuota)->not->toBeNull()
        ->and($kuota->semester)->toBe(1)
        ->and($kuota->kuota)->toBe(6)
        ->and($kuota->terpakai)->toBe(3);
});

it('approve cuti tahunan di semester 2 membuat row TERPISAH dari semester 1', function () {
    // Semester 1 sudah terpakai habis
    KuotaCuti::create([
        'karyawan_id' => $this->karyawan->id,
        'jenis_cuti_id' => $this->jenisCutiTahunan->id,
        'tahun' => 2026,
        'semester' => 1,
        'kuota' => 6,
        'terpakai' => 6,
    ]);

    $cuti = Cuti::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'jenis_cuti_id' => $this->jenisCutiTahunan->id,
        'tanggal_mulai' => '2026-08-10',
        'tanggal_selesai' => '2026-08-12',
        'jumlah_hari' => 3,
        'status' => 'pending',
    ]);

    // Approve semester 2 TIDAK boleh gagal walau semester 1 sudah habis —
    // row-nya beda, saldo tidak ikut nyambung.
    $cuti->approve(auth()->user() ?? \App\Models\User::factory()->create());

    $kuotaSemester2 = KuotaCuti::where('karyawan_id', $this->karyawan->id)
        ->where('jenis_cuti_id', $this->jenisCutiTahunan->id)
        ->where('tahun', 2026)
        ->where('semester', 2)
        ->first();

    expect($kuotaSemester2)->not->toBeNull()
        ->and($kuotaSemester2->terpakai)->toBe(3)
        ->and(KuotaCuti::where('semester', 1)->first()->terpakai)->toBe(6); // tidak berubah
});

it('approve jenis cuti tahunan biasa (bukan semesteran) tetap pakai semester=0', function () {
    $cuti = Cuti::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'jenis_cuti_id' => $this->jenisCutiSakit->id,
        'tanggal_mulai' => '2026-05-10',
        'tanggal_selesai' => '2026-05-10',
        'jumlah_hari' => 1,
        'status' => 'pending',
        'lampiran' => 'lampiran-cuti/dummy.pdf',
    ]);

    $cuti->approve(auth()->user() ?? \App\Models\User::factory()->create());

    // potong_kuota=false, jadi tidak ada row KuotaCuti sama sekali
    expect(KuotaCuti::where('karyawan_id', $this->karyawan->id)
        ->where('jenis_cuti_id', $this->jenisCutiSakit->id)
        ->exists())->toBeFalse();
});

it('hariPendingUntuk dengan semester hanya menghitung pending di semester yang sama', function () {
    Cuti::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'jenis_cuti_id' => $this->jenisCutiTahunan->id,
        'tanggal_mulai' => '2026-03-01',
        'jumlah_hari' => 2,
        'status' => 'pending',
    ]);

    Cuti::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'jenis_cuti_id' => $this->jenisCutiTahunan->id,
        'tanggal_mulai' => '2026-09-01',
        'jumlah_hari' => 4,
        'status' => 'pending',
    ]);

    expect(Cuti::hariPendingUntuk($this->karyawan->id, $this->jenisCutiTahunan->id, 2026, null, 1))->toBe(2)
        ->and(Cuti::hariPendingUntuk($this->karyawan->id, $this->jenisCutiTahunan->id, 2026, null, 2))->toBe(4);
});
