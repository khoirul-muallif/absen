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

// ============================================================================
// TAMBAHAN untuk tests/Feature/Api/AbsensiControllerTest.php
//
// Tempel di akhir file. Setup di bawah sengaja ditulis lengkap (tidak
// bergantung pada beforeEach) — kalau file itu sudah punya helper/fixture
// sendiri untuk karyawan+instansi+qr, silakan disesuaikan biar tidak dobel.
// ============================================================================

// --- REGRESSION GUARD: melebihi_toleransi_bulanan benar-benar tersimpan ---
//
// Kolom ini dihitung di AbsensiController::masuk() sejak fase 9, tapi TIDAK
// ada di $fillable Absensi sampai fase 26. Akibatnya nilainya dibuang diam-diam
// oleh mass assignment dan kolomnya selalu false di seluruh data — tidak ada
// satu pun dari 15 test di file ini yang memeriksanya, jadi tidak pernah
// ketahuan. Pola yang sama dengan kolom `sumber` di Jadwal (fase 15).

it('masuk: menyimpan melebihi_toleransi_bulanan saat akumulasi bulan ini tembus toleransi', function () {
    \Illuminate\Support\Facades\Storage::fake('public');

    // Waktu dibekukan: status & menit_terlambat dihitung dari now().
    $this->travelTo(\Illuminate\Support\Carbon::parse('2026-08-10 08:20:00'));

    $instansi = \App\Models\Instansi::factory()->create([
        'latitude' => -7.0784947,
        'longitude' => 110.4119292,
        'radius_meter' => 100,
    ]);

    $karyawan = \App\Models\Karyawan::factory()->create([
        'instansi_id' => $instansi->id,
        'tipe_jadwal' => \App\Models\Karyawan::TIPE_UMUM,
    ]);

    $shift = \App\Models\Shift::factory()->create([
        'instansi_id' => $instansi->id,
        'jam_masuk' => '08:00:00',
        'toleransi_menit' => 30,
        'mode_toleransi' => 'akumulasi_bulanan',
    ]);

    \App\Models\KaryawanShift::factory()->create([
        'karyawan_id' => $karyawan->id,
        'shift_id' => $shift->id,
        'tanggal_berlaku' => '2026-08-01',
        'tanggal_berakhir' => null,
    ]);

    $qr = \App\Models\QrInstansi::factory()->create([
        'instansi_id' => $instansi->id,
        'is_active' => true,
        'expired_at' => null,
    ]);

    // Sudah telat 20 menit di bulan yang sama. Hari ini telat 20 menit lagi
    // -> total 40, melewati toleransi 30.
    \App\Models\Absensi::factory()->create([
        'karyawan_id' => $karyawan->id,
        'shift_id' => $shift->id,
        'tanggal' => '2026-08-05',
        'waktu_masuk' => '2026-08-05 08:20:00',
        'menit_terlambat' => 20,
        'status' => 'terlambat',
    ]);

    \Laravel\Sanctum\Sanctum::actingAs($karyawan);

    $response = $this->postJson('/api/absensi/masuk', [
        'latitude' => -7.0784947,
        'longitude' => 110.4119292,
        'kode_qr' => $qr->kode_qr,
        'foto_masuk' => \Illuminate\Http\UploadedFile::fake()->image('wajah.jpg'),
    ]);

    $response->assertStatus(200);

    $absensiHariIni = \App\Models\Absensi::where('karyawan_id', $karyawan->id)
        ->where('tanggal', '2026-08-10')
        ->first();

    expect($absensiHariIni)
        ->menit_terlambat->toBe(20)
        ->melebihi_toleransi_bulanan->toBeTrue();
});

it('masuk: melebihi_toleransi_bulanan tetap false kalau akumulasi masih di bawah toleransi', function () {
    \Illuminate\Support\Facades\Storage::fake('public');

    $this->travelTo(\Illuminate\Support\Carbon::parse('2026-08-10 08:05:00'));

    $instansi = \App\Models\Instansi::factory()->create([
        'latitude' => -7.0784947,
        'longitude' => 110.4119292,
        'radius_meter' => 100,
    ]);

    $karyawan = \App\Models\Karyawan::factory()->create([
        'instansi_id' => $instansi->id,
        'tipe_jadwal' => \App\Models\Karyawan::TIPE_UMUM,
    ]);

    $shift = \App\Models\Shift::factory()->create([
        'instansi_id' => $instansi->id,
        'jam_masuk' => '08:00:00',
        'toleransi_menit' => 30,
        'mode_toleransi' => 'akumulasi_bulanan',
    ]);

    \App\Models\KaryawanShift::factory()->create([
        'karyawan_id' => $karyawan->id,
        'shift_id' => $shift->id,
        'tanggal_berlaku' => '2026-08-01',
        'tanggal_berakhir' => null,
    ]);

    $qr = \App\Models\QrInstansi::factory()->create([
        'instansi_id' => $instansi->id,
        'is_active' => true,
        'expired_at' => null,
    ]);

    \Laravel\Sanctum\Sanctum::actingAs($karyawan);

    $this->postJson('/api/absensi/masuk', [
        'latitude' => -7.0784947,
        'longitude' => 110.4119292,
        'kode_qr' => $qr->kode_qr,
        'foto_masuk' => \Illuminate\Http\UploadedFile::fake()->image('wajah.jpg'),
    ])->assertStatus(200);

    expect(\App\Models\Absensi::where('karyawan_id', $karyawan->id)->first())
        ->menit_terlambat->toBe(5)
        ->melebihi_toleransi_bulanan->toBeFalse();
});

// --- Format jam di riwayat ---
//
// `jam_masuk` sebelumnya dikirim sebagai objek Carbon mentah, yang
// di-json_encode jadi ISO8601 penuh ("2026-08-10T08:00:00.000000Z") — bukan
// "08:00" seperti yang tersirat dari nama fieldnya. Cast 'datetime:H:i' cuma
// berlaku saat serialisasi lewat model, bukan saat objeknya ditaruh langsung
// ke array respons.

it('riwayat: jam_masuk shift dikirim sebagai H:i, bukan timestamp ISO', function () {
    $karyawan = \App\Models\Karyawan::factory()->create();

    $shift = \App\Models\Shift::factory()->create(['jam_masuk' => '08:00:00']);

    \App\Models\Absensi::factory()->create([
        'karyawan_id' => $karyawan->id,
        'shift_id' => $shift->id,
        'tanggal' => '2026-08-10',
        'waktu_masuk' => '2026-08-10 08:00:00',
    ]);

    \Laravel\Sanctum\Sanctum::actingAs($karyawan);

    $response = $this->getJson('/api/absensi/riwayat?bulan=8&tahun=2026');

    $response->assertStatus(200);

    expect($response->json('data.records.0.jam_masuk'))->toBe('08:00');
});
