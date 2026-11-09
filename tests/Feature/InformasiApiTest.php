<?php

use App\Models\Informasi;
use App\Models\Instansi;
use App\Models\Karyawan;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->instansi = Instansi::factory()->create();
    $this->karyawan = Karyawan::factory()->for($this->instansi)->create();

    Sanctum::actingAs($this->karyawan, ['*']);
});

// ── index() ──────────────────────────────────────────────────────────────

it('index: menampilkan informasi milik instansi karyawan yang login', function () {
    Informasi::factory()->for($this->instansi)->count(3)->create();

    $this->getJson('/api/informasi')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.total', 3)
        ->assertJsonCount(3, 'data.records');
});

it('index: tidak menampilkan informasi milik instansi lain', function () {
    Informasi::factory()->for($this->instansi)->create();
    Informasi::factory()->for(Instansi::factory())->count(2)->create();

    $this->getJson('/api/informasi')
        ->assertOk()
        ->assertJsonPath('data.total', 1);
});

it('index: tidak menampilkan informasi yang dinonaktifkan admin', function () {
    Informasi::factory()->for($this->instansi)->create();
    Informasi::factory()->for($this->instansi)->nonaktif()->count(2)->create();

    $this->getJson('/api/informasi')
        ->assertOk()
        ->assertJsonPath('data.total', 1);
});

it('index: mengirim ringkasan terpotong, bukan isi penuh', function () {
    $informasi = Informasi::factory()->for($this->instansi)->create([
        'isi' => str_repeat('a', 500),
    ]);

    $response = $this->getJson('/api/informasi')->assertOk();

    $record = $response->json('data.records.0');

    expect($record)->not->toHaveKey('isi')
        ->and(strlen($record['ringkasan']))->toBeLessThan(200)
        ->and($record['ringkasan'])->toEndWith('...');
});

it('index: urut dari tanggal terbit terbaru', function () {
    Informasi::factory()->for($this->instansi)->create([
        'judul' => 'Lama', 'tanggal_terbit' => '2026-01-01',
    ]);
    Informasi::factory()->for($this->instansi)->create([
        'judul' => 'Baru', 'tanggal_terbit' => '2026-11-01',
    ]);

    $this->getJson('/api/informasi')
        ->assertOk()
        ->assertJsonPath('data.records.0.judul', 'Baru')
        ->assertJsonPath('data.records.1.judul', 'Lama');
});

// ── paging ───────────────────────────────────────────────────────────────

it('index: membatasi 10 per halaman dan melaporkan total halaman', function () {
    Informasi::factory()->for($this->instansi)->count(25)->create();

    $this->getJson('/api/informasi')
        ->assertOk()
        ->assertJsonCount(10, 'data.records')
        ->assertJsonPath('data.total', 25)
        ->assertJsonPath('data.halaman_sekarang', 1)
        ->assertJsonPath('data.total_halaman', 3);

    $this->getJson('/api/informasi?page=3')
        ->assertOk()
        ->assertJsonCount(5, 'data.records')
        ->assertJsonPath('data.halaman_sekarang', 3);
});

it('index: paging stabil walau banyak informasi bertanggal sama', function () {
    // tanggal_terbit bertipe date, jadi tanpa pengurut kedua (id) urutannya
    // tidak ditentukan — baris yang sama bisa muncul di dua halaman.
    Informasi::factory()->for($this->instansi)->count(20)->create([
        'tanggal_terbit' => '2026-11-01',
    ]);

    $halaman1 = collect($this->getJson('/api/informasi')->json('data.records'))->pluck('id');
    $halaman2 = collect($this->getJson('/api/informasi?page=2')->json('data.records'))->pluck('id');

    expect($halaman1->intersect($halaman2))->toBeEmpty()
        ->and($halaman1->merge($halaman2)->unique())->toHaveCount(20);
});

// ── pencarian ────────────────────────────────────────────────────────────

it('index: q mencari di judul', function () {
    Informasi::factory()->for($this->instansi)->create(['judul' => 'Pengajuan Cuti']);
    Informasi::factory()->for($this->instansi)->create(['judul' => 'Pemberlakuan Absensi']);

    $this->getJson('/api/informasi?q=cuti')
        ->assertOk()
        ->assertJsonPath('data.total', 1)
        ->assertJsonPath('data.records.0.judul', 'Pengajuan Cuti');
});

it('index: q TIDAK mencari di isi', function () {
    Informasi::factory()->for($this->instansi)->create([
        'judul' => 'Pemberlakuan Absensi',
        'isi' => 'Pengumuman ini menyebut kata rapat di dalam isinya.',
    ]);

    $this->getJson('/api/informasi?q=rapat')
        ->assertOk()
        ->assertJsonPath('data.total', 0);
});

it('index: q kosong atau spasi saja tidak menyaring apa pun', function () {
    Informasi::factory()->for($this->instansi)->count(3)->create();

    $this->getJson('/api/informasi?q=')->assertOk()->assertJsonPath('data.total', 3);
    $this->getJson('/api/informasi?q=%20%20')->assertOk()->assertJsonPath('data.total', 3);
});

it('index: pencarian tetap terkunci ke instansi karyawan', function () {
    Informasi::factory()->for($this->instansi)->create(['judul' => 'Rapat Internal']);
    Informasi::factory()->for(Instansi::factory())->create(['judul' => 'Rapat Pusat']);

    $this->getJson('/api/informasi?q=rapat')
        ->assertOk()
        ->assertJsonPath('data.total', 1)
        ->assertJsonPath('data.records.0.judul', 'Rapat Internal');
});

// ── show() ───────────────────────────────────────────────────────────────

it('show: mengirim isi penuh', function () {
    $informasi = Informasi::factory()->for($this->instansi)->create([
        'isi' => str_repeat('b', 500),
    ]);

    $this->getJson("/api/informasi/{$informasi->id}")
        ->assertOk()
        ->assertJsonPath('data.isi', str_repeat('b', 500));
});

it('show: 404 untuk informasi milik instansi lain', function () {
    $milikOrangLain = Informasi::factory()->for(Instansi::factory())->create();

    $this->getJson("/api/informasi/{$milikOrangLain->id}")->assertStatus(404);
});

it('show: 404 untuk informasi yang dinonaktifkan', function () {
    $nonaktif = Informasi::factory()->for($this->instansi)->nonaktif()->create();

    $this->getJson("/api/informasi/{$nonaktif->id}")->assertStatus(404);
});

// ── auth ─────────────────────────────────────────────────────────────────

it('menolak akses tanpa token', function () {
    // override actingAs dari beforeEach
    app('auth')->forgetGuards();

    $this->getJson('/api/informasi')->assertStatus(401);
});
