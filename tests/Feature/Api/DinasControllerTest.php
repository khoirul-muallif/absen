<?php

use App\Models\Dinas;
use App\Models\Karyawan;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->karyawan = Karyawan::factory()->create();
    Sanctum::actingAs($this->karyawan);
});

test('bisa mengajukan dinas baru', function () {
    $response = $this->postJson('/api/dinas', [
        'tanggal_mulai'   => '2026-08-01',
        'tanggal_selesai' => '2026-08-03',
        'tujuan'          => 'Jakarta',
        'keperluan'       => 'Pelatihan BPJS Kesehatan',
    ]);

    $response->assertStatus(201)->assertJson(['success' => true]);
    expect(Dinas::where('karyawan_id', $this->karyawan->id)->where('status', 'pending')->exists())->toBeTrue();
});

test('menolak pengajuan tanpa tujuan', function () {
    $response = $this->postJson('/api/dinas', [
        'tanggal_mulai'   => '2026-08-01',
        'tanggal_selesai' => '2026-08-03',
        'keperluan'       => 'Tes',
    ]);

    $response->assertStatus(422);
});

test('menolak tanggal_selesai sebelum tanggal_mulai', function () {
    $response = $this->postJson('/api/dinas', [
        'tanggal_mulai'   => '2026-08-05',
        'tanggal_selesai' => '2026-08-01',
        'tujuan'          => 'Jakarta',
        'keperluan'       => 'Tes',
    ]);

    $response->assertStatus(422);
});

test('riwayat hanya menampilkan dinas milik karyawan yang login', function () {
    Dinas::factory()->create(['karyawan_id' => $this->karyawan->id]);
    $karyawanLain = Karyawan::factory()->create();
    Dinas::factory()->create(['karyawan_id' => $karyawanLain->id]);

    $response = $this->getJson('/api/dinas');

    $response->assertStatus(200)->assertJsonCount(1, 'data.records');
});

test('riwayat bisa difilter berdasarkan status', function () {
    Dinas::factory()->create(['karyawan_id' => $this->karyawan->id, 'status' => 'pending']);
    Dinas::factory()->create(['karyawan_id' => $this->karyawan->id, 'status' => 'approved']);

    $response = $this->getJson('/api/dinas?status=approved');

    $response->assertStatus(200)->assertJsonCount(1, 'data.records');
});

test('bisa membatalkan dinas yang masih pending', function () {
    $dinas = Dinas::factory()->create(['karyawan_id' => $this->karyawan->id, 'status' => 'pending']);

    $response = $this->deleteJson("/api/dinas/{$dinas->id}");

    $response->assertStatus(200);
    expect(Dinas::find($dinas->id))->toBeNull();
});

test('tidak bisa membatalkan dinas yang sudah approved', function () {
    $dinas = Dinas::factory()->create(['karyawan_id' => $this->karyawan->id, 'status' => 'approved']);

    $response = $this->deleteJson("/api/dinas/{$dinas->id}");

    $response->assertStatus(422);
    expect(Dinas::find($dinas->id))->not->toBeNull();
});

test('404 kalau membatalkan dinas milik karyawan lain', function () {
    $karyawanLain = Karyawan::factory()->create();
    $dinas = Dinas::factory()->create(['karyawan_id' => $karyawanLain->id, 'status' => 'pending']);

    $response = $this->deleteJson("/api/dinas/{$dinas->id}");

    $response->assertStatus(404);
});
