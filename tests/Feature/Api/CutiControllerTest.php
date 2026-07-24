<?php

use App\Models\Cuti;
use App\Models\JenisCuti;
use App\Models\Karyawan;
use App\Models\KuotaCuti;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->karyawan = Karyawan::factory()->create();
    Sanctum::actingAs($this->karyawan);
    $this->jenisCuti = JenisCuti::factory()->create([
        'nama' => 'Cuti Tahunan',
        'default_kuota' => 12,
        'potong_kuota' => true,
        'perlu_lampiran' => false,
        'is_active' => true,
    ]);
});

test('bisa melihat sisa kuota tanpa row KuotaCuti (fallback ke default_kuota)', function () {
    $response = $this->getJson('/api/cuti/kuota?tahun=2026');

    $response->assertStatus(200);
    $record = collect($response->json('data.records'))->firstWhere('jenis_cuti_id', $this->jenisCuti->id);
    expect($record['sisa'])->toBe(12);
});

test('sisa kuota mencerminkan KuotaCuti yang sudah ada', function () {
    KuotaCuti::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'jenis_cuti_id' => $this->jenisCuti->id,
        'tahun' => 2026,
        'kuota' => 12,
        'terpakai' => 5,
    ]);

    $response = $this->getJson('/api/cuti/kuota?tahun=2026');

    $record = collect($response->json('data.records'))->firstWhere('jenis_cuti_id', $this->jenisCuti->id);
    expect($record['sisa'])->toBe(7);
});

test('bisa mengajukan cuti dengan jumlah_hari dihitung otomatis', function () {
    $response = $this->postJson('/api/cuti', [
        'jenis_cuti_id'   => $this->jenisCuti->id,
        'tanggal_mulai'   => '2026-08-01',
        'tanggal_selesai' => '2026-08-03',
        'alasan'          => 'Liburan keluarga',
    ]);

    $response->assertStatus(201)->assertJson([
        'success' => true,
        'data' => ['jumlah_hari' => 3],
    ]);
});

test('menolak pengajuan kalau melebihi sisa kuota', function () {
    KuotaCuti::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'jenis_cuti_id' => $this->jenisCuti->id,
        'tahun' => 2026,
        'kuota' => 12,
        'terpakai' => 10,
    ]);

    $response = $this->postJson('/api/cuti', [
        'jenis_cuti_id'   => $this->jenisCuti->id,
        'tanggal_mulai'   => '2026-08-01',
        'tanggal_selesai' => '2026-08-05', // 5 hari, sisa cuma 2
        'alasan'          => 'Liburan panjang',
    ]);

    $response->assertStatus(422);
    expect(Cuti::where('karyawan_id', $this->karyawan->id)->exists())->toBeFalse();
});

test('jenis cuti yang tidak potong_kuota tidak dicek terhadap kuota', function () {
    $jenisSakit = JenisCuti::factory()->create([
        'nama' => 'Cuti Sakit',
        'potong_kuota' => false,
        'perlu_lampiran' => true,
    ]);

    $response = $this->postJson('/api/cuti', [
        'jenis_cuti_id'   => $jenisSakit->id,
        'tanggal_mulai'   => '2026-08-01',
        'tanggal_selesai' => '2026-08-10',
        'alasan'          => 'Sakit demam berdarah',
        'lampiran'        => \Illuminate\Http\UploadedFile::fake()->create('surat.pdf', 500),
    ]);

    $response->assertStatus(201);
});

test('menolak jika jenis cuti perlu_lampiran tapi tidak ada file', function () {
    $jenisSakit = JenisCuti::factory()->create([
        'nama' => 'Cuti Sakit',
        'potong_kuota' => false,
        'perlu_lampiran' => true,
    ]);

    $response = $this->postJson('/api/cuti', [
        'jenis_cuti_id'   => $jenisSakit->id,
        'tanggal_mulai'   => '2026-08-01',
        'tanggal_selesai' => '2026-08-02',
        'alasan'          => 'Sakit',
    ]);

    $response->assertStatus(422);
});

test('menolak jenis_cuti_id yang tidak aktif', function () {
    $jenisNonAktif = JenisCuti::factory()->create(['is_active' => false]);

    $response = $this->postJson('/api/cuti', [
        'jenis_cuti_id'   => $jenisNonAktif->id,
        'tanggal_mulai'   => '2026-08-01',
        'tanggal_selesai' => '2026-08-02',
        'alasan'          => 'Tes',
    ]);

    $response->assertStatus(422);
});

test('riwayat hanya menampilkan cuti milik karyawan yang login', function () {
    Cuti::factory()->create(['karyawan_id' => $this->karyawan->id, 'jenis_cuti_id' => $this->jenisCuti->id]);
    $karyawanLain = Karyawan::factory()->create();
    Cuti::factory()->create(['karyawan_id' => $karyawanLain->id, 'jenis_cuti_id' => $this->jenisCuti->id]);

    $response = $this->getJson('/api/cuti');

    $response->assertStatus(200)->assertJsonCount(1, 'data.records');
});

test('bisa membatalkan cuti yang masih pending', function () {
    $cuti = Cuti::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'jenis_cuti_id' => $this->jenisCuti->id,
        'status' => 'pending',
    ]);

    $response = $this->deleteJson("/api/cuti/{$cuti->id}");

    $response->assertStatus(200);
    expect(Cuti::find($cuti->id))->toBeNull();
});

test('tidak bisa membatalkan cuti yang sudah approved', function () {
    $cuti = Cuti::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'jenis_cuti_id' => $this->jenisCuti->id,
        'status' => 'approved',
    ]);

    $response = $this->deleteJson("/api/cuti/{$cuti->id}");

    $response->assertStatus(422);
    expect(Cuti::find($cuti->id))->not->toBeNull();
});

test('404 kalau membatalkan cuti milik karyawan lain', function () {
    $karyawanLain = Karyawan::factory()->create();
    $cuti = Cuti::factory()->create([
        'karyawan_id' => $karyawanLain->id,
        'jenis_cuti_id' => $this->jenisCuti->id,
        'status' => 'pending',
    ]);

    $response = $this->deleteJson("/api/cuti/{$cuti->id}");

    $response->assertStatus(404);
});
