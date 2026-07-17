<?php

namespace App\Console\Commands;

use App\Models\Karyawan;
use Illuminate\Console\Command;

class CekTipeJadwalKaryawan extends Command
{
    protected $signature   = 'karyawan:cek-tipe-jadwal';
    protected $description = 'Cek konsistensi tipe_jadwal karyawan vs data KaryawanShift — deteksi kemungkinan salah setting sebelum jadi bug produksi';

    public function handle(): int
    {
        $this->info('Mengecek konsistensi tipe_jadwal vs KaryawanShift...');
        $this->newLine();

        $masalah = 0;

        // Kasus 1: tipe ROTASI tapi punya KaryawanShift (seharusnya gak ada sama sekali)
        $rotasiTapiPunyaShift = Karyawan::where('tipe_jadwal', Karyawan::TIPE_ROTASI)
            ->whereHas('karyawanShift')
            ->get();

        foreach ($rotasiTapiPunyaShift as $k) {
            $this->error("  ✗ {$k->nama} (ID {$k->id}) — tipe ROTASI tapi punya record KaryawanShift. Cek: tipe_jadwal salah, atau assignment shift-nya harus dihapus?");
            $masalah++;
        }

        // Kasus 2: tipe UMUM, aktif, tapi gak punya KaryawanShift yang aktif hari ini
        $umumTanpaShiftAktif = Karyawan::where('tipe_jadwal', Karyawan::TIPE_UMUM)
            ->where('is_active', true)
            ->whereDoesntHave('karyawanShift', function ($q) {
                $q->where('tanggal_berlaku', '<=', today())
                    ->where(fn ($qq) => $qq->whereNull('tanggal_berakhir')
                        ->orWhere('tanggal_berakhir', '>=', today()));
            })
            ->get();

        foreach ($umumTanpaShiftAktif as $k) {
            $this->warn("  ⚠ {$k->nama} (ID {$k->id}) — tipe UMUM tapi gak punya KaryawanShift aktif per hari ini. RekapHarian akan terus anggap ini libur mingguan sampai dibuatkan assignment baru.");
            $masalah++;
        }

        $this->newLine();
        if ($masalah === 0) {
            $this->info('Semua konsisten, gak ada masalah ditemukan.');
        } else {
            $this->warn("Ditemukan {$masalah} potensi masalah. Cek satu-satu di atas sebelum lanjut ke rekap/generator jadwal.");
        }

        return self::SUCCESS;
    }
}
