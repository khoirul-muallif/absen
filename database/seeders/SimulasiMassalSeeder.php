<?php

namespace Database\Seeders;

use App\Models\Cuti;
use App\Models\Dinas;
use App\Models\Instansi;
use App\Models\Jadwal;
use App\Models\JenisCuti;
use App\Models\Karyawan;
use App\Models\PolaRotasi;
use App\Models\QrInstansi;
use App\Models\Shift;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class SimulasiMassalSeeder extends Seeder
{
    // ── Konfigurasi — ubah di sini sebelum run, konsisten dengan gaya
    // AbsensiSimulasiSeeder (plain, tanpa opsi Artisan) ──
    protected int $jumlahKaryawanBaru = 150;
    protected float $porsiRotasi = 0.3; // sisanya umum
    protected int $jumlahBulanKeBelakang = 3; // termasuk bulan berjalan

    protected float $peluangAlpha = 0.05;
    protected float $peluangTerlambat = 0.20; // dari yang bukan alpha
    protected float $porsiKaryawanDapatCuti = 0.10;
    protected float $porsiKaryawanDapatDinas = 0.05;

    // Seed tetap — hasilnya reproducible, tidak beda tiap migrate:fresh --seed
    protected int $seedAcak = 12345;

    public function run(): void
    {
        mt_srand($this->seedAcak);
        fake()->seed($this->seedAcak);

        $instansi = Instansi::firstOrFail();
        $admin = User::firstOrFail();
        $shiftUmum = Shift::where('instansi_id', $instansi->id)->where('nama_shift', 'umum')->firstOrFail();
        $polaRotasi = PolaRotasi::where('instansi_id', $instansi->id)->where('nama_pola', 'Rotasi 5 Kerja 2 Libur')->firstOrFail();
        $jenisCutiTahunan = JenisCuti::where('nama', 'Cuti Tahunan')->firstOrFail();

        $awalWindow = today()->subMonths($this->jumlahBulanKeBelakang - 1)->startOfMonth();
        $akhirWindow = today();

        $this->command->info("Window simulasi: {$awalWindow->toDateString()} s/d {$akhirWindow->toDateString()}");

        // ── 1. Karyawan dummy baru ──
        $jumlahRotasi = (int) round($this->jumlahKaryawanBaru * $this->porsiRotasi);
        $jumlahUmum = $this->jumlahKaryawanBaru - $jumlahRotasi;

        $karyawanUmumBaru = Karyawan::factory()
            ->umum()
            ->count($jumlahUmum)
            ->create(['instansi_id' => $instansi->id]);

        $karyawanRotasiBaru = Karyawan::factory()
            ->rotasi()
            ->count($jumlahRotasi)
            ->create(['instansi_id' => $instansi->id, 'unit_kerja' => 'Rawat Inap']);

        foreach ($karyawanUmumBaru as $k) {
            $k->karyawanShift()->create([
                'shift_id' => $shiftUmum->id,
                'tanggal_berlaku' => $awalWindow,
                'tanggal_berakhir' => null,
            ]);
        }

        foreach ($karyawanRotasiBaru as $k) {
            $k->karyawanPolaRotasis()->create([
                'pola_rotasi_id' => $polaRotasi->id,
                'tanggal_mulai' => $awalWindow,
                'tanggal_berakhir' => null,
            ]);
        }

        $this->command->info("Karyawan baru: {$jumlahUmum} umum, {$jumlahRotasi} rotasi.");

        // ── 2. Generate Jadwal lewat command resmi, bulan per bulan ──
        $periodeBulan = [];
        for ($i = $this->jumlahBulanKeBelakang - 1; $i >= 0; $i--) {
            $periodeBulan[] = today()->subMonthsNoOverflow($i);
        }

        foreach ($periodeBulan as $p) {
            Artisan::call('jadwal:generate-bulanan', ['--bulan' => $p->month, '--tahun' => $p->year]);
            $this->command->line("  generate-bulanan {$p->format('M Y')}: selesai.");

            Artisan::call('jadwal:generate-rotasi', ['bulan' => $p->month, 'tahun' => $p->year]);
            $this->command->line("  generate-rotasi {$p->format('M Y')}: selesai.");
        }

        // ── 3. Inject sebagian Cuti/Dinas approved secara acak ──
        $semuaKaryawanBaru = $karyawanUmumBaru->concat($karyawanRotasiBaru);

        $dapatCuti = $semuaKaryawanBaru->random((int) round($semuaKaryawanBaru->count() * $this->porsiKaryawanDapatCuti));
        $cutiBerhasil = 0;
        foreach ($dapatCuti as $k) {
            $mulai = Carbon::parse(fake()->dateTimeBetween($awalWindow, $akhirWindow->copy()->subDays(5)));
            $selesai = $mulai->copy()->addDays(fake()->numberBetween(0, 2));

            $cuti = Cuti::create([
                'karyawan_id' => $k->id,
                'jenis_cuti_id' => $jenisCutiTahunan->id,
                'tanggal_mulai' => $mulai,
                'tanggal_selesai' => $selesai,
                'jumlah_hari' => $mulai->diffInDays($selesai) + 1,
                'alasan' => 'Simulasi massal',
                'status' => 'pending',
            ]);

            try {
                $cuti->approve($admin);
                $cutiBerhasil++;
            } catch (\Throwable $e) {
                // Kuota kebetulan habis untuk kombinasi ini — dibiarkan pending.
                // Realistis: tidak semua pengajuan ujungnya approved.
            }
        }

        $dapatDinas = $semuaKaryawanBaru->random((int) round($semuaKaryawanBaru->count() * $this->porsiKaryawanDapatDinas));
        foreach ($dapatDinas as $k) {
            $mulai = Carbon::parse(fake()->dateTimeBetween($awalWindow, $akhirWindow->copy()->subDays(3)));
            $selesai = $mulai->copy()->addDays(fake()->numberBetween(0, 1));

            Dinas::create([
                'karyawan_id' => $k->id,
                'tanggal_mulai' => $mulai,
                'tanggal_selesai' => $selesai,
                'tujuan' => 'Simulasi massal',
                'keperluan' => 'Simulasi massal',
                'status' => 'pending',
            ])->approve($admin);
        }

        $this->command->info("Cuti approved: {$cutiBerhasil}/{$dapatCuti->count()}, Dinas approved: {$dapatDinas->count()}.");

        // ── 4. Simulasikan Absensi untuk Jadwal reguler/piket yang sudah lewat ──
        $shiftCache = Shift::all()->keyBy('id');
        $qrInstansi = QrInstansi::where('instansi_id', $instansi->id)->first();

        $jadwalUntukDiabsen = Jadwal::whereIn('jenis', [Jadwal::JENIS_REGULER, Jadwal::JENIS_PIKET])
            ->whereNotNull('shift_id')
            ->whereBetween('tanggal', [$awalWindow->toDateString(), $akhirWindow->toDateString()])
            ->orderBy('karyawan_id')
            ->orderBy('tanggal')
            ->get(['karyawan_id', 'shift_id', 'tanggal']);

        $akumulasiPerKaryawanBulan = [];
        $baris = [];
        $totalAlpha = 0;
        $totalHadir = 0;
        $now = now();

        foreach ($jadwalUntukDiabsen as $jadwal) {
            if (fake()->randomFloat(2, 0, 1) < $this->peluangAlpha) {
                $totalAlpha++;
                continue; // sengaja tidak dibuat row — biar RekapHarian punya kerjaan
            }

            $shift = $shiftCache[$jadwal->shift_id];
            $kunciBulan = $jadwal->tanggal->format('Y-m');
            $akumulasiPerKaryawanBulan[$jadwal->karyawan_id][$kunciBulan] ??= 0;

            $menitTelat = fake()->randomFloat(2, 0, 1) < $this->peluangTerlambat
                ? fake()->numberBetween(1, 45)
                : 0;

            $waktuMasuk = Carbon::parse($jadwal->tanggal->toDateString().' '.$shift->jamMasukString())
                ->addMinutes($menitTelat);

            $akumulasiPerKaryawanBulan[$jadwal->karyawan_id][$kunciBulan] += $menitTelat;
            $status = $shift->tentukanStatus($waktuMasuk);
            $melebihi = $shift->sudahMelebihiToleransiBulanan(
                $akumulasiPerKaryawanBulan[$jadwal->karyawan_id][$kunciBulan]
            );

            $baris[] = [
                'karyawan_id' => $jadwal->karyawan_id,
                'shift_id' => $jadwal->shift_id,
                'qr_instansi_id' => $qrInstansi?->id,
                'tanggal' => $jadwal->tanggal->toDateString(),
                'waktu_masuk' => $waktuMasuk,
                'menit_terlambat' => $menitTelat,
                'melebihi_toleransi_bulanan' => $melebihi,
                'status' => $status,
                'latitude_masuk' => -7.0784947,
                'longitude_masuk' => 110.4119292,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $totalHadir++;

            if (count($baris) >= 500) {
                DB::table('absensi')->insert($baris);
                $baris = [];
            }
        }

        if (! empty($baris)) {
            DB::table('absensi')->insert($baris);
        }

        $this->command->info("Absensi dibuat: {$totalHadir}, sengaja dilewati (alpha): {$totalAlpha}.");

        // ── 5. Audit otomatis ──
        $this->command->info('Menjalankan audit akhir...');
        Artisan::call('karyawan:cek-tipe-jadwal');
        $this->command->line(trim(Artisan::output()));

        Artisan::call('absensi:audit-menit-terlambat');
        $this->command->line(trim(Artisan::output()));
    }
}
