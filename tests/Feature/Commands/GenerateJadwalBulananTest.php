<?php

use App\Models\Cuti;
use App\Models\HariLibur;
use App\Models\Instansi;
use App\Models\Jadwal;
use App\Models\Karyawan;
use App\Models\KaryawanShift;
use App\Models\Shift;
use Illuminate\Support\Facades\Artisan;

function jalankanGeneratorBulanan(int $bulan, int $tahun, array $options = []): void
{
    Artisan::call('jadwal:generate-bulanan', array_merge(['--bulan' => $bulan, '--tahun' => $tahun], $options));
}

beforeEach(function () {
    $instansi = Instansi::factory()->create();
    $this->instansi = $instansi;});

test('generate jadwal reguler untuk hari kerja sesuai KaryawanShift', function () {
    $shift = Shift::factory()->create(['instansi_id' => $this->instansi->id, 'hari_kerja' => []]);
    $karyawan = Karyawan::factory()->umum()->create(['instansi_id' => $this->instansi->id]);
    KaryawanShift::factory()->openEnded()->create([
        'karyawan_id' => $karyawan->id,
        'shift_id' => $shift->id,
        'tanggal_berlaku' => '2026-07-01',
    ]);

    jalankanGeneratorBulanan(8, 2026);

    $jadwal = Jadwal::where('karyawan_id', $karyawan->id)->whereDate('tanggal', '2026-08-03')->first();
    expect($jadwal)->not->toBeNull()
        ->and($jadwal->jenis)->toBe('reguler')
        ->and($jadwal->shift_id)->toBe($shift->id)
        ->and($jadwal->sumber ?? 'generate')->not->toBeNull();

    expect(Jadwal::where('karyawan_id', $karyawan->id)->count())->toBe(31);
});

test('tidak menimpa Jadwal yang sudah ada di tanggal tersebut', function () {
    $shift = Shift::factory()->create(['instansi_id' => $this->instansi->id, 'hari_kerja' => []]);
    $karyawan = Karyawan::factory()->umum()->create(['instansi_id' => $this->instansi->id]);
    KaryawanShift::factory()->openEnded()->create([
        'karyawan_id' => $karyawan->id,
        'shift_id' => $shift->id,
        'tanggal_berlaku' => '2026-07-01',
    ]);

    Jadwal::create([
        'karyawan_id' => $karyawan->id,
        'tanggal' => '2026-08-05',
        'shift_id' => null,
        'jenis' => 'piket',
        'sumber' => 'manual',
    ]);

    jalankanGeneratorBulanan(8, 2026);

    $jadwal = Jadwal::where('karyawan_id', $karyawan->id)->whereDate('tanggal', '2026-08-05')->first();
    expect($jadwal->jenis)->toBe('piket')->and($jadwal->sumber)->toBe('manual');
});

test('skip tanggal yang bukan hari kerja pola shift (libur mingguan)', function () {
    $shift = Shift::factory()->create([
        'instansi_id' => $this->instansi->id,
        'hari_kerja' => [1, 2, 3, 4, 5], // Senin-Jumat
    ]);
    $karyawan = Karyawan::factory()->umum()->create(['instansi_id' => $this->instansi->id]);
    KaryawanShift::factory()->openEnded()->create([
        'karyawan_id' => $karyawan->id,
        'shift_id' => $shift->id,
        'tanggal_berlaku' => '2026-07-01',
    ]);

    jalankanGeneratorBulanan(8, 2026);

    // 2026-08-01 & 2026-08-02 = Sabtu & Minggu
    expect(Jadwal::where('karyawan_id', $karyawan->id)->whereDate('tanggal', '2026-08-01')->exists())->toBeFalse()
        ->and(Jadwal::where('karyawan_id', $karyawan->id)->whereDate('tanggal', '2026-08-02')->exists())->toBeFalse();
});

test('skip tanggal yang bentrok dengan Cuti approved', function () {
    $shift = Shift::factory()->create(['instansi_id' => $this->instansi->id, 'hari_kerja' => []]);
    $karyawan = Karyawan::factory()->umum()->create(['instansi_id' => $this->instansi->id]);
    KaryawanShift::factory()->openEnded()->create([
        'karyawan_id' => $karyawan->id,
        'shift_id' => $shift->id,
        'tanggal_berlaku' => '2026-07-01',
    ]);

    Cuti::factory()->create([
        'karyawan_id' => $karyawan->id,
        'status' => 'approved',
        'tanggal_mulai' => '2026-08-10',
        'tanggal_selesai' => '2026-08-12',
    ]);

    jalankanGeneratorBulanan(8, 2026);

    expect(Jadwal::where('karyawan_id', $karyawan->id)->whereDate('tanggal', '2026-08-11')->exists())->toBeFalse();
});

test('hari libur nasional tetap digenerate eksplisit sebagai jenis libur', function () {
    $shift = Shift::factory()->create(['instansi_id' => $this->instansi->id, 'hari_kerja' => []]);
    $karyawan = Karyawan::factory()->umum()->create(['instansi_id' => $this->instansi->id]);
    KaryawanShift::factory()->openEnded()->create([
        'karyawan_id' => $karyawan->id,
        'shift_id' => $shift->id,
        'tanggal_berlaku' => '2026-07-01',
    ]);
    HariLibur::create([
        'instansi_id' => $this->instansi->id,
        'tanggal' => '2026-08-17',
        'nama' => 'HUT RI',
        'is_cuti_bersama' => false,
    ]);

    jalankanGeneratorBulanan(8, 2026);

    $jadwal = Jadwal::where('karyawan_id', $karyawan->id)->whereDate('tanggal', '2026-08-17')->first();
    expect($jadwal->jenis)->toBe('libur')->and($jadwal->shift_id)->toBeNull();
});

test('--dry-run tidak menyimpan apapun ke database', function () {
    $shift = Shift::factory()->create(['instansi_id' => $this->instansi->id, 'hari_kerja' => []]);
    $karyawan = Karyawan::factory()->umum()->create(['instansi_id' => $this->instansi->id]);
    KaryawanShift::factory()->openEnded()->create([
        'karyawan_id' => $karyawan->id,
        'shift_id' => $shift->id,
        'tanggal_berlaku' => '2026-07-01',
    ]);

    jalankanGeneratorBulanan(8, 2026, ['--dry-run' => true]);

    expect(Jadwal::where('karyawan_id', $karyawan->id)->count())->toBe(0);
});

test('karyawan tipe rotasi yang salah punya KaryawanShift dilewati (guard pengaman)', function () {
    $shift = Shift::factory()->create(['instansi_id' => $this->instansi->id, 'hari_kerja' => []]);
    $karyawan = Karyawan::factory()->rotasi()->create(['instansi_id' => $this->instansi->id]);
    KaryawanShift::factory()->openEnded()->create([
        'karyawan_id' => $karyawan->id,
        'shift_id' => $shift->id,
        'tanggal_berlaku' => '2026-07-01',
    ]);

    jalankanGeneratorBulanan(8, 2026);

    expect(Jadwal::where('karyawan_id', $karyawan->id)->exists())->toBeFalse();
});
