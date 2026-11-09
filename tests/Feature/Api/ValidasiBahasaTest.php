<?php

use App\Models\Karyawan;
use App\Models\Lembur;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->karyawan = Karyawan::factory()->create();
    Sanctum::actingAs($this->karyawan);
    $this->travelTo(Carbon::parse('2026-07-25 08:00:00'));
});

it('pesan validasi API berbahasa Indonesia, bukan kunci mentah', function () {
    $karyawan = Karyawan::factory()->create();
    Sanctum::actingAs($karyawan, ['*']);

    // Kirim body kosong supaya required memicu.
    $response = $this->postJson('/api/cuti', [])->assertUnprocessable();

    $pesan = $response->json('errors.alasan.0');

    expect($pesan)->not->toStartWith('validation.')
        ->and($pesan)->toBe('Alasan wajib diisi.');
});

it('nama field di pesan validasi dibaca manusia, bukan nama kolom', function () {
    $karyawan = Karyawan::factory()->create();
    Sanctum::actingAs($karyawan, ['*']);

    $response = $this->postJson('/api/cuti', [])->assertUnprocessable();

    // Tanpa blok `attributes`, pesannya berbunyi "jenis_cuti_id wajib diisi".
    expect($response->json('errors.jenis_cuti_id.0'))->toBe('Jenis cuti wajib diisi.');
});
