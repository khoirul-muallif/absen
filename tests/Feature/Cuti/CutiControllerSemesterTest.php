<?php

use App\Models\JenisCuti;
use App\Models\Karyawan;
use App\Models\KuotaCuti;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    // Dibekukan ke tanggal sebelum semua rentang tanggal di test ini, biar
    // aturan after_or_equal:today (fase 23) tidak ikut menolak — pola sama
    // seperti CutiControllerTest.
    $this->travelTo(Carbon::parse('2026-06-01 08:00:00'));

    $this->karyawan = Karyawan::factory()->create();
    Sanctum::actingAs($this->karyawan);

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

it('menolak pengajuan cuti tahunan yang melintasi pergantian semester', function () {
    $response = $this->postJson('/api/cuti', [
        'jenis_cuti_id'   => $this->jenisCutiTahunan->id,
        'tanggal_mulai'   => '2026-06-28',
        'tanggal_selesai' => '2026-07-02',
        'alasan'          => 'test lintas semester',
    ]);

    $response->assertStatus(422);
    expect($response->json('message'))->toContain('semester');
});

it('mengizinkan pengajuan cuti tahunan yang masih dalam satu semester', function () {
    $response = $this->postJson('/api/cuti', [
        'jenis_cuti_id'   => $this->jenisCutiTahunan->id,
        'tanggal_mulai'   => '2026-07-05',
        'tanggal_selesai' => '2026-07-07',
        'alasan'          => 'test dalam semester',
    ]);

    $response->assertStatus(201);
});

it('menolak pengajuan yang melebihi sisa kuota semester tertentu, bukan kuota tahun penuh', function () {
    KuotaCuti::create([
        'karyawan_id'   => $this->karyawan->id,
        'jenis_cuti_id' => $this->jenisCutiTahunan->id,
        'tahun'         => 2026,
        'semester'      => 2,
        'kuota'         => 6,
        'terpakai'      => 5,
    ]);

    $response = $this->postJson('/api/cuti', [
        'jenis_cuti_id'   => $this->jenisCutiTahunan->id,
        'tanggal_mulai'   => '2026-08-10',
        'tanggal_selesai' => '2026-08-12',
        'alasan'          => 'test sisa semester habis',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('data.sisa_kuota', 1);
});

it('kuota endpoint memecah jenis cuti semesteran jadi 2 baris, jenis tahunan tetap 1 baris', function () {
    $response = $this->getJson('/api/cuti/kuota?tahun=2026');

    $response->assertStatus(200);

    $records = collect($response->json('data.records'));

    $barisTahunan = $records->where('jenis_cuti_id', $this->jenisCutiTahunan->id);
    $barisSakit   = $records->where('jenis_cuti_id', $this->jenisCutiSakit->id);

    expect($barisTahunan)->toHaveCount(2)
        ->and($barisTahunan->pluck('semester')->sort()->values()->all())->toBe([1, 2])
        ->and($barisSakit)->toHaveCount(1)
        ->and($barisSakit->first()['semester'])->toBeNull();
});
