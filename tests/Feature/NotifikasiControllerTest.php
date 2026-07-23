<?php

use App\Models\Karyawan;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

function buatNotifikasi(Karyawan $karyawan, array $data = [], ?string $readAt = null): string
{
    $id = (string) Str::uuid();

    $karyawan->notifications()->create([
        'id' => $id,
        'type' => 'App\\Notifications\\AbsenTerlambat',
        'data' => array_merge([
            'judul' => 'Absen Terlambat',
            'pesan' => 'Anda terlambat 15 menit',
            'tipe' => 'warning',
        ], $data),
        'read_at' => $readAt,
    ]);

    return $id;
}

// ── index() ──────────────────────────────────────────────────────────────

it('index: menampilkan notifikasi milik karyawan beserta total belum baca', function () {
    $karyawan = Karyawan::factory()->create();
    buatNotifikasi($karyawan);
    buatNotifikasi($karyawan, ['pesan' => 'Sudah dibaca'], now()->toDateTimeString());

    Sanctum::actingAs($karyawan, ['*']);

    $this->getJson('/api/notifikasi')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.total_belum_baca', 1)
        ->assertJsonCount(2, 'data.notifikasi.data');
});

it('index: filter belum_baca=true hanya menampilkan yang belum dibaca', function () {
    $karyawan = Karyawan::factory()->create();
    buatNotifikasi($karyawan);
    buatNotifikasi($karyawan, [], now()->toDateTimeString());

    Sanctum::actingAs($karyawan, ['*']);

    $this->getJson('/api/notifikasi?belum_baca=true')
        ->assertOk()
        ->assertJsonCount(1, 'data.notifikasi.data');
});

it('index: tidak menampilkan notifikasi milik karyawan lain', function () {
    $karyawan = Karyawan::factory()->create();
    $karyawanLain = Karyawan::factory()->create();
    buatNotifikasi($karyawanLain);

    Sanctum::actingAs($karyawan, ['*']);

    $this->getJson('/api/notifikasi')
        ->assertOk()
        ->assertJsonPath('data.total_belum_baca', 0)
        ->assertJsonCount(0, 'data.notifikasi.data');
});

// ── baca() ───────────────────────────────────────────────────────────────

it('baca: menandai notifikasi sebagai sudah dibaca', function () {
    $karyawan = Karyawan::factory()->create();
    $id = buatNotifikasi($karyawan);

    Sanctum::actingAs($karyawan, ['*']);

    $this->postJson("/api/notifikasi/{$id}/baca")
        ->assertOk()
        ->assertJsonPath('success', true);

    expect($karyawan->notifications()->find($id)->read_at)->not->toBeNull();
});

it('baca: 404 kalau notifikasi bukan milik karyawan yang login', function () {
    $karyawan = Karyawan::factory()->create();
    $karyawanLain = Karyawan::factory()->create();
    $id = buatNotifikasi($karyawanLain);

    Sanctum::actingAs($karyawan, ['*']);

    $this->postJson("/api/notifikasi/{$id}/baca")
        ->assertStatus(404);
});

// ── bacaSemua() ──────────────────────────────────────────────────────────

it('bacaSemua: menandai semua notifikasi belum dibaca jadi dibaca', function () {
    $karyawan = Karyawan::factory()->create();
    buatNotifikasi($karyawan);
    buatNotifikasi($karyawan);

    Sanctum::actingAs($karyawan, ['*']);

    $this->postJson('/api/notifikasi/baca-semua')
        ->assertOk()
        ->assertJsonPath('success', true);

    expect($karyawan->unreadNotifications()->count())->toBe(0);
});

// ── hapus() ──────────────────────────────────────────────────────────────

it('hapus: menghapus notifikasi milik sendiri', function () {
    $karyawan = Karyawan::factory()->create();
    $id = buatNotifikasi($karyawan);

    Sanctum::actingAs($karyawan, ['*']);

    $this->deleteJson("/api/notifikasi/{$id}")
        ->assertOk()
        ->assertJsonPath('success', true);

    expect($karyawan->notifications()->find($id))->toBeNull();
});

it('hapus: 404 kalau notifikasi bukan milik karyawan yang login', function () {
    $karyawan = Karyawan::factory()->create();
    $karyawanLain = Karyawan::factory()->create();
    $id = buatNotifikasi($karyawanLain);

    Sanctum::actingAs($karyawan, ['*']);

    $this->deleteJson("/api/notifikasi/{$id}")
        ->assertStatus(404);
});

// ── jumlah() ─────────────────────────────────────────────────────────────

it('jumlah: mengembalikan jumlah notifikasi belum dibaca', function () {
    $karyawan = Karyawan::factory()->create();
    buatNotifikasi($karyawan);
    buatNotifikasi($karyawan);
    buatNotifikasi($karyawan, [], now()->toDateTimeString());

    Sanctum::actingAs($karyawan, ['*']);

    $this->getJson('/api/notifikasi/jumlah')
        ->assertOk()
        ->assertJsonPath('data.belum_baca', 2);
});
