<?php

use App\Models\Instansi;
use App\Models\Karyawan;
use Illuminate\Support\Facades\Hash;

// ── Login ──────────────────────────────────────────────────────────────

it('bisa login dengan email dan password yang benar', function () {
    $instansi = Instansi::factory()->create();
    $karyawan = Karyawan::factory()->create([
        'instansi_id' => $instansi->id,
        'email' => 'budi@rsb.com',
        'password' => Hash::make('rahasia123'),
        'is_active' => true,
    ]);

    $response = $this->postJson('/api/auth/login', [
        'email' => 'budi@rsb.com',
        'password' => 'rahasia123',
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.karyawan.id', $karyawan->id)
        ->assertJsonPath('data.karyawan.instansi.id', $instansi->id)
        ->assertJsonStructure(['data' => ['token']]);
});

it('menolak login dengan password salah', function () {
    Karyawan::factory()->create([
        'email' => 'budi@rsb.com',
        'password' => Hash::make('rahasia123'),
        'is_active' => true,
    ]);

    $response = $this->postJson('/api/auth/login', [
        'email' => 'budi@rsb.com',
        'password' => 'salah',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

it('menolak login karyawan yang tidak aktif', function () {
    Karyawan::factory()->create([
        'email' => 'nonaktif@rsb.com',
        'password' => Hash::make('rahasia123'),
        'is_active' => false,
    ]);

    $response = $this->postJson('/api/auth/login', [
        'email' => 'nonaktif@rsb.com',
        'password' => 'rahasia123',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

it('login menghapus token lama sebelum membuat token baru', function () {
    $karyawan = Karyawan::factory()->create([
        'email' => 'budi@rsb.com',
        'password' => Hash::make('rahasia123'),
        'is_active' => true,
    ]);
    $karyawan->createToken('token-lama');

    expect($karyawan->tokens()->count())->toBe(1);

    $this->postJson('/api/auth/login', [
        'email' => 'budi@rsb.com',
        'password' => 'rahasia123',
    ])->assertOk();

    expect($karyawan->tokens()->count())->toBe(1); // lama dihapus, cuma token baru yang tersisa
});

// ── Logout ─────────────────────────────────────────────────────────────

it('bisa logout dan token yang dipakai jadi tidak valid lagi', function () {
    $karyawan = Karyawan::factory()->create();
    $token = $karyawan->createToken('absensi-app')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/auth/logout')
        ->assertOk()
        ->assertJsonPath('success', true);

    expect($karyawan->tokens()->count())->toBe(0);
});

it('menolak logout tanpa token', function () {
    $this->postJson('/api/auth/logout')->assertUnauthorized();
});

// ── Me ─────────────────────────────────────────────────────────────────

it('mengembalikan data karyawan yang sedang login lewat /me', function () {
    $instansi = Instansi::factory()->create();
    $karyawan = Karyawan::factory()->create(['instansi_id' => $instansi->id]);
    $token = $karyawan->createToken('absensi-app')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/auth/me')
        ->assertOk()
        ->assertJsonPath('data.id', $karyawan->id)
        ->assertJsonPath('data.instansi.id', $instansi->id)
        ->assertJsonPath('shift_aktif', null) // belum ada KaryawanShift
        ->assertJsonPath('data.absensi_hari_ini', null);
});

it('menolak akses /me tanpa token', function () {
    $this->getJson('/api/auth/me')->assertUnauthorized();
});
