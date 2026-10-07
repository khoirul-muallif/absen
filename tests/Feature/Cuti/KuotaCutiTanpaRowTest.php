<?php

use App\Exceptions\KuotaCutiTidakCukupException;
use App\Models\Cuti;
use App\Models\JenisCuti;
use App\Models\Karyawan;
use App\Models\KuotaCuti;
use App\Models\User;
use Carbon\Carbon;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-07 08:00:00'));

    $this->karyawan = Karyawan::factory()->umum()->create();
    $this->jenis = JenisCuti::factory()->create([
        'nama'          => 'Cuti Tahunan',
        'default_kuota' => 12,
        'potong_kuota'  => true,
        'is_active'     => true,
    ]);
    $this->admin = User::factory()->create();
});

function buatCutiPending(Karyawan $karyawan, JenisCuti $jenis, string $mulai, string $selesai, int $hari): Cuti
{
    return Cuti::create([
        'karyawan_id'     => $karyawan->id,
        'jenis_cuti_id'   => $jenis->id,
        'tanggal_mulai'   => $mulai,
        'tanggal_selesai' => $selesai,
        'jumlah_hari'     => $hari,
        'alasan'          => 'uji',
        'status'          => 'pending',
    ]);
}

it('approve tanpa row membuat row dengan terpakai sama dengan jumlah hari', function () {
    $cuti = buatCutiPending($this->karyawan, $this->jenis, '2026-10-12', '2026-10-14', 3);

    $cuti->approve($this->admin);

    $kuota = KuotaCuti::untuk($this->karyawan->id, $this->jenis->id, 2026);
    expect($kuota)->not->toBeNull()
        ->and($kuota->kuota)->toBe(12)
        ->and($kuota->terpakai)->toBe(3);
});

it('approve yang melewati default_kuota ditolak dan status tetap pending', function () {
    $cuti = buatCutiPending($this->karyawan, $this->jenis, '2026-10-12', '2026-10-24', 13);

    expect(fn () => $cuti->approve($this->admin))
        ->toThrow(KuotaCutiTidakCukupException::class);

    expect($cuti->fresh()->status)->toBe('pending')
        ->and(KuotaCuti::count())->toBe(0); // row ikut di-rollback
});

it('approve berurutan tidak bisa melewati kuota total', function () {
    $pertama = buatCutiPending($this->karyawan, $this->jenis, '2026-10-12', '2026-10-23', 12);
    $pertama->approve($this->admin);

    $kedua = buatCutiPending($this->karyawan, $this->jenis, '2026-11-02', '2026-11-02', 1);

    expect(fn () => $kedua->approve($this->admin))
        ->toThrow(KuotaCutiTidakCukupException::class);

    expect(KuotaCuti::untuk($this->karyawan->id, $this->jenis->id, 2026)->terpakai)->toBe(12);
});

it('membaca sisa kuota lewat API tidak membuat row', function () {
    Sanctum::actingAs($this->karyawan, ['*']);

    $this->getJson('/api/cuti/kuota')->assertOk();

    expect(KuotaCuti::count())->toBe(0);
});
