<?php

use App\Models\HariLibur;
use App\Models\Instansi;
use App\Models\Jadwal;
use App\Models\Karyawan;
use App\Models\KaryawanPolaRotasi;
use App\Models\PolaRotasi;
use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;

function jalankanGenerator(int $bulan, int $tahun, array $options = []): void
{
    Artisan::call('jadwal:generate-rotasi', array_merge(['bulan' => $bulan, 'tahun' => $tahun], $options));
}

test('generate jadwal rotasi sesuai pola untuk sebulan penuh', function () {
    $instansi = Instansi::factory()->create();
    $shiftPagi = Shift::factory()->create(['instansi_id' => $instansi->id]);
    $shiftMalam = Shift::factory()->create(['instansi_id' => $instansi->id]);

    $pola = PolaRotasi::factory()->create([
        'instansi_id' => $instansi->id,
        'langkah' => [
            ['shift_id' => $shiftPagi->id, 'libur' => false],
            ['shift_id' => $shiftMalam->id, 'libur' => false],
            ['shift_id' => null, 'libur' => true],
        ],
    ]);
    $karyawan = Karyawan::factory()->rotasi()->create(['instansi_id' => $instansi->id]);
    KaryawanPolaRotasi::factory()->create([
        'karyawan_id' => $karyawan->id,
        'pola_rotasi_id' => $pola->id,
        'tanggal_mulai' => Carbon::create(2026, 8, 1),
    ]);

    jalankanGenerator(8, 2026);

    $ambil = fn ($tgl) => Jadwal::where('karyawan_id', $karyawan->id)->whereDate('tanggal', $tgl)->first();

    expect($ambil('2026-08-01')->shift_id)->toBe($shiftPagi->id)
        ->and($ambil('2026-08-02')->shift_id)->toBe($shiftMalam->id)
        ->and($ambil('2026-08-03')->jenis)->toBe('libur')
        ->and($ambil('2026-08-03')->shift_id)->toBeNull()
        ->and($ambil('2026-08-04')->shift_id)->toBe($shiftPagi->id); // wrap siklus

    expect(Jadwal::where('karyawan_id', $karyawan->id)->count())->toBe(31);
});

test('tidak menimpa jadwal yang sudah manual', function () {
    $instansi = Instansi::factory()->create();
    $shift = Shift::factory()->create(['instansi_id' => $instansi->id]);
    $pola = PolaRotasi::factory()->create(['instansi_id' => $instansi->id, 'langkah' => [['shift_id' => $shift->id, 'libur' => false]]]);
    $karyawan = Karyawan::factory()->rotasi()->create(['instansi_id' => $instansi->id]);
    KaryawanPolaRotasi::factory()->create(['karyawan_id' => $karyawan->id, 'pola_rotasi_id' => $pola->id, 'tanggal_mulai' => Carbon::create(2026, 8, 1)]);

    Jadwal::create(['karyawan_id' => $karyawan->id, 'tanggal' => '2026-08-05', 'shift_id' => null, 'jenis' => 'libur', 'sumber' => 'manual']);

    jalankanGenerator(8, 2026);

    $jadwal = Jadwal::where('karyawan_id', $karyawan->id)->whereDate('tanggal', '2026-08-05')->first();
    expect($jadwal->sumber)->toBe('manual')->and($jadwal->jenis)->toBe('libur');
});

test('--overwrite-generate menimpa baris generate lama tapi bukan manual', function () {
    $instansi = Instansi::factory()->create();
    $shiftLama = Shift::factory()->create(['instansi_id' => $instansi->id]);
    $shiftBaru = Shift::factory()->create(['instansi_id' => $instansi->id]);
    $pola = PolaRotasi::factory()->create(['instansi_id' => $instansi->id, 'langkah' => [['shift_id' => $shiftBaru->id, 'libur' => false]]]);
    $karyawan = Karyawan::factory()->rotasi()->create(['instansi_id' => $instansi->id]);
    KaryawanPolaRotasi::factory()->create(['karyawan_id' => $karyawan->id, 'pola_rotasi_id' => $pola->id, 'tanggal_mulai' => Carbon::create(2026, 8, 1)]);

    Jadwal::create(['karyawan_id' => $karyawan->id, 'tanggal' => '2026-08-10', 'shift_id' => $shiftLama->id, 'jenis' => 'piket', 'sumber' => 'generate']);
    Jadwal::create(['karyawan_id' => $karyawan->id, 'tanggal' => '2026-08-11', 'shift_id' => $shiftLama->id, 'jenis' => 'piket', 'sumber' => 'manual']);

    jalankanGenerator(8, 2026, ['--overwrite-generate' => true]);

    expect(Jadwal::where('karyawan_id', $karyawan->id)->whereDate('tanggal', '2026-08-10')->first()->shift_id)->toBe($shiftBaru->id)
        ->and(Jadwal::where('karyawan_id', $karyawan->id)->whereDate('tanggal', '2026-08-11')->first()->shift_id)->toBe($shiftLama->id);
});

test('hari libur nasional override jadi libur kalau pola tidak berlaku saat libur nasional', function () {
    $instansi = Instansi::factory()->create();
    $shift = Shift::factory()->create(['instansi_id' => $instansi->id]);
    $pola = PolaRotasi::factory()->create(['instansi_id' => $instansi->id, 'berlaku_saat_libur_nasional' => false, 'langkah' => [['shift_id' => $shift->id, 'libur' => false]]]);
    $karyawan = Karyawan::factory()->rotasi()->create(['instansi_id' => $instansi->id]);
    KaryawanPolaRotasi::factory()->create(['karyawan_id' => $karyawan->id, 'pola_rotasi_id' => $pola->id, 'tanggal_mulai' => Carbon::create(2026, 8, 1)]);

    HariLibur::create(['instansi_id' => $instansi->id, 'tanggal' => '2026-08-17', 'nama' => 'HUT RI', 'is_cuti_bersama' => false]);

    jalankanGenerator(8, 2026);

    $jadwal = Jadwal::where('karyawan_id', $karyawan->id)->whereDate('tanggal', '2026-08-17')->first();
    expect($jadwal->jenis)->toBe('libur')->and($jadwal->shift_id)->toBeNull();
});

test('unit 24 jam tetap masuk pas libur nasional kalau berlaku_saat_libur_nasional true', function () {
    $instansi = Instansi::factory()->create();
    $shift = Shift::factory()->create(['instansi_id' => $instansi->id]);
    $pola = PolaRotasi::factory()->create(['instansi_id' => $instansi->id, 'berlaku_saat_libur_nasional' => true, 'langkah' => [['shift_id' => $shift->id, 'libur' => false]]]);
    $karyawan = Karyawan::factory()->rotasi()->create(['instansi_id' => $instansi->id]);
    KaryawanPolaRotasi::factory()->create(['karyawan_id' => $karyawan->id, 'pola_rotasi_id' => $pola->id, 'tanggal_mulai' => Carbon::create(2026, 8, 1)]);

    HariLibur::create(['instansi_id' => $instansi->id, 'tanggal' => '2026-08-17', 'nama' => 'HUT RI', 'is_cuti_bersama' => false]);

    jalankanGenerator(8, 2026);

    $jadwal = Jadwal::where('karyawan_id', $karyawan->id)->whereDate('tanggal', '2026-08-17')->first();
    expect($jadwal->jenis)->toBe('piket')->and($jadwal->shift_id)->toBe($shift->id);
});

test('dua karyawan staggered di pola sama dapat shift berbeda di tanggal sama', function () {
    $instansi = Instansi::factory()->create();
    $shiftPagi = Shift::factory()->create(['instansi_id' => $instansi->id]);
    $shiftMalam = Shift::factory()->create(['instansi_id' => $instansi->id]);
    $pola = PolaRotasi::factory()->create([
        'instansi_id' => $instansi->id,
        'langkah' => [['shift_id' => $shiftPagi->id, 'libur' => false], ['shift_id' => $shiftMalam->id, 'libur' => false]],
    ]);

    $karyawanA = Karyawan::factory()->rotasi()->create(['instansi_id' => $instansi->id]);
    $karyawanB = Karyawan::factory()->rotasi()->create(['instansi_id' => $instansi->id]);

    KaryawanPolaRotasi::factory()->create(['karyawan_id' => $karyawanA->id, 'pola_rotasi_id' => $pola->id, 'tanggal_mulai' => Carbon::create(2026, 8, 1)]);
    KaryawanPolaRotasi::factory()->create(['karyawan_id' => $karyawanB->id, 'pola_rotasi_id' => $pola->id, 'tanggal_mulai' => Carbon::create(2026, 8, 2)]);

    jalankanGenerator(8, 2026);

    $shiftA = Jadwal::where('karyawan_id', $karyawanA->id)->whereDate('tanggal', '2026-08-05')->first()->shift_id;
    $shiftB = Jadwal::where('karyawan_id', $karyawanB->id)->whereDate('tanggal', '2026-08-05')->first()->shift_id;

    expect($shiftA)->not->toBe($shiftB);
});
