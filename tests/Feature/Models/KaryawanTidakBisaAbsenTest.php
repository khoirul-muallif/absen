<?php

use App\Models\Instansi;
use App\Models\Jadwal;
use App\Models\Karyawan;
use App\Models\KaryawanShift;
use App\Models\Shift;
use Carbon\Carbon;

/**
 * Scope ini adalah SALINAN kondisi langkah 5 AbsensiController::masuk().
 * Test di sini mengunci salinannya, bukan aslinya — kalau controller
 * berubah dan scope tidak, test ini tetap hijau sementara filter di panel
 * mulai berbohong. Lihat catatan di akhir file.
 */
beforeEach(function () {
    // Tanggal dibekukan: scope default-nya today(), dan seluruh test ini
    // membandingkan rentang tanggal. Tanpa travelTo, test yang kebetulan
    // hijau hari ini bisa merah di tanggal lain (lihat QUICK_CONTEXT).
    $this->travelTo(Carbon::parse('2026-06-15 09:00:00'));

    $this->instansi = Instansi::factory()->create();
    $this->shift = Shift::factory()->create();
});

function karyawanUmum(Instansi $instansi): Karyawan
{
    return Karyawan::factory()->create([
        'instansi_id' => $instansi->id,
        'tipe_jadwal' => Karyawan::TIPE_UMUM,
        'is_active'   => true,
    ]);
}

function karyawanRotasi(Instansi $instansi): Karyawan
{
    return Karyawan::factory()->create([
        'instansi_id' => $instansi->id,
        'tipe_jadwal' => Karyawan::TIPE_ROTASI,
        'unit_kerja'  => 'IGD',
        'is_active'   => true,
    ]);
}

it('tidak menandai karyawan umum yang KaryawanShift-nya sedang berlaku', function () {
    $karyawan = karyawanUmum($this->instansi);

    KaryawanShift::create([
        'karyawan_id'      => $karyawan->id,
        'shift_id'         => $this->shift->id,
        'tanggal_berlaku'  => '2026-06-01',
        'tanggal_berakhir' => null,
    ]);

    expect(Karyawan::tidakBisaAbsenPada()->pluck('id'))
        ->not->toContain($karyawan->id);
});

it('menandai karyawan umum yang KaryawanShift-nya sudah berakhir', function () {
    // Known Gap #4: assignment seeder dibatasi per bulan. Begitu
    // tanggal_berakhir lewat dan tidak diperpanjang, orang ini jatuh ke
    // cabang Jadwal — dan kalau generator bulanan juga belum jalan, dia
    // tidak bisa absen tanpa ada yang tahu.
    $karyawan = karyawanUmum($this->instansi);

    KaryawanShift::create([
        'karyawan_id'      => $karyawan->id,
        'shift_id'         => $this->shift->id,
        'tanggal_berlaku'  => '2026-05-01',
        'tanggal_berakhir' => '2026-05-31',
    ]);

    expect(Karyawan::tidakBisaAbsenPada()->pluck('id'))
        ->toContain($karyawan->id);
});

it('menandai karyawan umum yang KaryawanShift-nya baru berlaku besok', function () {
    $karyawan = karyawanUmum($this->instansi);

    KaryawanShift::create([
        'karyawan_id'      => $karyawan->id,
        'shift_id'         => $this->shift->id,
        'tanggal_berlaku'  => '2026-06-16',
        'tanggal_berakhir' => null,
    ]);

    expect(Karyawan::tidakBisaAbsenPada()->pluck('id'))
        ->toContain($karyawan->id);
});

it('menandai karyawan rotasi yang polanya sudah di-assign tapi jadwalnya belum digenerate', function () {
    // INI kasus yang bikin seluruh fase ini ada: berhenti di langkah 2
    // dari tiga prasyarat deploy. Dia punya baris karyawan_pola_rotasis,
    // tapi scope sengaja TIDAK melihat tabel itu — yang menentukan bisa
    // absen atau tidak cuma KaryawanShift dan Jadwal.
    $karyawan = karyawanRotasi($this->instansi);

    expect(Karyawan::tidakBisaAbsenPada()->pluck('id'))
        ->toContain($karyawan->id);
});

it('tidak menandai karyawan rotasi yang punya jadwal reguler hari ini', function () {
    $karyawan = karyawanRotasi($this->instansi);

    Jadwal::factory()->create([
        'karyawan_id' => $karyawan->id,
        'shift_id'    => $this->shift->id,
        'tanggal'     => '2026-06-15',
        'jenis'       => Jadwal::JENIS_REGULER,
    ]);

    expect(Karyawan::tidakBisaAbsenPada()->pluck('id'))
        ->not->toContain($karyawan->id);
});

it('tidak menandai karyawan rotasi yang hari ini kebetulan libur', function () {
    // Dia memang tidak absen hari ini, tapi bukan karena ada yang salah —
    // controller memberinya pesan sendiri ("jadwal Anda libur"), bukan
    // pesan ambigu "tidak ada shift aktif". Kalau orang ini ikut muncul,
    // filternya jadi berisik justru di kasus paling normal.
    $karyawan = karyawanRotasi($this->instansi);

    Jadwal::factory()->create([
        'karyawan_id' => $karyawan->id,
        'shift_id'    => null,
        'tanggal'     => '2026-06-15',
        'jenis'       => Jadwal::JENIS_LIBUR,
    ]);

    expect(Karyawan::tidakBisaAbsenPada()->pluck('id'))
        ->not->toContain($karyawan->id);
});

it('tidak menandai karyawan nonaktif', function () {
    $karyawan = Karyawan::factory()->create([
        'instansi_id' => $this->instansi->id,
        'tipe_jadwal' => Karyawan::TIPE_UMUM,
        'is_active'   => false,
    ]);

    expect(Karyawan::tidakBisaAbsenPada()->pluck('id'))
        ->not->toContain($karyawan->id);
});

it('menerima tanggal eksplisit, bukan cuma hari ini', function () {
    // Dipakai kalau nanti admin ingin memeriksa kesiapan bulan depan
    // sebelum bulannya tiba.
    $karyawan = karyawanUmum($this->instansi);

    KaryawanShift::create([
        'karyawan_id'      => $karyawan->id,
        'shift_id'         => $this->shift->id,
        'tanggal_berlaku'  => '2026-06-01',
        'tanggal_berakhir' => '2026-06-30',
    ]);

    expect(Karyawan::tidakBisaAbsenPada()->pluck('id'))
        ->not->toContain($karyawan->id);

    expect(Karyawan::tidakBisaAbsenPada(Carbon::parse('2026-07-01'))->pluck('id'))
        ->toContain($karyawan->id);
});
