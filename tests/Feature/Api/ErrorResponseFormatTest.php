<?php

use App\Models\Karyawan;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->karyawan = Karyawan::factory()->create();
});

it('validation error mengembalikan format seragam {success, message, errors}', function () {
    Sanctum::actingAs($this->karyawan);

    $response = $this->postJson('/api/izin', [
        // sengaja kosongkan field wajib
    ]);

    $response->assertStatus(422)
        ->assertJsonStructure(['success', 'message', 'errors'])
        ->assertJsonPath('success', false);

    expect($response->json('errors'))->toHaveKey('tanggal');
});

it('request tanpa token mengembalikan format seragam dengan status 401', function () {
    $response = $this->postJson('/api/izin', [
        'tanggal'   => '2026-08-01',
        'jam_keluar' => '10:00',
        'keperluan' => 'Tes',
    ]);

    $response->assertStatus(401)
        ->assertJsonStructure(['success', 'message'])
        ->assertJsonPath('success', false);
});

it('endpoint api yang tidak terdaftar mengembalikan format seragam dengan status 404', function () {
    Sanctum::actingAs($this->karyawan);

    $response = $this->getJson('/api/endpoint-yang-tidak-ada');

    $response->assertStatus(404)
        ->assertJsonStructure(['success', 'message'])
        ->assertJsonPath('success', false);
});
