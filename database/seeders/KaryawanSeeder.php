<?php

namespace Database\Seeders;

use App\Models\JenisCuti;
use App\Models\Karyawan;
use App\Models\Shift;
use Illuminate\Database\Seeder;

class KaryawanSeeder extends Seeder
{
    public function run(): void
    {
        $instansi = \App\Models\Instansi::firstOrFail();
        $shiftUmum = Shift::where('nama_shift', 'umum')->firstOrFail();
        $jenisCutiTahunan = JenisCuti::where('nama', 'Cuti Tahunan')->value('id');

        $daftarKaryawan = [
            [
                'nip' => '198501012026011001',
                'nama' => 'Budi Santoso',
                'email' => 'budi@rsb.com',
                'password' => 'budi',
                'nomor_telepon' => '081234567890',
                'jabatan' => 'Perawat',
            ],
            [
                'nip' => '198502022026022002',
                'nama' => 'Khoirul Alif',
                'email' => 'irul@rsb.com',
                'password' => 'irul',
                'nomor_telepon' => '081234567891',
                'jabatan' => 'Perawat',
            ],
        ];

        foreach ($daftarKaryawan as $data) {
            $karyawan = Karyawan::create([
                'instansi_id' => $instansi->id,
                'nip' => $data['nip'],
                'nama' => $data['nama'],
                'email' => $data['email'],
                'password' => $data['password'],
                'nomor_telepon' => $data['nomor_telepon'],
                'status_pegawai' => 'tetap',
                'role' => 'karyawan',
                'unit_kerja' => 'Rawat Inap',
                'jabatan' => $data['jabatan'],
                'tanggal_bergabung' => now()->subMonths(6),
                'is_active' => true,
            ]);

            $karyawan->shift()->attach($shiftUmum->id, [
                'tanggal_berlaku' => now()->startOfMonth(),
                'tanggal_berakhir' => now()->endOfMonth(),
            ]);

            $karyawan->kuotaCutis()->create([
                'jenis_cuti_id' => $jenisCutiTahunan,
                'tahun' => now()->year,
                'kuota' => 12,
                'terpakai' => 0,
            ]);

            // Jadwal harian 7 hari ke depan
            foreach (range(0, 6) as $i) {
                $karyawan->jadwals()->create([
                    'shift_id' => $shiftUmum->id,
                    'tanggal' => now()->addDays($i)->toDateString(),
                    'jenis' => 'reguler',
                ]);
            }
        }

        // Contoh 1 hari piket buat Budi, biar ada variasi jenis
        Karyawan::where('email', 'budi@rsb.com')->first()
            ->jadwals()->create([
                'shift_id' => $shiftUmum->id,
                'tanggal' => now()->addDays(7)->toDateString(),
                'jenis' => 'piket',
                'keterangan' => 'Piket akhir pekan',
            ]);
    }
}
