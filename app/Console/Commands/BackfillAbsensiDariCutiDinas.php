<?php

namespace App\Console\Commands;

use App\Models\Cuti;
use App\Models\Dinas;
use Illuminate\Console\Command;

class BackfillAbsensiDariCutiDinas extends Command
{
    protected $signature = 'absensi:backfill-cuti-dinas';
    protected $description = 'Sinkronkan ulang Absensi dari Cuti/Dinas yang sudah approved sebelumnya';

    public function handle(): void
    {
        $cutiCount = Cuti::where('status', 'approved')->count();
        $dinasCount = Dinas::where('status', 'approved')->count();

        Cuti::where('status', 'approved')->each(fn ($cuti) => $cuti->afterApprove());
        Dinas::where('status', 'approved')->each(fn ($dinas) => $dinas->afterApprove());

        $this->info("Selesai. {$cutiCount} data Cuti dan {$dinasCount} data Dinas diproses ulang.");
    }
}
