<?php

use App\Models\Karyawan;
use App\Models\Lembur;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->karyawan = Karyawan::factory()->create();
    Sanctum::actingAs($this->karyawan);
});

test('bisa mengajukan lembur baru', function () {
    $response = $this->postJson('/api/lembur', [
        'tanggal'     => '2026-08-01',
        'jam_mulai'   => '17:00',
        'jam_selesai' => '20:00',
        'alasan'      => 'Menyelesaikan laporan bulanan',
    ]);

    $response->assertStatus(201)
        ->assertJson(['success' => true]);

    expect(Lembur::where('karyawan_id', $this->karyawan->id)->where('status', 'pending')->exists())->toBeTrue();
});

test('menolak pengajuan tanpa alasan', function () {
    $response = $this->postJson('/api/lembur', [
        'tanggal'     => '2026-08-01',
        'jam_mulai'   => '17:00',
        'jam_selesai' => '20:00',
    ]);

    $response->assertStatus(422);
});

test('bisa mengajukan lembur lintas tengah malam', function () {
    $response = $this->postJson('/api/lembur', [
        'tanggal'     => '2026-08-01',
        'jam_mulai'   => '22:00',
        'jam_selesai' => '02:00',
        'alasan'      => 'Jaga malam proyek urgent',
    ]);

    $response->assertStatus(201)
        ->assertJson(['success' => true]);

    expect(Lembur::where('karyawan_id', $this->karyawan->id)->where('status', 'pending')->exists())->toBeTrue();
});

test('menolak durasi nol (jam_selesai sama dengan jam_mulai)', function () {
    $response = $this->postJson('/api/lembur', [
        'tanggal'     => '2026-08-01',
        'jam_mulai'   => '17:00',
        'jam_selesai' => '17:00',
        'alasan'      => 'Tes',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('jam_selesai');
});

test('riwayat hanya menampilkan lembur milik karyawan yang login', function () {
    Lembur::factory()->create(['karyawan_id' => $this->karyawan->id]);
    $karyawanLain = Karyawan::factory()->create();
    Lembur::factory()->create(['karyawan_id' => $karyawanLain->id]);

    $response = $this->getJson('/api/lembur');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data.records');
});

test('riwayat bisa difilter berdasarkan status', function () {
    Lembur::factory()->create(['karyawan_id' => $this->karyawan->id, 'status' => 'pending']);
    Lembur::factory()->create(['karyawan_id' => $this->karyawan->id, 'status' => 'approved']);

    $response = $this->getJson('/api/lembur?status=approved');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data.records');
});

test('riwayat: jam_mulai dan jam_selesai format H:i, bukan H:i:s mentah dari DB', function () {
    Lembur::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'jam_mulai'   => '17:00',
        'jam_selesai' => '20:30',
    ]);

    $response = $this->getJson('/api/lembur');

    $response->assertStatus(200);
    expect($response->json('data.records.0.jam_mulai'))->toBe('17:00');
    expect($response->json('data.records.0.jam_selesai'))->toBe('20:30');
});

test('bisa membatalkan lembur yang masih pending', function () {
    $lembur = Lembur::factory()->create(['karyawan_id' => $this->karyawan->id, 'status' => 'pending']);

    $response = $this->deleteJson("/api/lembur/{$lembur->id}");

    $response->assertStatus(200)->assertJson(['success' => true]);
    expect(Lembur::find($lembur->id))->toBeNull();
});

test('tidak bisa membatalkan lembur yang sudah approved', function () {
    $lembur = Lembur::factory()->create(['karyawan_id' => $this->karyawan->id, 'status' => 'approved']);

    $response = $this->deleteJson("/api/lembur/{$lembur->id}");

    $response->assertStatus(422);
    expect(Lembur::find($lembur->id))->not->toBeNull();
});

test('404 kalau membatalkan lembur milik karyawan lain', function () {
    $karyawanLain = Karyawan::factory()->create();
    $lembur = Lembur::factory()->create(['karyawan_id' => $karyawanLain->id, 'status' => 'pending']);

    $response = $this->deleteJson("/api/lembur/{$lembur->id}");

    $response->assertStatus(404);
});
