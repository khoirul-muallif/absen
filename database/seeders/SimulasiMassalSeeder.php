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
    protected int $jumlahKaryawanBaru = 150;
    protected float $porsiRotasi = 0.8; // ~120 rotasi tersebar ke unit 24/7, sisanya umum
    protected int $jumlahBulanKeBelakang = 3;

    protected array $unitRotasi = ['Rawat Inap', 'IGD', 'ICU'];

    protected float $peluangAlpha = 0.05;
    protected float $peluangTerlambat = 0.20;
    protected float $porsiKaryawanDapatCuti = 0.10;
    protected float $porsiKaryawanDapatDinas = 0.05;

    protected int $seedAcak = 12345;

    public function run(): void
    {
        mt_srand($this->seedAcak);
        fake()->seed($this->seedAcak);

        $instansi = Instansi::firstOrFail();
        $admin = User::firstOrFail();
        $shiftUmum = Shift::where('instansi_id', $instansi->id)->where('nama_shift', 'umum')->firstOrFail();
        $jenisCutiTahunan = JenisCuti::where('nama', 'Cuti Tahunan')->firstOrFail();

        // $polaPerUnit['Rawat Inap'] = PolaRotasi, dst -- satu pola rotasi
        // individu per unit (bukan per tim shift lagi, lihat PolaRotasiSeeder).
        $polaPerUnit = [];
        foreach ($this->unitRotasi as $unit) {
            $pola = PolaRotasi::where('unit_kerja', $unit)
                ->where('nama_pola', 'Rotasi 2 Pagi - 2 Siang - 2 Malam - 2 Libur')
                ->first();

            if (! $pola) {
                throw new \RuntimeException("PolaRotasi rotasi-individu untuk unit \"{$unit}\" belum ada -- jalankan PolaRotasiSeeder dulu.");
            }

            $polaPerUnit[$unit] = $pola;
        }

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

        foreach ($karyawanUmumBaru as $k) {
            $k->karyawanShift()->create([
                'shift_id' => $shiftUmum->id,
                'tanggal_berlaku' => $awalWindow,
                'tanggal_berakhir' => null,
            ]);
        }

        // Sebar rotasi: rata ke tiap unit. Tiap karyawan di-assign ke SATU
        // pola rotasi-individu unitnya, dengan tanggal_mulai di-offset
        // menyebar ke seluruh 8 posisi siklus (index % panjangSiklus) --
        // supaya pada hari yang sama, anggota unit itu tersebar di posisi
        // Pagi/Siang/Malam/Libur yang berbeda-beda, bukan serentak di
        // posisi yang sama.
        $karyawanRotasiBaru = collect();
        $jumlahPerUnit = intdiv($jumlahRotasi, count($this->unitRotasi));
        $sisaRotasi = $jumlahRotasi;

        foreach ($this->unitRotasi as $i => $unit) {
            $jumlahUnitIni = ($i === count($this->unitRotasi) - 1) ? $sisaRotasi : $jumlahPerUnit;
            $sisaRotasi -= $jumlahUnitIni;

            $pola = $polaPerUnit[$unit];
            $panjangSiklus = $pola->panjangSiklus();

            $karyawanUnitIni = Karyawan::factory()
                ->rotasi()
                ->count($jumlahUnitIni)
                ->create(['instansi_id' => $instansi->id, 'unit_kerja' => $unit]);

            foreach ($karyawanUnitIni as $index => $k) {
                $k->karyawanPolaRotasis()->create([
                    'pola_rotasi_id' => $pola->id,
                    'tanggal_mulai' => $awalWindow->copy()->subDays($index % $panjangSiklus),
                    'tanggal_berakhir' => null,
                ]);
            }

            $karyawanRotasiBaru = $karyawanRotasiBaru->concat($karyawanUnitIni);
        }

        $this->command->info("Karyawan baru: {$jumlahUmum} umum, {$jumlahRotasi} rotasi (".
            $karyawanRotasiBaru->countBy('unit_kerja')->map(fn ($n, $u) => "{$u}: {$n}")->implode(', ')
        .").");

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
                //
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
                continue;
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
