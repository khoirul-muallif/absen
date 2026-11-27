<?php

use App\Models\Instansi;
use App\Models\Karyawan;
use App\Models\KaryawanShift;
use App\Models\Shift;
use Illuminate\Support\Facades\Hash;

// ── Login ──────────────────────────────────────────────────────────────

it('bisa login dengan username dan password yang benar', function () {
    $instansi = Instansi::factory()->create();
    $karyawan = Karyawan::factory()->create([
        'instansi_id' => $instansi->id,
        'username' => 'budi',
        'password' => Hash::make('rahasia123'),
        'is_active' => true,
    ]);

    $response = $this->postJson('/api/auth/login', [
        'username' => 'budi',
        'password' => 'rahasia123',
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.karyawan.id', $karyawan->id)
        ->assertJsonPath('data.karyawan.tipe_jadwal', $karyawan->tipe_jadwal)
        ->assertJsonPath('data.karyawan.instansi.id', $instansi->id)
        ->assertJsonStructure(['data' => ['token']]);
});

it('menolak login dengan password salah', function () {
    Karyawan::factory()->create([
        'username' => 'budi',
        'password' => Hash::make('rahasia123'),
        'is_active' => true,
    ]);

    $response = $this->postJson('/api/auth/login', [
        'username' => 'budi',
        'password' => 'salah',
    ]);

    $response->assertUnprocessable()
        ->assertJsonPath('errors.username.0', 'Username atau password salah.');
});

it('menolak login karyawan yang tidak aktif', function () {
    Karyawan::factory()->create([
        'username' => 'nonaktif',
        'password' => Hash::make('rahasia123'),
        'is_active' => false,
    ]);

    $response = $this->postJson('/api/auth/login', [
        'username' => 'nonaktif',
        'password' => 'rahasia123',
    ]);

    $response->assertUnprocessable()
        ->assertJsonPath('errors.username.0', 'Username atau password salah.');
});

it('login menghapus token lama sebelum membuat token baru', function () {
    $karyawan = Karyawan::factory()->create([
        'username' => 'budi',
        'password' => Hash::make('rahasia123'),
        'is_active' => true,
    ]);
    $karyawan->createToken('token-lama');

    expect($karyawan->tokens()->count())->toBe(1);

    $this->postJson('/api/auth/login', [
        'username' => 'budi',
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

    $shift = Shift::factory()->create([
        'instansi_id'  => $instansi->id,
        'jam_masuk'    => '08:00:00',
        'jam_pulang'   => '15:00:00',
    ]);

    KaryawanShift::factory()->create([
        'karyawan_id'      => $karyawan->id,
        'shift_id'         => $shift->id,
        'tanggal_berlaku'  => today()->subDay(),
        'tanggal_berakhir' => null,
    ]);

    $token = $karyawan->createToken('absensi-app')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/auth/me');

    $response->assertStatus(200)
        ->assertJsonPath('data.tipe_jadwal', $karyawan->tipe_jadwal)
        ->assertJsonPath('data.shift_aktif.jam_masuk', '08:00')
        ->assertJsonPath('data.shift_aktif.jam_pulang', '15:00');
});

it('menolak akses /me tanpa token', function () {
    $this->getJson('/api/auth/me')->assertUnauthorized();
});


// ── Ubah Password ──────────────────────────────────────────────────────

it('bisa mengganti password sendiri', function () {
    $karyawan = Karyawan::factory()->create([
        'password' => Hash::make('rahasia123'),
    ]);
    $token = $karyawan->createToken('absensi-app')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/auth/ubah-password', [
            'password_lama'         => 'rahasia123',
            'password'              => 'rahasiaBaru456',
            'password_confirmation' => 'rahasiaBaru456',
        ])
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(Hash::check('rahasiaBaru456', $karyawan->fresh()->password))->toBeTrue();
});

it('password baru benar-benar bisa dipakai login, bukan hash ganda', function () {
    $karyawan = Karyawan::factory()->create([
        'username'  => 'budi',
        'password'  => Hash::make('rahasia123'),
        'is_active' => true,
    ]);
    $token = $karyawan->createToken('absensi-app')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/auth/ubah-password', [
            'password_lama'         => 'rahasia123',
            'password'              => 'rahasiaBaru456',
            'password_confirmation' => 'rahasiaBaru456',
        ])->assertOk();

    // Inti test ini: hash ganda lolos Hash::check() di atas kalau yang
    // dicek nilai yang salah, tapi TIDAK pernah lolos login sungguhan.
    $this->postJson('/api/auth/login', [
        'username' => 'budi',
        'password' => 'rahasiaBaru456',
    ])->assertOk();
});

it('menolak ubah password kalau password lama salah', function () {
    $karyawan = Karyawan::factory()->create([
        'password' => Hash::make('rahasia123'),
    ]);
    $token = $karyawan->createToken('absensi-app')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/auth/ubah-password', [
            'password_lama'         => 'bukan-ini',
            'password'              => 'rahasiaBaru456',
            'password_confirmation' => 'rahasiaBaru456',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('password_lama');

    expect(Hash::check('rahasia123', $karyawan->fresh()->password))->toBeTrue();
});

it('menolak kalau konfirmasi password tidak cocok', function () {
    $karyawan = Karyawan::factory()->create([
        'password' => Hash::make('rahasia123'),
    ]);
    $token = $karyawan->createToken('absensi-app')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/auth/ubah-password', [
            'password_lama'         => 'rahasia123',
            'password'              => 'rahasiaBaru456',
            'password_confirmation' => 'beda-sendiri',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password' => 'Konfirmasi password tidak cocok.']);
});

it('menolak password baru kurang dari 8 karakter', function () {
    $karyawan = Karyawan::factory()->create([
        'password' => Hash::make('rahasia123'),
    ]);
    $token = $karyawan->createToken('absensi-app')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/auth/ubah-password', [
            'password_lama'         => 'rahasia123',
            'password'              => 'pendek',
            'password_confirmation' => 'pendek',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password' => 'Password baru minimal 8 karakter.']);
});

it('menolak password baru yang sama dengan password lama', function () {
    $karyawan = Karyawan::factory()->create([
        'password' => Hash::make('rahasia123'),
    ]);
    $token = $karyawan->createToken('absensi-app')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/auth/ubah-password', [
            'password_lama'         => 'rahasia123',
            'password'              => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password' => 'Password baru harus berbeda dari password lama.']);
});

it('menolak ubah password tanpa token', function () {
    $this->postJson('/api/auth/ubah-password', [
        'password_lama'         => 'rahasia123',
        'password'              => 'rahasiaBaru456',
        'password_confirmation' => 'rahasiaBaru456',
    ])->assertUnauthorized();
});

it('mengirim URL logo instansi kalau ada', function () {
    $instansi = Instansi::factory()->create(['logo' => 'logo-instansi/rs.png']);
    $karyawan = Karyawan::factory()->create(['instansi_id' => $instansi->id]);
    $token = $karyawan->createToken('absensi-app')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/auth/me')
        ->assertOk()
        // URL penuh, bukan path mentah — FE memasangnya langsung ke
        // NetworkImage tanpa merakit apa pun.
        ->assertJsonPath('data.instansi.logo', asset('storage/logo-instansi/rs.png'));
});

it('mengirim logo null kalau instansi belum punya logo', function () {
    $instansi = Instansi::factory()->create(['logo' => null]);
    $karyawan = Karyawan::factory()->create(['instansi_id' => $instansi->id]);
    $token = $karyawan->createToken('absensi-app')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/auth/me')
        ->assertOk()
        ->assertJsonPath('data.instansi.logo', null);
});

it('mematikan harus_ganti_password setelah karyawan menggantinya', function () {
    $karyawan = Karyawan::factory()->create([
        'password' => Hash::make('rahasia123'),
        'harus_ganti_password' => true,
    ]);
    $token = $karyawan->createToken('absensi-app')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/auth/ubah-password', [
            'password_lama'         => 'rahasia123',
            'password'              => 'rahasiaBaru456',
            'password_confirmation' => 'rahasiaBaru456',
        ])->assertOk();

    expect($karyawan->fresh()->harus_ganti_password)->toBeFalse();
});

it('login dan me mengirim harus_ganti_password', function () {
    $karyawan = Karyawan::factory()->create([
        'username'  => 'budi',
        'password'  => Hash::make('rahasia123'),
        'is_active' => true,
        'harus_ganti_password' => true,
    ]);

    $this->postJson('/api/auth/login', [
        'username' => 'budi',
        'password' => 'rahasia123',
    ])->assertOk()->assertJsonPath('data.karyawan.harus_ganti_password', true);

    $token = $karyawan->createToken('absensi-app')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/auth/me')
        ->assertOk()
        ->assertJsonPath('data.harus_ganti_password', true);
});

it('password default admin tidak lebih lemah dari yang boleh dipilih karyawan', function () {
    // Dua hal yang tidak saling tahu: panjang minimum di
    // ubahPassword() dan nilai password default. Kalau defaultnya
    // diperpendek, karyawan yang baru di-reset punya password yang
    // lebih lemah daripada yang boleh dia pilih sendiri — dan tidak
    // ada yang menolak apa pun, jadi tidak ada yang tahu.
    expect(strlen(Karyawan::PASSWORD_DEFAULT))->toBeGreaterThanOrEqual(8);
});

it('password default benar-benar bisa dipakai login dan diganti', function () {
    // Membuktikan nilainya lolos aturan sungguhan, bukan cuma panjangnya.
    $karyawan = Karyawan::factory()->create([
        'username'  => 'budi',
        'password'  => Karyawan::PASSWORD_DEFAULT,
        'is_active' => true,
    ]);

    $this->postJson('/api/auth/login', [
        'username' => 'budi',
        'password' => Karyawan::PASSWORD_DEFAULT,
    ])->assertOk();

    $token = $karyawan->createToken('absensi-app')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/auth/ubah-password', [
            'password_lama'         => Karyawan::PASSWORD_DEFAULT,
            'password'              => 'rahasiaBaru456',
            'password_confirmation' => 'rahasiaBaru456',
        ])->assertOk();
});
