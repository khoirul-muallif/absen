<?php

use App\Models\Cuti;
use App\Models\JenisCuti;
use App\Models\Karyawan;
use App\Models\KuotaCuti;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    // Waktu dibekukan supaya tanggal hardcode di file ini (2026-08-xx) selalu
    // berada di masa depan relatif terhadap "hari ini" — aturan
    // after_or_equal:today dari fase 23 bikin test ini jadi bom waktu kalau
    // tanggalnya dibiarkan bergantung pada tanggal server saat dijalankan.
    // Pola bug yang sama pernah terjadi di Shift::hitungMenitTerlambat()
    // (fase 14): test lolos cuma karena tanggal hardcode kebetulan sama
    // dengan tanggal server saat test ditulis.
    $this->travelTo(Carbon::parse('2026-08-01 08:00:00'));

    $this->karyawan = Karyawan::factory()->create();
    Sanctum::actingAs($this->karyawan);
    $this->jenisCuti = JenisCuti::factory()->create([
        'nama' => 'Cuti Tahunan',
        'default_kuota' => 12,
        'potong_kuota' => true,
        'perlu_lampiran' => false,
        'is_active' => true,
    ]);
});

test('bisa melihat sisa kuota tanpa row KuotaCuti (fallback ke default_kuota)', function () {
    $response = $this->getJson('/api/cuti/kuota?tahun=2026');

    $response->assertStatus(200);
    $record = collect($response->json('data.records'))->firstWhere('jenis_cuti_id', $this->jenisCuti->id);
    expect($record['sisa'])->toBe(12);
});

test('sisa kuota mencerminkan KuotaCuti yang sudah ada', function () {
    KuotaCuti::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'jenis_cuti_id' => $this->jenisCuti->id,
        'tahun' => 2026,
        'kuota' => 12,
        'terpakai' => 5,
    ]);

    $response = $this->getJson('/api/cuti/kuota?tahun=2026');

    $record = collect($response->json('data.records'))->firstWhere('jenis_cuti_id', $this->jenisCuti->id);
    expect($record['sisa'])->toBe(7);
});

test('bisa mengajukan cuti dengan jumlah_hari dihitung otomatis', function () {
    $response = $this->postJson('/api/cuti', [
        'jenis_cuti_id'   => $this->jenisCuti->id,
        'tanggal_mulai'   => '2026-08-01',
        'tanggal_selesai' => '2026-08-03',
        'alasan'          => 'Liburan keluarga',
    ]);

    $response->assertStatus(201)->assertJson([
        'success' => true,
        'data' => ['jumlah_hari' => 3],
    ]);
});

test('menolak pengajuan kalau melebihi sisa kuota', function () {
    KuotaCuti::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'jenis_cuti_id' => $this->jenisCuti->id,
        'tahun' => 2026,
        'kuota' => 12,
        'terpakai' => 10,
    ]);

    $response = $this->postJson('/api/cuti', [
        'jenis_cuti_id'   => $this->jenisCuti->id,
        'tanggal_mulai'   => '2026-08-01',
        'tanggal_selesai' => '2026-08-05', // 5 hari, sisa cuma 2
        'alasan'          => 'Liburan panjang',
    ]);

    // Assert spesifik ke sebab penolakan: kalau cuma assertStatus(422),
    // test ini tetap hijau walau yang menolak sebenarnya validasi tanggal
    // (atau sebab lain apa pun) dan pengecekan kuotanya sudah tidak jalan.
    $response->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('data.sisa_kuota', 2);

    expect(Cuti::where('karyawan_id', $this->karyawan->id)->exists())->toBeFalse();
});

test('jenis cuti yang tidak potong_kuota tidak dicek terhadap kuota', function () {
    $jenisSakit = JenisCuti::factory()->create([
        'nama' => 'Cuti Sakit',
        'potong_kuota' => false,
        'perlu_lampiran' => true,
    ]);

    $response = $this->postJson('/api/cuti', [
        'jenis_cuti_id'   => $jenisSakit->id,
        'tanggal_mulai'   => '2026-08-01',
        'tanggal_selesai' => '2026-08-10',
        'alasan'          => 'Sakit demam berdarah',
        'lampiran'        => \Illuminate\Http\UploadedFile::fake()->create('surat.pdf', 500),
    ]);

    $response->assertStatus(201);
});

test('menolak jika jenis cuti perlu_lampiran tapi tidak ada file', function () {
    $jenisSakit = JenisCuti::factory()->create([
        'nama' => 'Cuti Sakit',
        'potong_kuota' => false,
        'perlu_lampiran' => true,
    ]);

    $response = $this->postJson('/api/cuti', [
        'jenis_cuti_id'   => $jenisSakit->id,
        'tanggal_mulai'   => '2026-08-01',
        'tanggal_selesai' => '2026-08-02',
        'alasan'          => 'Sakit',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('message', 'Jenis cuti "Cuti Sakit" wajib melampirkan surat keterangan.');
});

test('menolak jenis_cuti_id yang tidak aktif', function () {
    $jenisNonAktif = JenisCuti::factory()->create(['is_active' => false]);

    $response = $this->postJson('/api/cuti', [
        'jenis_cuti_id'   => $jenisNonAktif->id,
        'tanggal_mulai'   => '2026-08-01',
        'tanggal_selesai' => '2026-08-02',
        'alasan'          => 'Tes',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('message', 'Jenis cuti ini sudah tidak aktif.');
});

test('riwayat hanya menampilkan cuti milik karyawan yang login', function () {
    Cuti::factory()->create(['karyawan_id' => $this->karyawan->id, 'jenis_cuti_id' => $this->jenisCuti->id]);
    $karyawanLain = Karyawan::factory()->create();
    Cuti::factory()->create(['karyawan_id' => $karyawanLain->id, 'jenis_cuti_id' => $this->jenisCuti->id]);

    $response = $this->getJson('/api/cuti');

    $response->assertStatus(200)->assertJsonCount(1, 'data.records');
});

test('bisa membatalkan cuti yang masih pending', function () {
    $cuti = Cuti::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'jenis_cuti_id' => $this->jenisCuti->id,
        'status' => 'pending',
    ]);

    $response = $this->deleteJson("/api/cuti/{$cuti->id}");

    $response->assertStatus(200);
    expect(Cuti::find($cuti->id))->toBeNull();
});

test('tidak bisa membatalkan cuti yang sudah approved', function () {
    $cuti = Cuti::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'jenis_cuti_id' => $this->jenisCuti->id,
        'status' => 'approved',
    ]);

    $response = $this->deleteJson("/api/cuti/{$cuti->id}");

    $response->assertStatus(422);
    expect(Cuti::find($cuti->id))->not->toBeNull();
});

test('404 kalau membatalkan cuti milik karyawan lain', function () {
    $karyawanLain = Karyawan::factory()->create();
    $cuti = Cuti::factory()->create([
        'karyawan_id' => $karyawanLain->id,
        'jenis_cuti_id' => $this->jenisCuti->id,
        'status' => 'pending',
    ]);

    $response = $this->deleteJson("/api/cuti/{$cuti->id}");

    $response->assertStatus(404);
});

it('mengizinkan pengajuan yang pas dengan sisa setelah dikurangi pending lain (boundary)', function () {
    $jenisCuti = JenisCuti::factory()->create(['potong_kuota' => true]);
    KuotaCuti::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'jenis_cuti_id' => $jenisCuti->id,
        'tahun' => 2026,
        'kuota' => 5,
        'terpakai' => 0,
    ]);

    // Pengajuan pertama: 3 hari, masih pending (belum menyentuh terpakai)
    Cuti::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'jenis_cuti_id' => $jenisCuti->id,
        'tanggal_mulai' => '2026-08-01',
        'tanggal_selesai' => '2026-08-03',
        'jumlah_hari' => 3,
        'status' => 'pending',
    ]);

    // Sisa efektif tinggal 2 (5 - 0 terpakai - 3 pending). Pengajuan 2 hari
    // harus PAS diterima — sisi lain dari batas yang diuji test berikutnya.
    $response = $this->postJson('/api/cuti', [
        'jenis_cuti_id'   => $jenisCuti->id,
        'tanggal_mulai'   => '2026-08-10',
        'tanggal_selesai' => '2026-08-11',
        'alasan'          => 'Keperluan keluarga',
    ]);

    $response->assertStatus(201);

    expect(Cuti::where('karyawan_id', $this->karyawan->id)->count())->toBe(2);
});

it('menolak pengajuan kalau total dengan pending lain melebihi sisa kuota', function () {
    $jenisCuti = JenisCuti::factory()->create(['potong_kuota' => true]);
    KuotaCuti::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'jenis_cuti_id' => $jenisCuti->id,
        'tahun' => 2026,
        'kuota' => 5,
        'terpakai' => 0,
    ]);

    // Pengajuan pertama: 3 hari, masih pending
    Cuti::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'jenis_cuti_id' => $jenisCuti->id,
        'tanggal_mulai' => '2026-08-01',
        'tanggal_selesai' => '2026-08-03',
        'jumlah_hari' => 3,
        'status' => 'pending',
    ]);

    // Pengajuan kedua: 3 hari lagi — total 6 > kuota 5
    $response = $this->postJson('/api/cuti', [
        'jenis_cuti_id'   => $jenisCuti->id,
        'tanggal_mulai'   => '2026-08-10',
        'tanggal_selesai' => '2026-08-12',
        'alasan'          => 'Keperluan keluarga',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('data.sisa_kuota', 2); // 5 - 0 terpakai - 3 pending

    expect(Cuti::where('karyawan_id', $this->karyawan->id)->count())->toBe(1);
});

test('menolak pengajuan cuti untuk tanggal_mulai yang sudah lewat', function () {
    $response = $this->postJson('/api/cuti', [
        'jenis_cuti_id'   => $this->jenisCuti->id,
        'tanggal_mulai'   => today()->subDay()->toDateString(),
        'tanggal_selesai' => today()->addDay()->toDateString(),
        'alasan'          => 'Tes tanggal lewat',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['tanggal_mulai']);

    expect(Cuti::where('karyawan_id', $this->karyawan->id)->exists())->toBeFalse();
});

test('menolak pengajuan cuti yang melintasi pergantian tahun', function () {
    $response = $this->postJson('/api/cuti', [
        'jenis_cuti_id'   => $this->jenisCuti->id,
        'tanggal_mulai'   => '2026-12-30',
        'tanggal_selesai' => '2027-01-02',
        'alasan'          => 'Tes lintas tahun',
    ]);

    $response->assertStatus(422)
        ->assertJsonFragment(['message' => 'Pengajuan cuti tidak boleh melintasi pergantian tahun. Ajukan terpisah untuk masing-masing tahun (mis. sisa hari di Desember, lalu pengajuan baru di Januari).']);
    expect(Cuti::where('karyawan_id', $this->karyawan->id)->exists())->toBeFalse();
});

test('menolak pengajuan cuti yang bentrok dengan cuti approved lain', function () {
    Cuti::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'jenis_cuti_id' => $this->jenisCuti->id,
        'tanggal_mulai' => '2026-08-01',
        'tanggal_selesai' => '2026-08-05',
        'status' => 'approved',
    ]);

    $response = $this->postJson('/api/cuti', [
        'jenis_cuti_id'   => $this->jenisCuti->id,
        'tanggal_mulai'   => '2026-08-03',
        'tanggal_selesai' => '2026-08-07',
        'alasan'          => 'Tes bentrok cuti',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('message', 'Anda sudah tercatat cuti/dinas (disetujui) yang bentrok dengan rentang tanggal ini.');

    expect(Cuti::where('karyawan_id', $this->karyawan->id)->where('status', 'pending')->exists())->toBeFalse();
});

test('menolak pengajuan cuti yang bentrok dengan dinas approved lain', function () {
    \App\Models\Dinas::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'tanggal_mulai' => '2026-08-01',
        'tanggal_selesai' => '2026-08-05',
        'status' => 'approved',
    ]);

    $response = $this->postJson('/api/cuti', [
        'jenis_cuti_id'   => $this->jenisCuti->id,
        'tanggal_mulai'   => '2026-08-03',
        'tanggal_selesai' => '2026-08-07',
        'alasan'          => 'Tes bentrok dinas',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('message', 'Anda sudah tercatat cuti/dinas (disetujui) yang bentrok dengan rentang tanggal ini.');

    expect(Cuti::where('karyawan_id', $this->karyawan->id)->where('status', 'pending')->exists())->toBeFalse();
});
