<?php

use App\Models\Absensi;
use App\Models\Instansi;
use App\Models\Jadwal;
use App\Models\Karyawan;
use App\Models\KaryawanShift;
use App\Models\QrInstansi;
use App\Models\Shift;
use App\Notifications\AbsenTerlambat;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

afterEach(function () {
    Carbon::setTestNow(); // pastikan freeze time nggak bocor ke test lain
});

function loginSebagai(Karyawan $karyawan): void
{
    Sanctum::actingAs($karyawan, ['*']);
}

function buatInstansiDenganTitik(): Instansi
{
    return Instansi::factory()->create([
        'latitude' => -6.9800000,
        'longitude' => 110.4000000,
        'radius_meter' => 100,
    ]);
}

// ── status() ─────────────────────────────────────────────────────────────

it('status: belum ada absensi hari ini', function () {
    $karyawan = Karyawan::factory()->create();
    loginSebagai($karyawan);

    $this->getJson('/api/absensi/status')
        ->assertOk()
        ->assertJsonPath('data.sudah_masuk', false)
        ->assertJsonPath('data.sudah_pulang', false)
        ->assertJsonPath('data.absensi', null);
});

// ── masuk() — jalur sukses, karyawan tipe umum ──────────────────────────

it('masuk: berhasil untuk karyawan umum dalam radius, QR valid, tepat waktu', function () {
    Storage::fake('public');
    Notification::fake();
    Carbon::setTestNow(Carbon::parse('2026-07-23 07:15:00'));

    $instansi = buatInstansiDenganTitik();
    $shift = Shift::factory()->create(['instansi_id' => $instansi->id, 'jam_masuk' => '07:30:00']);
    $karyawan = Karyawan::factory()->umum()->create(['instansi_id' => $instansi->id]);
    KaryawanShift::factory()->create([
        'karyawan_id' => $karyawan->id,
        'shift_id' => $shift->id,
        'tanggal_berlaku' => today()->subDay(),
    ]);
    $qr = QrInstansi::factory()->create(['instansi_id' => $instansi->id]);

    loginSebagai($karyawan);

    $response = $this->postJson('/api/absensi/masuk', [
        'latitude' => $instansi->latitude,
        'longitude' => $instansi->longitude,
        'kode_qr' => $qr->kode_qr,
        'foto_masuk' => UploadedFile::fake()->image('masuk.jpg'),
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'tepat_waktu')
        ->assertJsonPath('data.terlambat', null);

    $this->assertDatabaseHas('absensi', [
        'karyawan_id' => $karyawan->id,
        'shift_id' => $shift->id,
        'status' => 'tepat_waktu',
        'menit_terlambat' => 0,
    ]);

    Notification::assertNothingSent();
});

it('masuk: terlambat mengirim notifikasi AbsenTerlambat', function () {
    Storage::fake('public');
    Notification::fake();
    Carbon::setTestNow(Carbon::parse('2026-07-23 08:00:00'));

    $instansi = buatInstansiDenganTitik();
    $shift = Shift::factory()->create(['instansi_id' => $instansi->id, 'jam_masuk' => '07:30:00']);
    $karyawan = Karyawan::factory()->umum()->create(['instansi_id' => $instansi->id]);
    KaryawanShift::factory()->create([
        'karyawan_id' => $karyawan->id,
        'shift_id' => $shift->id,
        'tanggal_berlaku' => today()->subDay(),
    ]);
    $qr = QrInstansi::factory()->create(['instansi_id' => $instansi->id]);

    loginSebagai($karyawan);

    $this->postJson('/api/absensi/masuk', [
        'latitude' => $instansi->latitude,
        'longitude' => $instansi->longitude,
        'kode_qr' => $qr->kode_qr,
        'foto_masuk' => UploadedFile::fake()->image('masuk.jpg'),
    ])->assertOk()
        ->assertJsonPath('data.status', 'terlambat')
        ->assertJsonPath('data.terlambat', '30 menit');

    Notification::assertSentTo($karyawan, AbsenTerlambat::class);
});

// ── masuk() — validasi GPS & QR ─────────────────────────────────────────

it('masuk: ditolak kalau di luar radius instansi', function () {
    Storage::fake('public');

    $instansi = buatInstansiDenganTitik();
    $shift = Shift::factory()->create(['instansi_id' => $instansi->id]);
    $karyawan = Karyawan::factory()->umum()->create(['instansi_id' => $instansi->id]);
    KaryawanShift::factory()->create([
        'karyawan_id' => $karyawan->id,
        'shift_id' => $shift->id,
        'tanggal_berlaku' => today()->subDay(),
    ]);
    $qr = QrInstansi::factory()->create(['instansi_id' => $instansi->id]);

    loginSebagai($karyawan);

    $this->postJson('/api/absensi/masuk', [
        'latitude' => $instansi->latitude + 0.01, // ~1.1km, jauh di luar radius 100m
        'longitude' => $instansi->longitude,
        'kode_qr' => $qr->kode_qr,
        'foto_masuk' => UploadedFile::fake()->image('masuk.jpg'),
    ])->assertStatus(422)
        ->assertJsonStructure(['data' => ['jarak_meter']]);

    $this->assertDatabaseCount('absensi', 0);
});

it('masuk: ditolak kalau kode QR salah', function () {
    Storage::fake('public');

    $instansi = buatInstansiDenganTitik();
    $shift = Shift::factory()->create(['instansi_id' => $instansi->id]);
    $karyawan = Karyawan::factory()->umum()->create(['instansi_id' => $instansi->id]);
    KaryawanShift::factory()->create([
        'karyawan_id' => $karyawan->id,
        'shift_id' => $shift->id,
        'tanggal_berlaku' => today()->subDay(),
    ]);

    loginSebagai($karyawan);

    $this->postJson('/api/absensi/masuk', [
        'latitude' => $instansi->latitude,
        'longitude' => $instansi->longitude,
        'kode_qr' => 'KODE-NGACO',
        'foto_masuk' => UploadedFile::fake()->image('masuk.jpg'),
    ])->assertStatus(422)
        ->assertJsonPath('message', 'QR code tidak valid atau sudah kadaluarsa.');
});

it('masuk: ditolak kalau QR sudah expired', function () {
    Storage::fake('public');

    $instansi = buatInstansiDenganTitik();
    $shift = Shift::factory()->create(['instansi_id' => $instansi->id]);
    $karyawan = Karyawan::factory()->umum()->create(['instansi_id' => $instansi->id]);
    KaryawanShift::factory()->create([
        'karyawan_id' => $karyawan->id,
        'shift_id' => $shift->id,
        'tanggal_berlaku' => today()->subDay(),
    ]);
    $qr = QrInstansi::factory()->expired()->create(['instansi_id' => $instansi->id]);

    loginSebagai($karyawan);

    $this->postJson('/api/absensi/masuk', [
        'latitude' => $instansi->latitude,
        'longitude' => $instansi->longitude,
        'kode_qr' => $qr->kode_qr,
        'foto_masuk' => UploadedFile::fake()->image('masuk.jpg'),
    ])->assertStatus(422);
});

it('masuk: ditolak kalau sudah absen masuk hari ini', function () {
    Storage::fake('public');

    $instansi = buatInstansiDenganTitik();
    $shift = Shift::factory()->create(['instansi_id' => $instansi->id]);
    $karyawan = Karyawan::factory()->umum()->create(['instansi_id' => $instansi->id]);
    KaryawanShift::factory()->create([
        'karyawan_id' => $karyawan->id,
        'shift_id' => $shift->id,
        'tanggal_berlaku' => today()->subDay(),
    ]);
    $qr = QrInstansi::factory()->create(['instansi_id' => $instansi->id]);

    $karyawan->absensi()->create([
        'shift_id' => $shift->id,
        'tanggal' => today(),
        'waktu_masuk' => now(),
    ]);

    loginSebagai($karyawan);

    $this->postJson('/api/absensi/masuk', [
        'latitude' => $instansi->latitude,
        'longitude' => $instansi->longitude,
        'kode_qr' => $qr->kode_qr,
        'foto_masuk' => UploadedFile::fake()->image('masuk.jpg'),
    ])->assertStatus(422)
        ->assertJsonPath('message', 'Anda sudah melakukan absen masuk hari ini.');
});

// ── masuk() — fallback shift untuk karyawan rotasi ──────────────────────

it('masuk: karyawan rotasi pakai shift dari Jadwal hari ini (bukan KaryawanShift)', function () {
    Storage::fake('public');
    Carbon::setTestNow(Carbon::parse('2026-07-23 07:00:00'));

    $instansi = buatInstansiDenganTitik();
    $shift = Shift::factory()->create(['instansi_id' => $instansi->id, 'jam_masuk' => '07:00:00']);
    $karyawan = Karyawan::factory()->rotasi()->create(['instansi_id' => $instansi->id]);
    Jadwal::factory()->create([
        'karyawan_id' => $karyawan->id,
        'shift_id' => $shift->id,
        'tanggal' => today(),
        'jenis' => 'piket',
    ]);
    $qr = QrInstansi::factory()->create(['instansi_id' => $instansi->id]);

    loginSebagai($karyawan);

    $this->postJson('/api/absensi/masuk', [
        'latitude' => $instansi->latitude,
        'longitude' => $instansi->longitude,
        'kode_qr' => $qr->kode_qr,
        'foto_masuk' => UploadedFile::fake()->image('masuk.jpg'),
    ])->assertOk();

    $this->assertDatabaseHas('absensi', [
        'karyawan_id' => $karyawan->id,
        'shift_id' => $shift->id,
    ]);
});

it('masuk: karyawan rotasi tanpa Jadwal hari ini ditolak dengan pesan jelas', function () {
    Storage::fake('public');

    $instansi = buatInstansiDenganTitik();
    $karyawan = Karyawan::factory()->rotasi()->create(['instansi_id' => $instansi->id]);
    $qr = QrInstansi::factory()->create(['instansi_id' => $instansi->id]);

    loginSebagai($karyawan);

    $this->postJson('/api/absensi/masuk', [
        'latitude' => $instansi->latitude,
        'longitude' => $instansi->longitude,
        'kode_qr' => $qr->kode_qr,
        'foto_masuk' => UploadedFile::fake()->image('masuk.jpg'),
    ])->assertStatus(422)
        ->assertJsonPath('message', 'Tidak ada shift aktif untuk hari ini. Hubungi admin.');
});

// ── pulang() ─────────────────────────────────────────────────────────────

it('pulang: berhasil setelah absen masuk', function () {
    Storage::fake('public');

    $instansi = buatInstansiDenganTitik();
    $shift = Shift::factory()->create(['instansi_id' => $instansi->id]);
    $karyawan = Karyawan::factory()->umum()->create(['instansi_id' => $instansi->id]);

    $karyawan->absensi()->create([
        'shift_id' => $shift->id,
        'tanggal' => today(),
        'waktu_masuk' => today()->setTime(7, 30),
        'status' => 'tepat_waktu',
    ]);

    loginSebagai($karyawan);

    $this->postJson('/api/absensi/pulang', [
        'latitude' => $instansi->latitude,
        'longitude' => $instansi->longitude,
        'foto_pulang' => UploadedFile::fake()->image('pulang.jpg'),
    ])->assertOk()
        ->assertJsonPath('success', true);

    $this->assertDatabaseHas('absensi', [
        'karyawan_id' => $karyawan->id,
        'shift_id' => $shift->id,
    ]);
});

it('pulang: ditolak kalau belum absen masuk', function () {
    Storage::fake('public');
    $instansi = buatInstansiDenganTitik();
    $karyawan = Karyawan::factory()->umum()->create(['instansi_id' => $instansi->id]);

    loginSebagai($karyawan);

    $this->postJson('/api/absensi/pulang', [
        'latitude' => $instansi->latitude,
        'longitude' => $instansi->longitude,
        'foto_pulang' => UploadedFile::fake()->image('pulang.jpg'),
    ])->assertStatus(422)
        ->assertJsonPath('message', 'Anda belum melakukan absen masuk hari ini.');
});

it('pulang: ditolak kalau sudah absen pulang', function () {
    Storage::fake('public');
    $instansi = buatInstansiDenganTitik();
    $shift = Shift::factory()->create(['instansi_id' => $instansi->id]);
    $karyawan = Karyawan::factory()->umum()->create(['instansi_id' => $instansi->id]);

    $karyawan->absensi()->create([
        'shift_id' => $shift->id,
        'tanggal' => today(),
        'waktu_masuk' => today()->setTime(7, 30),
        'waktu_pulang' => today()->setTime(16, 0),
        'status' => 'tepat_waktu',
    ]);

    loginSebagai($karyawan);

    $this->postJson('/api/absensi/pulang', [
        'latitude' => $instansi->latitude,
        'longitude' => $instansi->longitude,
        'foto_pulang' => UploadedFile::fake()->image('pulang.jpg'),
    ])->assertStatus(422)
        ->assertJsonPath('message', 'Anda sudah melakukan absen pulang hari ini.');
});

it('absen masuk ditolak kalau hari ini sudah berstatus dinas', function () {
    Storage::fake('public');

    $instansi = buatInstansiDenganTitik();
    $karyawan = Karyawan::factory()->umum()->create(['instansi_id' => $instansi->id]);
    $qr = QrInstansi::factory()->create(['instansi_id' => $instansi->id]);

    Absensi::factory()->create([
        'karyawan_id' => $karyawan->id,
        'tanggal' => today(),
        'status' => 'dinas',
        'waktu_masuk' => null,
    ]);

    loginSebagai($karyawan);

    $this->postJson('/api/absensi/masuk', [
        'latitude' => $instansi->latitude,
        'longitude' => $instansi->longitude,
        'kode_qr' => $qr->kode_qr,
        'foto_masuk' => UploadedFile::fake()->image('masuk.jpg'),
    ])->assertStatus(422)
        ->assertJsonPath('message', 'Anda tercatat dinas hari ini.');
});

it('absen masuk ditolak kalau hari ini sudah berstatus cuti', function () {
    Storage::fake('public');

    $instansi = buatInstansiDenganTitik();
    $karyawan = Karyawan::factory()->umum()->create(['instansi_id' => $instansi->id]);
    $qr = QrInstansi::factory()->create(['instansi_id' => $instansi->id]);

    Absensi::factory()->create([
        'karyawan_id' => $karyawan->id,
        'tanggal' => today(),
        'status' => 'cuti',
        'waktu_masuk' => null,
    ]);

    loginSebagai($karyawan);

    $this->postJson('/api/absensi/masuk', [
        'latitude' => $instansi->latitude,
        'longitude' => $instansi->longitude,
        'kode_qr' => $qr->kode_qr,
        'foto_masuk' => UploadedFile::fake()->image('masuk.jpg'),
    ])->assertStatus(422)
        ->assertJsonPath('message', 'Anda tercatat cuti hari ini.');
});

it('masuk: race condition — insert kedua ditolak 422 bukan 500, foto orphan terhapus', function () {
    Storage::fake('public');

    $instansi = buatInstansiDenganTitik();
    $shift = Shift::factory()->create(['instansi_id' => $instansi->id]);
    $karyawan = Karyawan::factory()->umum()->create(['instansi_id' => $instansi->id]);
    KaryawanShift::factory()->create([
        'karyawan_id' => $karyawan->id,
        'shift_id' => $shift->id,
        'tanggal_berlaku' => today()->subDay(),
    ]);
    $qr = QrInstansi::factory()->create(['instansi_id' => $instansi->id]);

    loginSebagai($karyawan);

    // Simulasikan race condition: sisipkan row Absensi "dari request lain"
    // tepat sebelum insert asli terjadi — melewati pengecekan awal di controller
    // (yang tadi masih melihat belum ada row sama sekali), sehingga insert asli
    // kena unique constraint DB, bukan pengecekan aplikasi.
    Absensi::creating(function () use ($karyawan, $shift) {
        if (! Absensi::where('karyawan_id', $karyawan->id)->whereDate('tanggal', today())->exists()) {
            \Illuminate\Support\Facades\DB::table('absensi')->insert([
                'karyawan_id' => $karyawan->id,
                'shift_id' => $shift->id,
                'tanggal' => today(),
                'waktu_masuk' => now(),
                'status' => 'tepat_waktu',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    });

    $response = $this->postJson('/api/absensi/masuk', [
        'latitude' => $instansi->latitude,
        'longitude' => $instansi->longitude,
        'kode_qr' => $qr->kode_qr,
        'foto_masuk' => UploadedFile::fake()->image('masuk.jpg'),
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('message', 'Anda sudah melakukan absen masuk hari ini.');

    expect(Absensi::where('karyawan_id', $karyawan->id)->whereDate('tanggal', today())->count())->toBe(1);

    // Pastikan tidak ada foto tersisa di storage (foto orphan sudah dihapus)
    Storage::disk('public')->assertDirectoryEmpty('foto-absen/masuk');
});
