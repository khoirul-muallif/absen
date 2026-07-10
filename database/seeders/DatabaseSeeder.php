<?php

namespace Database\Seeders;

use App\Models\Instansi;
use App\Models\Karyawan;
use App\Models\QrInstansi;
use App\Models\Shift;
use App\Models\JenisCuti;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Instansi
        $instansi = Instansi::create([
            'nama' => 'RSU Banyumanik 2',
            'kode_instansi' => 'RSUB2',
            'latitude' => -7.0784947,
            'longitude' => 110.4119292,
            'radius_meter' => 100,
            'alamat' => 'Jl. Perintis Kemerdekaan No.57, Banyumanik, Kec. Banyumanik, Kota Semarang, Jawa Tengah 50265',
            'telepon' => '024-7466525',
            'is_active' => true,
        ]);

        // QR Instansi
        QrInstansi::create([
            'instansi_id' => $instansi->id,
            'kode_qr' => strtoupper(Str::random(32)),
            'is_active' => true,
        ]);

        // Shift
        $shiftUmum = Shift::create([
            'instansi_id' => $instansi->id,
            'nama_shift' => 'umum',
            'jam_masuk' => '07:30:00',
            'jam_pulang' => '16:00:00',
            'toleransi_menit' => 30,
            'is_active' => true,
        ]);

        Shift::create([
            'instansi_id' => $instansi->id,
            'nama_shift' => 'pagi',
            'jam_masuk' => '07:00:00',
            'jam_pulang' => '12:00:00',
            'toleransi_menit' => 30,
            'is_active' => true,
        ]);

        // Karyawan test
        $karyawan = Karyawan::create([
            'instansi_id' => $instansi->id,
            'nip' => '198501012026011001',
            'nama' => 'Budi Santoso',
            'email' => 'karyawan@absensi.com',
            'password' => 'password',
            'nomor_telepon' => '081234567890',
            'status_pegawai' => 'tetap',
            'role' => 'karyawan',
            'unit_kerja' => 'Rawat Inap',
            'jabatan' => 'Perawat',
            'tanggal_bergabung' => now()->subYear(),
            'is_active' => true,
        ]);

        // Assignment shift (karyawan_shift, bukan jadwal harian)
        $karyawan->shift()->attach($shiftUmum->id, [
            'tanggal_berlaku' => now()->startOfMonth(),
            'tanggal_berakhir' => now()->endOfMonth(),
        ]);

        // Jenis cuti
        JenisCuti::insert([
            [
                'nama' => 'Cuti Tahunan',
                'is_tahunan' => true,
                'default_kuota' => 12,
                'perlu_lampiran' => false,
                'potong_kuota' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nama' => 'Cuti Sakit',
                'is_tahunan' => false,
                'default_kuota' => 0,
                'perlu_lampiran' => true,
                'potong_kuota' => false,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nama' => 'Cuti Melahirkan',
                'is_tahunan' => false,
                'default_kuota' => 90,
                'perlu_lampiran' => true,
                'potong_kuota' => false,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nama' => 'Cuti Menikah',
                'is_tahunan' => false,
                'default_kuota' => 3,
                'perlu_lampiran' => false,
                'potong_kuota' => false,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // Kuota cuti tahunan buat karyawan test
        $karyawan->kuotaCutis()->create([
            'jenis_cuti_id' => JenisCuti::where('nama', 'Cuti Tahunan')->value('id'),
            'tahun' => now()->year,
            'kuota' => 12,
            'terpakai' => 0,
        ]);

        // Jadwal harian (7 hari ke depan, shift umum, reguler)
        foreach (range(0, 6) as $i) {
            $karyawan->jadwals()->create([
                'shift_id' => $shiftUmum->id,
                'tanggal' => now()->addDays($i)->toDateString(),
                'jenis' => 'reguler',
            ]);
        }

        // Contoh 1 hari piket, biar ada variasi jenis
        $karyawan->jadwals()->create([
            'shift_id' => $shiftUmum->id,
            'tanggal' => now()->addDays(7)->toDateString(),
            'jenis' => 'piket',
            'keterangan' => 'Piket akhir pekan',
        ]);
    }
}
