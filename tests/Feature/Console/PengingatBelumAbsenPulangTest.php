<?php

use App\Models\Absensi;
use App\Models\Instansi;
use App\Models\Karyawan;
use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    $this->instansi = Instansi::factory()->create();

    $this->karyawan = Karyawan::factory()->create([
        'instansi_id' => $this->instansi->id,
        'is_active'   => true,
    ]);

    $this->shiftPagi = Shift::factory()->create([
        'instansi_id'     => $this->instansi->id,
        'nama_shift'      => 'Pagi',
        'jam_masuk'       => '07:00:00',
        'jam_pulang'      => '14:00:00',
        'toleransi_menit' => 15,
    ]);

    $this->shiftMalam = Shift::factory()->create([
        'instansi_id'     => $this->instansi->id,
        'nama_shift'      => 'Malam',
        'jam_masuk'       => '22:00:00',
        'jam_pulang'      => '07:00:00',
        'toleransi_menit' => 15,
    ]);
});

function absensiTanpaPulang(Karyawan $karyawan, Shift $shift, string $tanggal, string $masuk): Absensi
{
    return Absensi::factory()->create([
        'karyawan_id'  => $karyawan->id,
        'shift_id'     => $shift->id,
        'tanggal'      => $tanggal,
        'waktu_masuk'  => Carbon::parse("{$tanggal} {$masuk}"),
        'waktu_pulang' => null,
    ]);
}

it('belum mengirim sebelum deadline pulang + 15 menit', function () {
    absensiTanpaPulang($this->karyawan, $this->shiftPagi, '2026-10-06', '07:02:00');

    $this->travelTo(Carbon::parse('2026-10-06 14:14:00'));
    Artisan::call('absensi:pengingat-belum-pulang');

    expect($this->karyawan->notifications()->count())->toBe(0);
});

it('mengirim tepat setelah deadline pulang + 15 menit', function () {
    absensiTanpaPulang($this->karyawan, $this->shiftPagi, '2026-10-06', '07:02:00');

    $this->travelTo(Carbon::parse('2026-10-06 14:16:00'));
    Artisan::call('absensi:pengingat-belum-pulang');

    $notif = $this->karyawan->notifications()->first();
    expect($this->karyawan->notifications()->count())->toBe(1)
        ->and($notif->data['tipe'])->toBe('belum_absen_pulang')
        ->and($notif->data['pesan'])->toContain('Pulang: 14:00')
        ->and($notif->data['pesan'])->not->toContain('{');
});

it('shift malam: deadline jatuh di hari berikutnya', function () {
    absensiTanpaPulang($this->karyawan, $this->shiftMalam, '2026-10-06', '21:55:00');

    // Masih di tanggal T, belum lewat jam pulang T+1
    $this->travelTo(Carbon::parse('2026-10-06 23:30:00'));
    Artisan::call('absensi:pengingat-belum-pulang');
    expect($this->karyawan->notifications()->count())->toBe(0);

    // T+1 jam 07:10, masih dalam 15 menit toleransi
    $this->travelTo(Carbon::parse('2026-10-07 07:10:00'));
    Artisan::call('absensi:pengingat-belum-pulang');
    expect($this->karyawan->notifications()->count())->toBe(0);

    // T+1 jam 07:20, sudah lewat
    $this->travelTo(Carbon::parse('2026-10-07 07:20:00'));
    Artisan::call('absensi:pengingat-belum-pulang');
    expect($this->karyawan->notifications()->count())->toBe(1);
});

it('dua absensi berbeda di hari yang sama mendapat notifikasi masing-masing', function () {
    $malam = absensiTanpaPulang($this->karyawan, $this->shiftMalam, '2026-10-06', '21:55:00');
    $pagi  = absensiTanpaPulang($this->karyawan, $this->shiftPagi, '2026-10-07', '07:02:00');

    $this->travelTo(Carbon::parse('2026-10-07 20:00:00'));
    Artisan::call('absensi:pengingat-belum-pulang');

    $absensiIds = $this->karyawan->notifications()
        ->get()
        ->map(fn ($n) => $n->data['absensi_id'])
        ->sort()
        ->values()
        ->all();

    expect($absensiIds)->toBe([$malam->id, $pagi->id]);
});

it('tidak mengirim ulang untuk absensi yang sama saat command dijalankan dua kali', function () {
    absensiTanpaPulang($this->karyawan, $this->shiftPagi, '2026-10-06', '07:02:00');

    $this->travelTo(Carbon::parse('2026-10-06 14:30:00'));
    Artisan::call('absensi:pengingat-belum-pulang');
    Artisan::call('absensi:pengingat-belum-pulang');

    expect($this->karyawan->notifications()->count())->toBe(1);
});

it('karyawan yang sudah absen pulang tidak mendapat notifikasi', function () {
    $absensi = absensiTanpaPulang($this->karyawan, $this->shiftPagi, '2026-10-06', '07:02:00');
    $absensi->update(['waktu_pulang' => Carbon::parse('2026-10-06 14:05:00')]);

    $this->travelTo(Carbon::parse('2026-10-06 16:00:00'));
    Artisan::call('absensi:pengingat-belum-pulang');

    expect($this->karyawan->notifications()->count())->toBe(0);
});

it('baris tanpa waktu_masuk (cuti, alpha) tidak ditagih absen pulang', function () {
    Absensi::factory()->create([
        'karyawan_id' => $this->karyawan->id,
        'shift_id'    => $this->shiftPagi->id,
        'tanggal'     => '2026-10-06',
        'status'      => 'cuti',
        'waktu_masuk' => null,
    ]);

    $this->travelTo(Carbon::parse('2026-10-06 16:00:00'));
    Artisan::call('absensi:pengingat-belum-pulang');

    expect($this->karyawan->notifications()->count())->toBe(0);
});

it('karyawan nonaktif tidak mendapat notifikasi', function () {
    absensiTanpaPulang($this->karyawan, $this->shiftPagi, '2026-10-06', '07:02:00');
    $this->karyawan->update(['is_active' => false]);

    $this->travelTo(Carbon::parse('2026-10-06 16:00:00'));
    Artisan::call('absensi:pengingat-belum-pulang');

    expect($this->karyawan->notifications()->count())->toBe(0);
});
