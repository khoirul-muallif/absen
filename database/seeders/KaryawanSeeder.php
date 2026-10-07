<?php

namespace Database\Seeders;

use App\Models\Instansi;
use App\Models\JenisCuti;
use App\Models\Karyawan;
use App\Models\Shift;
use Illuminate\Database\Seeder;
use App\Models\PolaRotasi;

class KaryawanSeeder extends Seeder
{
    public function run(): void
    {
        $instansi = Instansi::firstOrFail();
        $shiftUmum = Shift::where('nama_shift', 'umum')->firstOrFail();
        $shiftRotasi = Shift::whereIn('nama_shift', ['pagi', 'siang', 'malam'])
            ->get()
            ->keyBy('nama_shift');
        $jenisCutiTahunan = JenisCuti::where('nama', 'Cuti Tahunan')->value('id');

        // ── Karyawan shift umum (Senin-Jumat, jadwal seragam) ──
        $karyawanUmum = [
            [
                'nip' => '198501012026011001',
                'nama' => 'Budi Santoso',
                'email' => 'budi@rsb.com',
                'password' => 'budi',
                'nomor_telepon' => '081234567890',
                'jabatan' => 'Staff Administrasi',
            ],
            [
                'nip' => '198502022026022002',
                'nama' => 'Khoirul Alif',
                'email' => 'irul@rsb.com',
                'password' => 'irul',
                'nomor_telepon' => '081234567891',
                'jabatan' => 'Staff Administrasi',
            ],
        ];

        foreach ($karyawanUmum as $data) {
            $karyawan = $this->buatKaryawan($instansi, $data, $shiftUmum->id, $jenisCutiTahunan);

            // Jadwal 15 hari ke depan, skip hari yang bukan hari kerja shift umum
            foreach (range(0, 14) as $i) {
                $tanggal = now()->addDays($i);
                if (! $shiftUmum->adalahHariKerja($tanggal)) {
                    continue;
                }

                $karyawan->jadwals()->create([
                    'shift_id' => $shiftUmum->id,
                    'tanggal' => $tanggal->toDateString(),
                    'jenis' => 'reguler',
                ]);
            }
        }

        // ── Karyawan shift rotasi (pagi/siang/malam bergantian) ──
        $karyawanRotasi = [
            [
                'nip' => '198601011026011003',
                'nama' => 'Dedi Kurniawan',
                'email' => 'dedi@rsb.com',
                'password' => 'dedi',
                'nomor_telepon' => '081234567892',
                'jabatan' => 'Perawat',
            ],
            [
                'nip' => '198702022026022004',
                'nama' => 'Siti Aminah',
                'email' => 'siti@rsb.com',
                'password' => 'siti',
                'nomor_telepon' => '081234567893',
                'jabatan' => 'Perawat',
            ],
            [
                'nip' => '198803032026033005',
                'nama' => 'Rina Wulandari',
                'email' => 'rina@rsb.com',
                'password' => 'rina',
                'nomor_telepon' => '081234567894',
                'jabatan' => 'Perawat',
            ],
        ];

        $urutanShift = ['pagi', 'siang', 'malam'];
        $daftarKaryawanRotasi = [];

        foreach ($karyawanRotasi as $index => $data) {
            $karyawan = $this->buatKaryawan($instansi, $data, null, $jenisCutiTahunan);
            $daftarKaryawanRotasi[] = $karyawan;
        }

        // Rotasi harian: tiap hari, urutan shift antar karyawan digeser satu
        foreach (range(0, 14) as $hari) {
            foreach ($daftarKaryawanRotasi as $index => $karyawan) {
                $namaShift = $urutanShift[($index + $hari) % 3];
                $shift = $shiftRotasi[$namaShift];

                $karyawan->jadwals()->create([
                    'shift_id' => $shift->id,
                    'tanggal' => now()->addDays($hari)->toDateString(),
                    'jenis' => 'reguler',
                ]);
            }
        }
        // ── Karyawan rotasi BER-LIBUR (pola 5 kerja 2 libur, unit Rawat Inap) ──
        // Beda dari Dedi/Siti/Rina di atas (rotasi nonstop tanpa langkah libur).
        // Dibuat lewat KaryawanPolaRotasi + PolaRotasi, BUKAN Jadwal manual,
        // supaya generator (jadwal:generate-rotasi) dan jalur "rotasi sedang
        // libur" bisa diuji & diklik manual di admin panel. Lihat todo.md.
        //
        // CATATAN: Jadwal TIDAK digenerate otomatis di sini — jalankan manual
        // setelah seed: php artisan jadwal:generate-rotasi {bulan} {tahun}
        $polaBerlibur = PolaRotasi::where('nama_pola', 'Rotasi 5 Kerja 2 Libur')->firstOrFail();

        $yono = $this->buatKaryawan($instansi, [
            'nip' => '198904042026044006',
            'nama' => 'Yono Pratama',
            'email' => 'yono@rsb.com',
            'password' => 'yono',
            'nomor_telepon' => '081234567895',
            'jabatan' => 'Perawat',
        ], null, $jenisCutiTahunan);

        $yono->update([
            'tipe_jadwal' => Karyawan::TIPE_ROTASI,
            'unit_kerja'  => 'Rawat Inap',
        ]);

        $yono->karyawanPolaRotasis()->create([
            'pola_rotasi_id'   => $polaBerlibur->id,
            'tanggal_mulai'    => now()->startOfMonth(),
            'tanggal_berakhir' => null,
        ]);
    }

    protected function buatKaryawan(Instansi $instansi, array $data, ?int $shiftIdDefault, ?int $jenisCutiTahunan): Karyawan
    {
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

        if ($shiftIdDefault) {
            $karyawan->shift()->attach($shiftIdDefault, [
                'tanggal_berlaku' => now()->startOfMonth(),
                'tanggal_berakhir' => now()->endOfMonth(),
            ]);
        }

        $karyawan->kuotaCutis()->create([
            'jenis_cuti_id' => $jenisCutiTahunan,
            'tahun' => now()->year,
            'kuota' => 12,
            'terpakai' => 0,
        ]);

        return $karyawan;
    }
}
