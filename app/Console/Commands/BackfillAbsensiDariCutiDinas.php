<?php

namespace App\Console\Commands;

use App\Models\Cuti;
use App\Models\Dinas;
use Illuminate\Console\Command;

class BackfillAbsensiDariCutiDinas extends Command
{
    protected $signature = 'absensi:backfill-cuti-dinas
                            {--force : Lewati konfirmasi (untuk test/CI)}';

    protected $description = 'Sinkronkan ulang Jadwal & Absensi dari Cuti/Dinas yang sudah approved sebelumnya';

    public function handle(): int
    {
        $cutiCount = Cuti::where('status', 'approved')->count();
        $dinasCount = Dinas::where('status', 'approved')->count();

        $this->warn("Akan menimpa Jadwal & Absensi untuk {$cutiCount} Cuti dan {$dinasCount} Dinas approved.");
        $this->line('Jadwal yang sumbernya "manual" di tanggal-tanggal itu IKUT tertimpa — memang itu tujuan backfill, tapi pastikan itu yang kamu mau.');

        if (! $this->option('force') && ! $this->confirm('Lanjutkan?')) {
            $this->info('Dibatalkan.');

            return self::SUCCESS;
        }

        // PENTING: panggil resyncJadwalDanAbsensi(), BUKAN afterApprove().
        //
        // afterApprove() sekarang punya efek samping yang tidak boleh diulang:
        //   - fase 22: menaikkan KuotaCuti.terpakai (jalan massal = kuota
        //     terhitung ganda, dan bisa melempar KuotaCutiTidakCukupException
        //     di tengah loop sehingga sebagian sudah ter-increment)
        // Command ini cuma bertugas menyinkronkan Jadwal & Absensi seperti
        // yang dijanjikan namanya, jadi bagian kuota sengaja tidak disentuh.
        // Operasi di bawah idempoten — aman dijalankan berkali-kali.

        Cuti::where('status', 'approved')->each(fn ($cuti) => $cuti->resyncJadwalDanAbsensi());
        Dinas::where('status', 'approved')->each(fn ($dinas) => $dinas->resyncJadwalDanAbsensi());

        $this->info("Selesai. {$cutiCount} data Cuti dan {$dinasCount} data Dinas diproses ulang.");
        $this->comment('Kuota cuti sengaja TIDAK disentuh oleh command ini.');

        return self::SUCCESS;
    }
}
