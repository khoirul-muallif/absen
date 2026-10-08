<?php

use App\Models\Izin;
use App\Models\Karyawan;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->karyawan = Karyawan::factory()->create();
    Sanctum::actingAs($this->karyawan);
});

test('bisa mengajukan izin baru, langsung approved tanpa approval admin', function () {
    $response = $this->postJson('/api/izin', [
        'tanggal'     => '2026-08-01',
        'jam_keluar'  => '10:00',
        'jam_kembali' => '12:00',
        'keperluan'   => 'Ke bank',
    ]);

    $response->assertStatus(201)
        ->assertJson(['success' => true, 'data' => ['status' => 'approved']]);

    $izin = Izin::where('karyawan_id', $this->karyawan->id)->first();

    expect($izin->status)->toBe('approved')
        ->and($izin->approved_by)->toBeNull() // bukan admin yang approve
        ->and($izin->approved_at)->not->toBeNull();
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
    // status 'pending' sekarang cuma realistis untuk entri manual admin lewat
    // Filament (bukan dari jalur API ini), tapi tetap valid data-nya di DB —
    // filter harus tetap jalan untuk kedua nilai.
    Izin::factory()->create(['karyawan_id' => $this->karyawan->id, 'status' => 'pending']);
    Izin::factory()->create(['karyawan_id' => $this->karyawan->id, 'status' => 'approved']);

    $response = $this->getJson('/api/izin?status=approved');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data.records');
});

test('bisa membatalkan izin yang jam_kembali belum terisi', function () {
    // Dasar pembatalan sekarang jam_kembali, BUKAN status — izin dari API
    // selalu lahir approved, jadi guard lama (isPending()) tidak akan pernah
    // bisa dipenuhi lagi kalau tidak diganti.
    $izin = Izin::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'status'      => 'approved',
        'jam_kembali' => null,
    ]);

    $response = $this->deleteJson("/api/izin/{$izin->id}");

    $response->assertStatus(200)->assertJson(['success' => true]);
    expect(Izin::find($izin->id))->toBeNull();
});

test('tidak bisa membatalkan izin yang jam_kembali sudah terisi', function () {
    $izin = Izin::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'status'      => 'approved',
        'jam_keluar'  => '10:00',
        'jam_kembali' => '12:00',
    ]);

    $response = $this->deleteJson("/api/izin/{$izin->id}");

    $response->assertStatus(422);
    expect(Izin::find($izin->id))->not->toBeNull();
});

test('404 kalau membatalkan izin milik karyawan lain', function () {
    $karyawanLain = Karyawan::factory()->create();
    $izin = Izin::factory()->create(['karyawan_id' => $karyawanLain->id, 'jam_kembali' => null]);

    $response = $this->deleteJson("/api/izin/{$izin->id}");

    $response->assertStatus(404);
});

// --- Endpoint baru: PATCH /api/izin/{id}/jam-kembali ---

test('bisa mengisi jam_kembali yang masih kosong', function () {
    $izin = Izin::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'jam_keluar'  => '10:00',
        'jam_kembali' => null,
    ]);

    $response = $this->patchJson("/api/izin/{$izin->id}/jam-kembali", [
        'jam_kembali' => '11:30',
    ]);

    $response->assertStatus(200)
        ->assertJson(['success' => true, 'data' => ['jam_kembali' => '11:30']]);

    expect($izin->fresh()->jam_kembali)->toBe('11:30:00');
});

test('menolak isi jam_kembali kalau sudah pernah terisi', function () {
    $izin = Izin::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'jam_keluar'  => '10:00',
        'jam_kembali' => '11:00',
    ]);

    $response = $this->patchJson("/api/izin/{$izin->id}/jam-kembali", [
        'jam_kembali' => '13:00',
    ]);

    $response->assertStatus(422);
    expect($izin->fresh()->jam_kembali)->toBe('11:00:00'); // tidak berubah
});

test('menolak jam_kembali baru yang lebih awal dari jam_keluar', function () {
    $izin = Izin::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'jam_keluar'  => '14:00',
        'jam_kembali' => null,
    ]);

    $response = $this->patchJson("/api/izin/{$izin->id}/jam-kembali", [
        'jam_kembali' => '13:00',
    ]);

    $response->assertStatus(422);
});

test('404 kalau mengisi jam_kembali izin milik karyawan lain', function () {
    $karyawanLain = Karyawan::factory()->create();
    $izin = Izin::factory()->create(['karyawan_id' => $karyawanLain->id, 'jam_kembali' => null]);

    $response = $this->patchJson("/api/izin/{$izin->id}/jam-kembali", [
        'jam_kembali' => '12:00',
    ]);

    $response->assertStatus(404);
});
