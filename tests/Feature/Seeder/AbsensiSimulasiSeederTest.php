<?php

use App\Models\Absensi;
use App\Models\Instansi;
use App\Models\Karyawan;
use App\Models\QrInstansi;
use App\Models\Shift;
use Carbon\Carbon;
use Database\Seeders\AbsensiSimulasiSeeder;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-07 08:00:00'));

    $this->instansi = Instansi::factory()->create();
    $this->karyawan = Karyawan::factory()->umum()->create([
        'instansi_id' => $this->instansi->id,
        'email'       => 'budi@rsb.com',
    ]);
    $this->shift = Shift::factory()->create([
        'instansi_id' => $this->instansi->id,
        'nama_shift'  => 'umum',
        'jam_masuk'   => '07:30:00',
        'jam_pulang'  => '14:30:00',
        'hari_kerja'  => [1, 2, 3, 4, 5],
    ]);
    QrInstansi::factory()->create(['instansi_id' => $this->instansi->id]);
});

it('waktu_masuk simulasi selalu di tanggal yang sama dengan baris absensi', function () {
    $this->seed(AbsensiSimulasiSeeder::class);

    $baris = Absensi::where('karyawan_id', $this->karyawan->id)->get();

    expect($baris)->not->toBeEmpty();

    foreach ($baris as $absensi) {
        expect($absensi->waktu_masuk->toDateString())->toBe($absensi->tanggal->toDateString());
    }
});

it('tidak menulis absensi di hari libur mingguan shift', function () {
    $this->seed(AbsensiSimulasiSeeder::class);

    $akhirPekan = Absensi::where('karyawan_id', $this->karyawan->id)
        ->get()
        ->filter(fn ($a) => in_array($a->tanggal->dayOfWeekIso, [6, 7]));

    expect($akhirPekan)->toBeEmpty();
});
it('akumulasi di-reset saat hari simulasi melewati awal bulan', function () {
    // Seeder mundur 5 hari dari hari ini; pada tanggal 2 sebagian jatuh di bulan lalu.
    $this->travelTo(Carbon::parse('2026-10-02 08:00:00'));

    $this->seed(AbsensiSimulasiSeeder::class);

    $bulanLalu = Absensi::where('karyawan_id', $this->karyawan->id)
        ->whereMonth('tanggal', 9)
        ->get();

    // Akumulasi bulan lalu tidak boleh membawa ke bulan Oktober.
    // Di bulan Oktober, akumulasi hanya dari tanggal 1-2 yang masuk simulasi.
    $oktober = Absensi::where('karyawan_id', $this->karyawan->id)
        ->whereMonth('tanggal', 10)
        ->orderBy('tanggal')
        ->get();

    expect($oktober->first()->menit_terlambat)->toBeLessThan(10)
        ->and($oktober->every(fn ($a) => $a->melebihi_toleransi_bulanan === false))->toBeTrue();
});
