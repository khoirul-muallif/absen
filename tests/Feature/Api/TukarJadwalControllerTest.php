<?php

use App\Models\Cuti;
use App\Models\Dinas;
use App\Models\Instansi;
use App\Models\Jadwal;
use App\Models\Karyawan;
use App\Models\Shift;
use App\Models\TukarJadwal;
use Laravel\Sanctum\Sanctum;

function loginKaryawanSebagai(Karyawan $karyawan): void
{
    Sanctum::actingAs($karyawan, ['*']);
}

beforeEach(function () {
    $this->instansi = Instansi::factory()->create();
    $this->shift = Shift::factory()->create(['instansi_id' => $this->instansi->id]);
});

// ── Mode pindah ──────────────────────────────────────────────────────────

it('bisa mengajukan pindah jadwal sendiri', function () {
    $karyawan = Karyawan::factory()->create(['instansi_id' => $this->instansi->id]);
    $jadwal = Jadwal::factory()->create([
        'karyawan_id' => $karyawan->id,
        'shift_id' => $this->shift->id,
        'tanggal' => today()->addDays(3),
    ]);

    loginKaryawanSebagai($karyawan);

    $this->postJson('/api/tukar-jadwal', [
        'mode' => 'pindah',
        'jadwal_id' => $jadwal->id,
        'tanggal_baru' => today()->addDays(10)->toDateString(),
        'alasan' => 'Ada acara keluarga',
    ])->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'menunggu_admin');

    $this->assertDatabaseHas('tukar_jadwals', [
        'jadwal_id' => $jadwal->id,
        'karyawan_pengaju_id' => $karyawan->id,
        'status' => 'menunggu_admin',
    ]);
});

it('menolak pindah kalau jadwal bukan milik sendiri', function () {
    $karyawan = Karyawan::factory()->create(['instansi_id' => $this->instansi->id]);
    $oranglain = Karyawan::factory()->create(['instansi_id' => $this->instansi->id]);
    $jadwal = Jadwal::factory()->create([
        'karyawan_id' => $oranglain->id,
        'shift_id' => $this->shift->id,
        'tanggal' => today()->addDays(3),
    ]);

    loginKaryawanSebagai($karyawan);

    $this->postJson('/api/tukar-jadwal', [
        'mode' => 'pindah',
        'jadwal_id' => $jadwal->id,
        'tanggal_baru' => today()->addDays(10)->toDateString(),
        'alasan' => 'Test',
    ])->assertStatus(403)
        ->assertJsonPath('message', 'Jadwal ini bukan milik Anda.');
});

it('menolak pindah ke tanggal yang sudah ada jadwal sendiri', function () {
    $karyawan = Karyawan::factory()->create(['instansi_id' => $this->instansi->id]);
    $jadwal = Jadwal::factory()->create([
        'karyawan_id' => $karyawan->id,
        'shift_id' => $this->shift->id,
        'tanggal' => today()->addDays(3),
    ]);
    $tanggalBentrok = today()->addDays(9);
    Jadwal::factory()->create([
        'karyawan_id' => $karyawan->id,
        'shift_id' => $this->shift->id,
        'tanggal' => $tanggalBentrok,
    ]);

    loginKaryawanSebagai($karyawan);

    $this->postJson('/api/tukar-jadwal', [
        'mode' => 'pindah',
        'jadwal_id' => $jadwal->id,
        'tanggal_baru' => $tanggalBentrok->toDateString(),
        'alasan' => 'Test',
    ])->assertStatus(422)
        ->assertJsonPath('message', 'Anda sudah punya jadwal di tanggal tersebut.');
});

it('menolak pindah ke tanggal yang bentrok dengan cuti/dinas approved', function () {
    $karyawan = Karyawan::factory()->create(['instansi_id' => $this->instansi->id]);
    $jadwal = Jadwal::factory()->create([
        'karyawan_id' => $karyawan->id,
        'shift_id' => $this->shift->id,
        'tanggal' => today()->addDays(3),
    ]);
    $tanggalCuti = today()->addDays(15);
    Cuti::factory()->create([
        'karyawan_id' => $karyawan->id,
        'status' => 'approved',
        'tanggal_mulai' => $tanggalCuti,
        'tanggal_selesai' => $tanggalCuti,
    ]);

    loginKaryawanSebagai($karyawan);

    $this->postJson('/api/tukar-jadwal', [
        'mode' => 'pindah',
        'jadwal_id' => $jadwal->id,
        'tanggal_baru' => $tanggalCuti->toDateString(),
        'alasan' => 'Test',
    ])->assertStatus(422)
        ->assertJsonPath('message', 'Anda sedang cuti/dinas (disetujui) pada tanggal tersebut.');
});

// ── Mode tukar ───────────────────────────────────────────────────────────

it('bisa mengajukan tukar jadwal dengan rekan, status awal menunggu_rekan', function () {
    $pengaju = Karyawan::factory()->create(['instansi_id' => $this->instansi->id]);
    $tujuan = Karyawan::factory()->create(['instansi_id' => $this->instansi->id]);

    $jadwalAsal = Jadwal::factory()->create([
        'karyawan_id' => $pengaju->id,
        'shift_id' => $this->shift->id,
        'tanggal' => today()->addDays(3),
    ]);
    $jadwalTujuan = Jadwal::factory()->create([
        'karyawan_id' => $tujuan->id,
        'shift_id' => $this->shift->id,
        'tanggal' => today()->addDays(5),
    ]);

    loginKaryawanSebagai($pengaju);

    $this->postJson('/api/tukar-jadwal', [
        'mode' => 'tukar',
        'jadwal_id' => $jadwalAsal->id,
        'jadwal_tujuan_id' => $jadwalTujuan->id,
        'alasan' => 'Ada keperluan',
    ])->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'menunggu_rekan');

    $this->assertDatabaseHas('tukar_jadwals', [
        'jadwal_id' => $jadwalAsal->id,
        'jadwal_tujuan_id' => $jadwalTujuan->id,
        'karyawan_pengaju_id' => $pengaju->id,
        'karyawan_tujuan_id' => $tujuan->id,
        'status' => 'menunggu_rekan',
    ]);
});

it('menolak tukar dengan jadwal milik sendiri', function () {
    $pengaju = Karyawan::factory()->create(['instansi_id' => $this->instansi->id]);

    $jadwalAsal = Jadwal::factory()->create([
        'karyawan_id' => $pengaju->id,
        'shift_id' => $this->shift->id,
        'tanggal' => today()->addDays(3),
    ]);
    $jadwalLain = Jadwal::factory()->create([
        'karyawan_id' => $pengaju->id,
        'shift_id' => $this->shift->id,
        'tanggal' => today()->addDays(5),
    ]);

    loginKaryawanSebagai($pengaju);

    $this->postJson('/api/tukar-jadwal', [
        'mode' => 'tukar',
        'jadwal_id' => $jadwalAsal->id,
        'jadwal_tujuan_id' => $jadwalLain->id,
        'alasan' => 'Test',
    ])->assertStatus(422)
        ->assertJsonPath('message', 'Tidak bisa menukar dengan jadwal milik sendiri.');
});

it('menolak tukar kalau salah satu karyawan sedang cuti/dinas approved', function () {
    $pengaju = Karyawan::factory()->create(['instansi_id' => $this->instansi->id]);
    $tujuan = Karyawan::factory()->create(['instansi_id' => $this->instansi->id]);

    $jadwalAsal = Jadwal::factory()->create([
        'karyawan_id' => $pengaju->id,
        'shift_id' => $this->shift->id,
        'tanggal' => today()->addDays(3),
    ]);
    $jadwalTujuan = Jadwal::factory()->create([
        'karyawan_id' => $tujuan->id,
        'shift_id' => $this->shift->id,
        'tanggal' => today()->addDays(5),
    ]);

    Dinas::factory()->create([
        'karyawan_id' => $tujuan->id,
        'status' => 'approved',
        'tanggal_mulai' => $jadwalTujuan->tanggal,
        'tanggal_selesai' => $jadwalTujuan->tanggal,
    ]);

    loginKaryawanSebagai($pengaju);

    $this->postJson('/api/tukar-jadwal', [
        'mode' => 'tukar',
        'jadwal_id' => $jadwalAsal->id,
        'jadwal_tujuan_id' => $jadwalTujuan->id,
        'alasan' => 'Test',
    ])->assertStatus(422)
        ->assertJsonPath('message', 'Tidak bisa ditukar: salah satu karyawan sedang cuti/dinas (disetujui) di tanggal yang terlibat.');
});

it('menolak tukar kalau jadwal sudah dipakai pengajuan lain yang masih aktif', function () {
    $pengaju = Karyawan::factory()->create(['instansi_id' => $this->instansi->id]);
    $tujuan = Karyawan::factory()->create(['instansi_id' => $this->instansi->id]);

    $jadwalAsal = Jadwal::factory()->create([
        'karyawan_id' => $pengaju->id,
        'shift_id' => $this->shift->id,
        'tanggal' => today()->addDays(3),
    ]);
    $jadwalTujuan = Jadwal::factory()->create([
        'karyawan_id' => $tujuan->id,
        'shift_id' => $this->shift->id,
        'tanggal' => today()->addDays(5),
    ]);

    TukarJadwal::factory()->create([
        'jadwal_id' => $jadwalAsal->id,
        'status' => 'menunggu_rekan',
    ]);

    loginKaryawanSebagai($pengaju);

    $this->postJson('/api/tukar-jadwal', [
        'mode' => 'tukar',
        'jadwal_id' => $jadwalAsal->id,
        'jadwal_tujuan_id' => $jadwalTujuan->id,
        'alasan' => 'Test rebutan',
    ])->assertStatus(422)
        ->assertJsonPath('message', 'Jadwal ini sudah dipakai di pengajuan tukar/pindah lain yang masih diproses.');
});

// ── Respon rekan ─────────────────────────────────────────────────────────

it('rekan tujuan bisa menyetujui pengajuan, status berubah ke menunggu_admin', function () {
    $pengaju = Karyawan::factory()->create(['instansi_id' => $this->instansi->id]);
    $tujuan = Karyawan::factory()->create(['instansi_id' => $this->instansi->id]);

    $jadwalAsal = Jadwal::factory()->create(['karyawan_id' => $pengaju->id, 'shift_id' => $this->shift->id]);
    $jadwalTujuan = Jadwal::factory()->create(['karyawan_id' => $tujuan->id, 'shift_id' => $this->shift->id]);

    $tukarJadwal = TukarJadwal::factory()->create([
        'jadwal_id' => $jadwalAsal->id,
        'jadwal_tujuan_id' => $jadwalTujuan->id,
        'status' => 'menunggu_rekan',
    ]);

    loginKaryawanSebagai($tujuan);

    $this->postJson("/api/tukar-jadwal/{$tukarJadwal->id}/respon-rekan", [
        'setuju' => true,
    ])->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'menunggu_admin');

    expect($tukarJadwal->fresh()->status)->toBe('menunggu_admin');
});

it('rekan tujuan bisa menolak pengajuan, status berubah ke ditolak_rekan', function () {
    $pengaju = Karyawan::factory()->create(['instansi_id' => $this->instansi->id]);
    $tujuan = Karyawan::factory()->create(['instansi_id' => $this->instansi->id]);

    $jadwalAsal = Jadwal::factory()->create(['karyawan_id' => $pengaju->id, 'shift_id' => $this->shift->id]);
    $jadwalTujuan = Jadwal::factory()->create(['karyawan_id' => $tujuan->id, 'shift_id' => $this->shift->id]);

    $tukarJadwal = TukarJadwal::factory()->create([
        'jadwal_id' => $jadwalAsal->id,
        'jadwal_tujuan_id' => $jadwalTujuan->id,
        'status' => 'menunggu_rekan',
    ]);

    loginKaryawanSebagai($tujuan);

    $this->postJson("/api/tukar-jadwal/{$tukarJadwal->id}/respon-rekan", [
        'setuju' => false,
        'catatan' => 'Saya juga ada acara di tanggal itu',
    ])->assertOk()
        ->assertJsonPath('data.status', 'ditolak_rekan');

    expect($tukarJadwal->fresh())
        ->status->toBe('ditolak_rekan')
        ->catatan_penolakan_rekan->toBe('Saya juga ada acara di tanggal itu');
});

it('menolak respon rekan dari karyawan yang bukan pihak tujuan', function () {
    $pengaju = Karyawan::factory()->create(['instansi_id' => $this->instansi->id]);
    $tujuan = Karyawan::factory()->create(['instansi_id' => $this->instansi->id]);
    $bukanSiapaSiapa = Karyawan::factory()->create(['instansi_id' => $this->instansi->id]);

    $jadwalAsal = Jadwal::factory()->create(['karyawan_id' => $pengaju->id, 'shift_id' => $this->shift->id]);
    $jadwalTujuan = Jadwal::factory()->create(['karyawan_id' => $tujuan->id, 'shift_id' => $this->shift->id]);

    $tukarJadwal = TukarJadwal::factory()->create([
        'jadwal_id' => $jadwalAsal->id,
        'jadwal_tujuan_id' => $jadwalTujuan->id,
        'status' => 'menunggu_rekan',
    ]);

    loginKaryawanSebagai($bukanSiapaSiapa);

    $this->postJson("/api/tukar-jadwal/{$tukarJadwal->id}/respon-rekan", [
        'setuju' => true,
    ])->assertStatus(422)
        ->assertJsonPath('message', 'Anda bukan pihak yang dituju pada pengajuan ini.');
});

it('menolak respon rekan kalau status sudah bukan menunggu_rekan', function () {
    $pengaju = Karyawan::factory()->create(['instansi_id' => $this->instansi->id]);
    $tujuan = Karyawan::factory()->create(['instansi_id' => $this->instansi->id]);

    $jadwalAsal = Jadwal::factory()->create(['karyawan_id' => $pengaju->id, 'shift_id' => $this->shift->id]);
    $jadwalTujuan = Jadwal::factory()->create(['karyawan_id' => $tujuan->id, 'shift_id' => $this->shift->id]);

    $tukarJadwal = TukarJadwal::factory()->create([
        'jadwal_id' => $jadwalAsal->id,
        'jadwal_tujuan_id' => $jadwalTujuan->id,
        'status' => 'menunggu_admin',
    ]);

    loginKaryawanSebagai($tujuan);

    $this->postJson("/api/tukar-jadwal/{$tukarJadwal->id}/respon-rekan", [
        'setuju' => true,
    ])->assertStatus(422)
        ->assertJsonPath('message', 'Pengajuan ini bukan lagi tahap menunggu respon rekan.');
});

// ── Riwayat ──────────────────────────────────────────────────────────────

it('riwayat menampilkan pengajuan sebagai pengaju maupun sebagai tujuan', function () {
    $karyawan = Karyawan::factory()->create(['instansi_id' => $this->instansi->id]);
    $lain = Karyawan::factory()->create(['instansi_id' => $this->instansi->id]);

    $jadwalMilikSendiri = Jadwal::factory()->create([
        'karyawan_id' => $karyawan->id,
        'shift_id' => $this->shift->id,
        'tanggal' => today()->addDays(1),
    ]);
    $jadwalMilikSendiri2 = Jadwal::factory()->create([
        'karyawan_id' => $karyawan->id,
        'shift_id' => $this->shift->id,
        'tanggal' => today()->addDays(2),
    ]);
    $jadwalLain = Jadwal::factory()->create([
        'karyawan_id' => $lain->id,
        'shift_id' => $this->shift->id,
        'tanggal' => today()->addDays(3),
    ]);
    $jadwalLain2 = Jadwal::factory()->create([
        'karyawan_id' => $lain->id,
        'shift_id' => $this->shift->id,
        'tanggal' => today()->addDays(4),
    ]);

    // karyawan sebagai pengaju
    TukarJadwal::factory()->create([
        'jadwal_id' => $jadwalMilikSendiri->id,
        'tanggal_baru' => today()->addDays(20),
    ]);

    // karyawan sebagai tujuan (rekan lain yang mengajukan ke jadwalnya)
    TukarJadwal::factory()->create([
        'jadwal_id' => $jadwalLain->id,
        'jadwal_tujuan_id' => $jadwalMilikSendiri2->id,
    ]);

    // punya orang lain, tidak boleh muncul
    TukarJadwal::factory()->create([
        'jadwal_id' => $jadwalLain2->id,
        'tanggal_baru' => today()->addDays(25),
    ]);

    loginKaryawanSebagai($karyawan);

    $response = $this->getJson('/api/tukar-jadwal')->assertOk();

    expect($response->json('data'))->toHaveCount(2);
});

// ── Batalkan ─────────────────────────────────────────────────────────────

it('bisa membatalkan pengajuan sendiri yang masih aktif', function () {
    $karyawan = Karyawan::factory()->create(['instansi_id' => $this->instansi->id]);
    $jadwal = Jadwal::factory()->create(['karyawan_id' => $karyawan->id, 'shift_id' => $this->shift->id]);

    $tukarJadwal = TukarJadwal::factory()->create([
        'jadwal_id' => $jadwal->id,
        'tanggal_baru' => today()->addDays(20),
    ]);

    loginKaryawanSebagai($karyawan);

    $this->deleteJson("/api/tukar-jadwal/{$tukarJadwal->id}")
        ->assertOk()
        ->assertJsonPath('success', true);

    $this->assertDatabaseMissing('tukar_jadwals', ['id' => $tukarJadwal->id]);
});

it('tidak bisa membatalkan pengajuan yang sudah approved', function () {
    $karyawan = Karyawan::factory()->create(['instansi_id' => $this->instansi->id]);
    $jadwal = Jadwal::factory()->create(['karyawan_id' => $karyawan->id, 'shift_id' => $this->shift->id]);

    $tukarJadwal = TukarJadwal::factory()->create([
        'jadwal_id' => $jadwal->id,
        'tanggal_baru' => today()->addDays(20),
        'status' => 'approved',
    ]);

    loginKaryawanSebagai($karyawan);

    $this->deleteJson("/api/tukar-jadwal/{$tukarJadwal->id}")
        ->assertStatus(422)
        ->assertJsonPath('message', 'Pengajuan ini sudah tidak bisa dibatalkan (sudah diproses).');
});

it('404 kalau membatalkan pengajuan milik karyawan lain', function () {
    $karyawan = Karyawan::factory()->create(['instansi_id' => $this->instansi->id]);
    $lain = Karyawan::factory()->create(['instansi_id' => $this->instansi->id]);
    $jadwal = Jadwal::factory()->create(['karyawan_id' => $lain->id, 'shift_id' => $this->shift->id]);

    $tukarJadwal = TukarJadwal::factory()->create([
        'jadwal_id' => $jadwal->id,
        'tanggal_baru' => today()->addDays(20),
    ]);

    loginKaryawanSebagai($karyawan);

    $this->deleteJson("/api/tukar-jadwal/{$tukarJadwal->id}")
        ->assertStatus(404);
});
