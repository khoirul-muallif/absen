<?php

use App\Models\Cuti;
use App\Models\Dinas;
use App\Models\Instansi;
use App\Models\Jadwal;
use App\Models\Karyawan;
use App\Models\Shift;
use App\Models\TukarJadwal;
use Laravel\Sanctum\Sanctum;

function loginKaryawanJadwalTest(Karyawan $karyawan): void
{
    Sanctum::actingAs($karyawan, ['*']);
}

beforeEach(function () {
    $this->instansi = Instansi::factory()->create();
    $this->shift = Shift::factory()->create(['instansi_id' => $this->instansi->id]);
});

// ── GET /api/jadwal/saya ─────────────────────────────────────────────────

it('menampilkan jadwal milik sendiri dalam rentang tanggal', function () {
    $karyawan = Karyawan::factory()->create(['instansi_id' => $this->instansi->id]);
    $lain = Karyawan::factory()->create(['instansi_id' => $this->instansi->id]);

    $dalamRentang = Jadwal::factory()->create([
        'karyawan_id' => $karyawan->id,
        'shift_id' => $this->shift->id,
        'tanggal' => today()->addDays(3),
    ]);
    Jadwal::factory()->create([
        'karyawan_id' => $karyawan->id,
        'shift_id' => $this->shift->id,
        'tanggal' => today()->addDays(60), // di luar rentang
    ]);
    Jadwal::factory()->create([
        'karyawan_id' => $lain->id,
        'shift_id' => $this->shift->id,
        'tanggal' => today()->addDays(3),
    ]);

    loginSebagai($karyawan);

    $response = $this->getJson('/api/jadwal/saya?'.http_build_query([
        'tanggal_mulai' => today()->toDateString(),
        'tanggal_selesai' => today()->addDays(10)->toDateString(),
    ]))->assertOk();

    expect($response->json('data.total'))->toBe(1);
    expect($response->json('data.records.0.jadwal_id'))->toBe($dalamRentang->id);
});

it('mengecualikan jenis cuti dan dinas dari jadwal saya', function () {
    $karyawan = Karyawan::factory()->create(['instansi_id' => $this->instansi->id]);

    Jadwal::factory()->create([
        'karyawan_id' => $karyawan->id,
        'shift_id' => $this->shift->id,
        'tanggal' => today()->addDays(3),
        'jenis' => Jadwal::JENIS_CUTI,
    ]);
    $reguler = Jadwal::factory()->create([
        'karyawan_id' => $karyawan->id,
        'shift_id' => $this->shift->id,
        'tanggal' => today()->addDays(4),
        'jenis' => 'reguler',
    ]);

    loginSebagai($karyawan);

    $response = $this->getJson('/api/jadwal/saya?'.http_build_query([
        'tanggal_mulai' => today()->toDateString(),
        'tanggal_selesai' => today()->addDays(10)->toDateString(),
    ]))->assertOk();

    expect($response->json('data.total'))->toBe(1);
    expect($response->json('data.records.0.jadwal_id'))->toBe($reguler->id);
});

it('menandai bisa_diajukan false kalau jadwal sedang dipakai pengajuan TukarJadwal aktif', function () {
    $karyawan = Karyawan::factory()->create(['instansi_id' => $this->instansi->id]);
    $jadwal = Jadwal::factory()->create([
        'karyawan_id' => $karyawan->id,
        'shift_id' => $this->shift->id,
        'tanggal' => today()->addDays(3),
    ]);

    TukarJadwal::factory()->create([
        'jadwal_id' => $jadwal->id,
        'status' => 'menunggu_rekan',
    ]);

    loginSebagai($karyawan);

    $response = $this->getJson('/api/jadwal/saya?'.http_build_query([
        'tanggal_mulai' => today()->toDateString(),
        'tanggal_selesai' => today()->addDays(10)->toDateString(),
    ]))->assertOk();

    expect($response->json('data.records.0.bisa_diajukan'))->toBeFalse();
});

it('menandai bisa_diajukan true kalau pengajuan TukarJadwal sebelumnya sudah ditolak', function () {
    $karyawan = Karyawan::factory()->create(['instansi_id' => $this->instansi->id]);
    $jadwal = Jadwal::factory()->create([
        'karyawan_id' => $karyawan->id,
        'shift_id' => $this->shift->id,
        'tanggal' => today()->addDays(3),
    ]);

    TukarJadwal::factory()->create([
        'jadwal_id' => $jadwal->id,
        'status' => 'ditolak_rekan',
    ]);

    loginSebagai($karyawan);

    $response = $this->getJson('/api/jadwal/saya?'.http_build_query([
        'tanggal_mulai' => today()->toDateString(),
        'tanggal_selesai' => today()->addDays(10)->toDateString(),
    ]))->assertOk();

    expect($response->json('data.records.0.bisa_diajukan'))->toBeTrue();
});

it('menolak request jadwal saya tanpa tanggal_mulai/tanggal_selesai', function () {
    $karyawan = Karyawan::factory()->create(['instansi_id' => $this->instansi->id]);
    loginSebagai($karyawan);

    $this->getJson('/api/jadwal/saya')->assertStatus(422);
});

// ── GET /api/jadwal/rekan ────────────────────────────────────────────────

it('menampilkan jadwal rekan satu unit_kerja dan satu instansi', function () {
    $karyawan = Karyawan::factory()->create([
        'instansi_id' => $this->instansi->id,
        'unit_kerja' => 'IGD',
    ]);
    $rekanSatuUnit = Karyawan::factory()->create([
        'instansi_id' => $this->instansi->id,
        'unit_kerja' => 'IGD',
    ]);

    $tanggal = today()->addDays(5);
    $jadwalRekan = Jadwal::factory()->create([
        'karyawan_id' => $rekanSatuUnit->id,
        'shift_id' => $this->shift->id,
        'tanggal' => $tanggal,
    ]);

    loginSebagai($karyawan);

    $response = $this->getJson('/api/jadwal/rekan?tanggal='.$tanggal->toDateString())
        ->assertOk();

    expect($response->json('data.total'))->toBe(1);
    expect($response->json('data.records.0.jadwal_id'))->toBe($jadwalRekan->id);
    expect($response->json('data.records.0.karyawan_id'))->toBe($rekanSatuUnit->id);
});

it('tidak menampilkan jadwal rekan beda unit_kerja', function () {
    $karyawan = Karyawan::factory()->create([
        'instansi_id' => $this->instansi->id,
        'unit_kerja' => 'IGD',
    ]);
    $bedaUnit = Karyawan::factory()->create([
        'instansi_id' => $this->instansi->id,
        'unit_kerja' => 'ICU',
    ]);

    $tanggal = today()->addDays(5);
    Jadwal::factory()->create([
        'karyawan_id' => $bedaUnit->id,
        'shift_id' => $this->shift->id,
        'tanggal' => $tanggal,
    ]);

    loginSebagai($karyawan);

    $response = $this->getJson('/api/jadwal/rekan?tanggal='.$tanggal->toDateString())
        ->assertOk();

    expect($response->json('data.total'))->toBe(0);
});

it('tidak menampilkan jadwal rekan beda instansi walau unit_kerja sama', function () {
    $karyawan = Karyawan::factory()->create([
        'instansi_id' => $this->instansi->id,
        'unit_kerja' => 'IGD',
    ]);

    $instansiLain = Instansi::factory()->create();
    $shiftInstansiLain = Shift::factory()->create(['instansi_id' => $instansiLain->id]);
    $karyawanInstansiLain = Karyawan::factory()->create([
        'instansi_id' => $instansiLain->id,
        'unit_kerja' => 'IGD',
    ]);

    $tanggal = today()->addDays(5);
    Jadwal::factory()->create([
        'karyawan_id' => $karyawanInstansiLain->id,
        'shift_id' => $shiftInstansiLain->id,
        'tanggal' => $tanggal,
    ]);

    loginSebagai($karyawan);

    $response = $this->getJson('/api/jadwal/rekan?tanggal='.$tanggal->toDateString())
        ->assertOk();

    expect($response->json('data.total'))->toBe(0);
});

it('tidak menampilkan jadwal diri sendiri di daftar rekan', function () {
    $karyawan = Karyawan::factory()->create(['instansi_id' => $this->instansi->id]);
    $tanggal = today()->addDays(5);
    Jadwal::factory()->create([
        'karyawan_id' => $karyawan->id,
        'shift_id' => $this->shift->id,
        'tanggal' => $tanggal,
    ]);

    loginSebagai($karyawan);

    $response = $this->getJson('/api/jadwal/rekan?tanggal='.$tanggal->toDateString())
        ->assertOk();

    expect($response->json('data.total'))->toBe(0);
});

it('tidak menampilkan jadwal rekan yang sudah tidak aktif', function () {
    $karyawan = Karyawan::factory()->create(['instansi_id' => $this->instansi->id]);
    $rekanNonaktif = Karyawan::factory()->create([
        'instansi_id' => $this->instansi->id,
        'unit_kerja' => $karyawan->unit_kerja,
        'is_active' => false,
    ]);

    $tanggal = today()->addDays(5);
    Jadwal::factory()->create([
        'karyawan_id' => $rekanNonaktif->id,
        'shift_id' => $this->shift->id,
        'tanggal' => $tanggal,
    ]);

    loginSebagai($karyawan);

    $response = $this->getJson('/api/jadwal/rekan?tanggal='.$tanggal->toDateString())
        ->assertOk();

    expect($response->json('data.total'))->toBe(0);
});

it('mengecualikan jenis cuti dan dinas dari jadwal rekan', function () {
    $karyawan = Karyawan::factory()->create(['instansi_id' => $this->instansi->id]);
    $rekan = Karyawan::factory()->create([
        'instansi_id' => $this->instansi->id,
        'unit_kerja' => $karyawan->unit_kerja,
    ]);

    $tanggal = today()->addDays(5);
    Jadwal::factory()->create([
        'karyawan_id' => $rekan->id,
        'shift_id' => $this->shift->id,
        'tanggal' => $tanggal,
        'jenis' => Jadwal::JENIS_DINAS,
    ]);

    loginSebagai($karyawan);

    $response = $this->getJson('/api/jadwal/rekan?tanggal='.$tanggal->toDateString())
        ->assertOk();

    expect($response->json('data.total'))->toBe(0);
});

it('menolak request jadwal rekan tanpa tanggal', function () {
    $karyawan = Karyawan::factory()->create(['instansi_id' => $this->instansi->id]);
    loginSebagai($karyawan);

    $this->getJson('/api/jadwal/rekan')->assertStatus(422);
});
