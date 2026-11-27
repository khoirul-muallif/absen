<?php

use App\Exports\LaporanBulananExport;
use App\Models\Izin;
use App\Models\Karyawan;

it('menghitung izin yang kembalinya lewat dari rencana', function () {
    $karyawan = Karyawan::factory()->create(['is_active' => true]);

    Izin::factory()->create([
        'karyawan_id'          => $karyawan->id,
        'tanggal'              => '2026-11-10',
        'jam_keluar'           => '10:00:00',
        'jam_kembali_rencana'  => '11:00:00',
        'jam_kembali_aktual'   => '11:45:00',
        'status'               => 'approved',
    ]);

    $baris = LaporanBulananExport::agregasi(11, 2026, null)->first();

    expect($baris['molor'])->toBe(1);
});

it('tidak menghitung izin yang kembali tepat waktu atau lebih cepat', function () {
    $karyawan = Karyawan::factory()->create(['is_active' => true]);

    // Pas di jam rencana — tanpa toleransi berarti ini BUKAN molor,
    // karena perbandingannya '>' bukan '>='.
    Izin::factory()->create([
        'karyawan_id'         => $karyawan->id,
        'tanggal'             => '2026-11-10',
        'jam_kembali_rencana' => '11:00:00',
        'jam_kembali_aktual'  => '11:00:00',
        'status'              => 'approved',
    ]);

    Izin::factory()->create([
        'karyawan_id'         => $karyawan->id,
        'tanggal'             => '2026-11-11',
        'jam_kembali_rencana' => '11:00:00',
        'jam_kembali_aktual'  => '10:30:00',
        'status'              => 'approved',
    ]);

    expect(LaporanBulananExport::agregasi(11, 2026, null)->first()['molor'])->toBe(0);
});

it('tidak menghitung izin yang karyawannya belum menandai kembali', function () {
    // jam_kembali_aktual null = belum kembali, bukan molor. Tanpa
    // whereNotNull, MySQL mengembalikan null untuk perbandingannya —
    // baris itu tidak ikut terhitung, tapi kebergantungan pada
    // perilaku null SQL tidak boleh jadi hal yang tidak disengaja.
    $karyawan = Karyawan::factory()->create(['is_active' => true]);

    Izin::factory()->create([
        'karyawan_id'         => $karyawan->id,
        'tanggal'             => '2026-11-10',
        'jam_kembali_rencana' => '11:00:00',
        'jam_kembali_aktual'  => null,
        'status'              => 'approved',
    ]);

    expect(LaporanBulananExport::agregasi(11, 2026, null)->first()['molor'])->toBe(0);
});

it('tidak menghitung izin yang belum di-approve', function () {
    // Konsisten dengan kolom Izin di sebelahnya, yang juga cuma
    // menghitung approved.
    $karyawan = Karyawan::factory()->create(['is_active' => true]);

    Izin::factory()->create([
        'karyawan_id'         => $karyawan->id,
        'tanggal'             => '2026-11-10',
        'jam_kembali_rencana' => '11:00:00',
        'jam_kembali_aktual'  => '12:00:00',
        'status'              => 'pending',
    ]);

    expect(LaporanBulananExport::agregasi(11, 2026, null)->first()['molor'])->toBe(0);
});
