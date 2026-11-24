<?php

use App\Models\Izin;
use App\Models\Karyawan;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->karyawan = Karyawan::factory()->create();
    Sanctum::actingAs($this->karyawan);
    $this->travelTo(Carbon::parse('2026-07-25 08:00:00'));
});

test('bisa mengajukan izin baru, langsung approved tanpa approval admin', function () {
    $response = $this->postJson('/api/izin', [
        'tanggal'             => '2026-08-01',
        'jam_keluar'          => '10:00',
        'jam_kembali_rencana' => '12:00',
        'keperluan'           => 'Ke bank',
    ]);

    $response->assertStatus(201)
        ->assertJson(['success' => true, 'data' => ['status' => 'approved']]);

    $izin = Izin::where('karyawan_id', $this->karyawan->id)->first();

    expect($izin->status)->toBe('approved')
        ->and($izin->approved_by)->toBeNull()
        ->and($izin->approved_at)->not->toBeNull()
        // Aktual TIDAK ikut terisi saat submit — itu inti pemisahan ini.
        ->and($izin->jam_kembali_aktual)->toBeNull();
});

test('menolak pengajuan tanpa rencana jam kembali', function () {
    $response = $this->postJson('/api/izin', [
        'tanggal'    => '2026-08-01',
        'jam_keluar' => '10:00',
        'keperluan'  => 'Ke bank',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors('jam_kembali_rencana');
});

test('menolak rencana kembali sebelum jam_keluar', function () {
    $response = $this->postJson('/api/izin', [
        'tanggal'             => '2026-08-01',
        'jam_keluar'          => '12:00',
        'jam_kembali_rencana' => '10:00',
        'keperluan'           => 'Tes',
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

test('riwayat: jam format H:i, bukan H:i:s mentah dari DB', function () {
    Izin::factory()->create([
        'karyawan_id'         => $this->karyawan->id,
        'jam_keluar'          => '10:00',
        'jam_kembali_rencana' => '12:30',
        'jam_kembali_aktual'  => '12:45',
    ]);

    $response = $this->getJson('/api/izin');

    $response->assertStatus(200);
    expect($response->json('data.records.0.jam_keluar'))->toBe('10:00');
    expect($response->json('data.records.0.jam_kembali_rencana'))->toBe('12:30');
    expect($response->json('data.records.0.jam_kembali_aktual'))->toBe('12:45');
});

test('riwayat: jam_kembali_aktual null tetap null (bukan string "00:00")', function () {
    Izin::factory()->create([
        'karyawan_id'        => $this->karyawan->id,
        'jam_kembali_aktual' => null,
    ]);

    $response = $this->getJson('/api/izin');

    expect($response->json('data.records.0.jam_kembali_aktual'))->toBeNull();
});

test('bisa membatalkan izin yang belum tercatat kembali', function () {
    // Dasar pembatalan sekarang jam_kembali_AKTUAL. Kalau guard ini
    // tertinggal di kolom rencana, tidak ada izin yang bisa dibatalkan
    // lagi — rencana selalu terisi sejak 24 Nov 2026.
    $izin = Izin::factory()->create([
        'karyawan_id'        => $this->karyawan->id,
        'status'             => 'approved',
        'jam_kembali_aktual' => null,
    ]);

    $this->deleteJson("/api/izin/{$izin->id}")
        ->assertStatus(200)->assertJson(['success' => true]);

    expect(Izin::find($izin->id))->toBeNull();
});

test('izin dengan rencana terisi tapi belum kembali TETAP bisa dibatalkan', function () {
    // Penjaga langsung untuk kekeliruan di atas.
    $izin = Izin::factory()->create([
        'karyawan_id'         => $this->karyawan->id,
        'jam_kembali_rencana' => '12:00',
        'jam_kembali_aktual'  => null,
    ]);

    $this->deleteJson("/api/izin/{$izin->id}")->assertStatus(200);
});

test('tidak bisa membatalkan izin yang sudah tercatat kembali', function () {
    $izin = Izin::factory()->create([
        'karyawan_id'        => $this->karyawan->id,
        'status'             => 'approved',
        'jam_kembali_aktual' => '12:00',
    ]);

    $this->deleteJson("/api/izin/{$izin->id}")->assertStatus(422);
    expect(Izin::find($izin->id))->not->toBeNull();
});

test('404 kalau membatalkan izin milik karyawan lain', function () {
    $karyawanLain = Karyawan::factory()->create();
    $izin = Izin::factory()->create([
        'karyawan_id'        => $karyawanLain->id,
        'jam_kembali_aktual' => null,
    ]);

    $this->deleteJson("/api/izin/{$izin->id}")->assertStatus(404);
});

// --- PATCH /api/izin/{id}/jam-kembali — tanpa body, waktu dari server ---

test('menandai sudah kembali memakai jam server, bukan jam dari client', function () {
    $izin = Izin::factory()->create([
        'karyawan_id'        => $this->karyawan->id,
        'jam_keluar'         => '07:00',
        'jam_kembali_aktual' => null,
    ]);

    // Dikirim dengan body yang mencoba mengarang jam. Harus diabaikan.
    $response = $this->patchJson("/api/izin/{$izin->id}/jam-kembali", [
        'jam_kembali_aktual' => '23:59',
    ]);

    // beforeEach membekukan waktu di 08:00.
    $response->assertStatus(200)
        ->assertJson(['data' => ['jam_kembali_aktual' => '08:00']]);

    expect($izin->fresh()->jam_kembali_aktual)->toBe('08:00:00');
});

test('menolak menandai kembali kalau sudah pernah tercatat', function () {
    $izin = Izin::factory()->create([
        'karyawan_id'        => $this->karyawan->id,
        'jam_kembali_aktual' => '11:00',
    ]);

    $this->patchJson("/api/izin/{$izin->id}/jam-kembali")->assertStatus(422);
    expect($izin->fresh()->jam_kembali_aktual)->toBe('11:00:00');
});

test('404 kalau menandai kembali izin milik karyawan lain', function () {
    $karyawanLain = Karyawan::factory()->create();
    $izin = Izin::factory()->create([
        'karyawan_id'        => $karyawanLain->id,
        'jam_kembali_aktual' => null,
    ]);

    $this->patchJson("/api/izin/{$izin->id}/jam-kembali")->assertStatus(404);
});
