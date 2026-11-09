<?php

use App\Models\Instansi;
use App\Models\Jadwal;
use App\Models\Karyawan;
use App\Models\Shift;
use App\Models\TukarJadwal;
use App\Models\User;
use App\Notifications\TukarJadwalDiajukan;
use App\Notifications\TukarJadwalDibatalkanPengaju;
use App\Notifications\TukarJadwalDiproses;
use App\Notifications\TukarJadwalDiresponRekan;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    // Wajib dibekukan: endpoint ajukan() memakai isLewat() yang anchor ke
    // today(), jadi tanggal hardcode di bawah akan basi tanpa ini.
    $this->travelTo(\Carbon\Carbon::parse('2026-08-01 08:00:00'));

    Notification::fake();

    $this->instansi = Instansi::factory()->create();
    $this->admin = User::factory()->create();
    $this->shift = Shift::factory()->for($this->instansi)->create();

    $this->pengaju = Karyawan::factory()->for($this->instansi)->create();
    $this->rekan = Karyawan::factory()->for($this->instansi)->create();

    $this->jadwalPengaju = Jadwal::factory()
        ->for($this->pengaju)->for($this->shift)
        ->create(['tanggal' => '2026-08-10']);

    $this->jadwalRekan = Jadwal::factory()
        ->for($this->rekan)->for($this->shift)
        ->create(['tanggal' => '2026-08-11']);
});

function buatPengajuanTukar($test): TukarJadwal
{
    // status diisi eksplisit: factory default-nya 'menunggu_admin' (dipakai
    // test lama yang langsung approveAndSwap), sementara di sini kita justru
    // butuh tahap sebelumnya. Hook `creating` di model tidak menimpa nilai
    // yang sudah diisi.
    return TukarJadwal::factory()->create([
        'jadwal_id' => $test->jadwalPengaju->id,
        'jadwal_tujuan_id' => $test->jadwalRekan->id,
        'status' => 'menunggu_rekan',
    ]);
}

// ── ajukan() ─────────────────────────────────────────────────────────────

it('ajukan tukar: rekan tujuan dapat notifikasi', function () {
    Sanctum::actingAs($this->pengaju, ['*']);

    $this->postJson('/api/tukar-jadwal', [
        'mode' => 'tukar',
        'jadwal_id' => $this->jadwalPengaju->id,
        'jadwal_tujuan_id' => $this->jadwalRekan->id,
        'alasan' => 'Ada acara keluarga',
    ])->assertCreated();

    Notification::assertSentTo($this->rekan, TukarJadwalDiajukan::class);
    Notification::assertNotSentTo($this->pengaju, TukarJadwalDiajukan::class);
});

it('ajukan pindah: tidak mengirim notifikasi ke siapa pun (tidak ada rekan)', function () {
    Sanctum::actingAs($this->pengaju, ['*']);

    $this->postJson('/api/tukar-jadwal', [
        'mode' => 'pindah',
        'jadwal_id' => $this->jadwalPengaju->id,
        'tanggal_baru' => '2026-08-20',
        'alasan' => 'Tukar ke hari libur saya',
    ])->assertCreated();

    Notification::assertNothingSent();
});

// ── responRekan() ────────────────────────────────────────────────────────

it('rekan setuju: pengaju dapat notifikasi dengan setuju=true', function () {
    $tukar = buatPengajuanTukar($this);

    $tukar->responRekan($this->rekan, true);

    Notification::assertSentTo(
        $this->pengaju,
        TukarJadwalDiresponRekan::class,
        fn ($notification) => $notification->setuju === true
    );
});

it('rekan tolak: pengaju dapat notifikasi, catatan penolakan ikut di pesan', function () {
    $tukar = buatPengajuanTukar($this);

    $tukar->responRekan($this->rekan, false, 'Saya ada jadwal lain');

    Notification::assertSentTo(
        $this->pengaju,
        TukarJadwalDiresponRekan::class,
        function ($notification) {
            $data = $notification->toDatabase($this->pengaju);

            return $notification->setuju === false
                && str_contains($data['pesan'], 'Saya ada jadwal lain');
        }
    );
});

// ── approveAndSwap() ─────────────────────────────────────────────────────

it('admin approve tukar: pengaju DAN rekan sama-sama dapat notifikasi', function () {
    $tukar = buatPengajuanTukar($this);
    $tukar->responRekan($this->rekan, true);

    $tukar->approveAndSwap($this->admin);

    foreach ([$this->pengaju, $this->rekan] as $penerima) {
        Notification::assertSentTo(
            $penerima,
            TukarJadwalDiproses::class,
            fn ($notification) => $notification->hasil === TukarJadwalDiproses::HASIL_APPROVED
        );
    }
});

it('admin approve pindah: hanya pengaju yang dapat, tidak crash karena karyawan_tujuan_id null', function () {
    $tukar = TukarJadwal::factory()->create([
        'jadwal_id' => $this->jadwalPengaju->id,
        'tanggal_baru' => '2026-08-20',
    ]);

    $tukar->approveAndSwap($this->admin);

    Notification::assertSentTo($this->pengaju, TukarJadwalDiproses::class);
    Notification::assertNotSentTo($this->rekan, TukarJadwalDiproses::class);
});

it('approve GAGAL karena konflik: tidak ada notifikasi terkirim sama sekali', function () {
    $tukar = buatPengajuanTukar($this);
    $tukar->responRekan($this->rekan, true);

    // buang notifikasi dari responRekan di atas, yang diuji di sini cuma approve
    Notification::fake();

    // kepemilikan jadwal tujuan berubah setelah pengajuan dibuat
    $karyawanLain = Karyawan::factory()->for($this->instansi)->create();
    $this->jadwalRekan->update(['karyawan_id' => $karyawanLain->id]);

    expect(fn () => $tukar->approveAndSwap($this->admin))->toThrow(\Exception::class);

    Notification::assertNothingSent();
});

// ── reject() & batalkanOlehAdmin() ───────────────────────────────────────

it('admin tolak: kedua pihak dapat notifikasi dengan hasil rejected', function () {
    $tukar = buatPengajuanTukar($this);
    $tukar->responRekan($this->rekan, true);

    $tukar->reject($this->admin, 'Shift tidak seimbang');

    foreach ([$this->pengaju, $this->rekan] as $penerima) {
        Notification::assertSentTo(
            $penerima,
            TukarJadwalDiproses::class,
            fn ($notification) => $notification->hasil === TukarJadwalDiproses::HASIL_REJECTED
        );
    }
});

it('admin batalkan karena rekan tidak respons: kedua pihak dapat notifikasi', function () {
    $tukar = buatPengajuanTukar($this);

    $tukar->batalkanOlehAdmin($this->admin, 'Rekan sedang cuti panjang');

    foreach ([$this->pengaju, $this->rekan] as $penerima) {
        Notification::assertSentTo(
            $penerima,
            TukarJadwalDiproses::class,
            fn ($notification) => $notification->hasil === TukarJadwalDiproses::HASIL_DIBATALKAN_ADMIN
        );
    }
});

// ── kontrak data untuk FE ────────────────────────────────────────────────

it('ketiga notifikasi memakai tipe "tukar_jadwal" dan membawa tukar_jadwal_id', function () {
    $tukar = buatPengajuanTukar($this);

    $notifikasi = [
        new TukarJadwalDiajukan($tukar),
        new TukarJadwalDiresponRekan($tukar, true),
        new TukarJadwalDiproses($tukar, TukarJadwalDiproses::HASIL_APPROVED),
    ];

    foreach ($notifikasi as $n) {
        $data = $n->toDatabase($this->pengaju);

        expect($data['tipe'])->toBe('tukar_jadwal')
            ->and($data['tukar_jadwal_id'])->toBe($tukar->id)
            ->and($data['judul'])->not->toBeEmpty()
            ->and($data['pesan'])->not->toBeEmpty();
    }
});
// ── batalkan() oleh pengaju ──────────────────────────────────────────────

it('pengaju batalkan saat menunggu_rekan: rekan tujuan diberi tahu', function () {
    $tukar = buatPengajuanTukar($this);

    Sanctum::actingAs($this->pengaju, ['*']);

    $this->deleteJson("/api/tukar-jadwal/{$tukar->id}")->assertOk();

    Notification::assertSentTo(
        $this->rekan,
        TukarJadwalDibatalkanPengaju::class,
        fn ($n) => $n->statusSaatDibatalkan === 'menunggu_rekan'
    );
    Notification::assertNotSentTo($this->pengaju, TukarJadwalDibatalkanPengaju::class);
});

it('pengaju batalkan setelah rekan setuju: pesannya menyebut rekan sudah menyetujui', function () {
    $tukar = buatPengajuanTukar($this);
    $tukar->responRekan($this->rekan, true);

    Sanctum::actingAs($this->pengaju, ['*']);

    $this->deleteJson("/api/tukar-jadwal/{$tukar->id}")->assertOk();

    Notification::assertSentTo(
        $this->rekan,
        TukarJadwalDibatalkanPengaju::class,
        function ($n) {
            $data = $n->toDatabase($this->rekan);

            return $n->statusSaatDibatalkan === 'menunggu_admin'
                && str_contains($data['pesan'], 'sudah menyetujui');
        }
    );
});

it('pengaju batalkan mode pindah: tidak ada notifikasi (tidak ada rekan)', function () {
    $tukar = TukarJadwal::factory()->create([
        'jadwal_id' => $this->jadwalPengaju->id,
        'tanggal_baru' => '2026-08-20',
    ]);

    Sanctum::actingAs($this->pengaju, ['*']);

    $this->deleteJson("/api/tukar-jadwal/{$tukar->id}")->assertOk();

    Notification::assertNothingSent();
});

it('notifikasi pembatalan tetap punya label jadwal meski barisnya sudah dihapus', function () {
    $tukar = buatPengajuanTukar($this);

    Sanctum::actingAs($this->pengaju, ['*']);

    $this->deleteJson("/api/tukar-jadwal/{$tukar->id}")->assertOk();

    expect(TukarJadwal::find($tukar->id))->toBeNull();

    Notification::assertSentTo(
        $this->rekan,
        TukarJadwalDibatalkanPengaju::class,
        function ($n) {
            $data = $n->toDatabase($this->rekan);

            return $data['tipe'] === 'tukar_jadwal'
                && $data['aksi'] === 'dibatalkan_pengaju'
                && str_contains($data['pesan'], '10 Aug 2026');
        }
    );
});
