<?php

use App\Models\Izin;
use App\Models\Karyawan;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->karyawan = Karyawan::factory()->create();
    Sanctum::actingAs($this->karyawan);
});

test('bisa mengajukan izin baru', function () {
    $response = $this->postJson('/api/izin', [
        'tanggal'     => '2026-08-01',
        'jam_keluar'  => '10:00',
        'jam_kembali' => '12:00',
        'keperluan'   => 'Ke bank',
    ]);

    $response->assertStatus(201)
        ->assertJson(['success' => true]);

    expect(Izin::where('karyawan_id', $this->karyawan->id)->where('status', 'pending')->exists())->toBeTrue();
});

test('menolak pengajuan tanpa keperluan', function () {
    $response = $this->postJson('/api/izin', [
        'tanggal'    => '2026-08-01',
        'jam_keluar' => '10:00',
    ]);

    $response->assertStatus(422);
});

test('menolak jam_kembali sebelum jam_keluar', function () {
    $response = $this->postJson('/api/izin', [
        'tanggal'     => '2026-08-01',
        'jam_keluar'  => '12:00',
        'jam_kembali' => '10:00',
        'keperluan'   => 'Tes',
    ]);

    $response->assertStatus(422);
});

test('riwayat hanya menampilkan izin milik karyawan yang login', function () {
    Izin::factory()->create(['karyawan_id' => $this->karyawan->id]);
    $karyawanLain = Karyawan::factory()->create();
    Izin::factory()->create(['karyawan_id' => $karyawanLain->id]);

    $response = $this->getJson('/api/izin');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data.records');
});

test('riwayat bisa difilter berdasarkan status', function () {
    Izin::factory()->create(['karyawan_id' => $this->karyawan->id, 'status' => 'pending']);
    Izin::factory()->create(['karyawan_id' => $this->karyawan->id, 'status' => 'approved']);

    $response = $this->getJson('/api/izin?status=approved');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data.records');
});

test('bisa membatalkan izin yang masih pending', function () {
    $izin = Izin::factory()->create(['karyawan_id' => $this->karyawan->id, 'status' => 'pending']);

    $response = $this->deleteJson("/api/izin/{$izin->id}");

    $response->assertStatus(200)->assertJson(['success' => true]);
    expect(Izin::find($izin->id))->toBeNull();
});

test('tidak bisa membatalkan izin yang sudah approved', function () {
    $izin = Izin::factory()->create(['karyawan_id' => $this->karyawan->id, 'status' => 'approved']);

    $response = $this->deleteJson("/api/izin/{$izin->id}");

    $response->assertStatus(422);
    expect(Izin::find($izin->id))->not->toBeNull();
});

test('404 kalau membatalkan izin milik karyawan lain', function () {
    $karyawanLain = Karyawan::factory()->create();
    $izin = Izin::factory()->create(['karyawan_id' => $karyawanLain->id, 'status' => 'pending']);

    $response = $this->deleteJson("/api/izin/{$izin->id}");

    $response->assertStatus(404);
});
