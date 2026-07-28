<?php

use App\Models\Dinas;
use App\Models\Karyawan;
use Laravel\Sanctum\Sanctum;
use App\Models\Cuti;

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

test('menolak pengajuan dinas untuk tanggal_mulai yang sudah lewat', function () {
    $response = $this->postJson('/api/dinas', [
        'tanggal_mulai'   => today()->subDay()->toDateString(),
        'tanggal_selesai' => today()->addDay()->toDateString(),
        'tujuan'          => 'Kota A',
        'keperluan'       => 'Tes tanggal lewat',
    ]);

    $response->assertStatus(422);
    expect(Dinas::where('karyawan_id', $this->karyawan->id)->exists())->toBeFalse();
});

test('menolak pengajuan dinas yang melintasi pergantian tahun', function () {
    $response = $this->postJson('/api/dinas', [
        'tanggal_mulai'   => '2026-12-30',
        'tanggal_selesai' => '2027-01-02',
        'tujuan'          => 'Kota B',
        'keperluan'       => 'Tes lintas tahun',
    ]);

    $response->assertStatus(422)
        ->assertJsonFragment(['message' => 'Pengajuan dinas tidak boleh melintasi pergantian tahun. Ajukan terpisah untuk masing-masing tahun.']);
    expect(Dinas::where('karyawan_id', $this->karyawan->id)->exists())->toBeFalse();
});

test('menolak pengajuan dinas yang bentrok dengan dinas approved lain', function () {
    Dinas::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'tanggal_mulai' => '2026-08-01',
        'tanggal_selesai' => '2026-08-05',
        'status' => 'approved',
    ]);

    $response = $this->postJson('/api/dinas', [
        'tanggal_mulai'   => '2026-08-03',
        'tanggal_selesai' => '2026-08-07',
        'tujuan'          => 'Kota C',
        'keperluan'       => 'Tes bentrok dinas',
    ]);

    $response->assertStatus(422);
    expect(Dinas::where('karyawan_id', $this->karyawan->id)->where('status', 'pending')->exists())->toBeFalse();
});

test('menolak pengajuan dinas yang bentrok dengan cuti approved lain', function () {
    Cuti::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'jenis_cuti_id' => \App\Models\JenisCuti::factory()->create()->id,
        'tanggal_mulai' => '2026-08-01',
        'tanggal_selesai' => '2026-08-05',
        'status' => 'approved',
    ]);

    $response = $this->postJson('/api/dinas', [
        'tanggal_mulai'   => '2026-08-03',
        'tanggal_selesai' => '2026-08-07',
        'tujuan'          => 'Kota D',
        'keperluan'       => 'Tes bentrok cuti',
    ]);

    $response->assertStatus(422);
    expect(Dinas::where('karyawan_id', $this->karyawan->id)->where('status', 'pending')->exists())->toBeFalse();
});
