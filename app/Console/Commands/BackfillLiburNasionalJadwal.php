<?php

namespace App\Console\Commands;

use App\Models\HariLibur;
use App\Models\Jadwal;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Sekali pakai: menandai baris jadwals jenis='libur' yang jatuh di
 * tanggal libur nasional instansi karyawannya.
 *
 * Sumbernya hari_liburs, BUKAN string `keterangan`. Keterangan cuma ada
 * di baris hasil generator dan bisa berubah kapan saja; hari_liburs
 * otoritatif dan ikut menangkap baris libur yang dibuat admin manual.
 *
 * Baris libur MINGGUAN yang kebetulan jatuh di tanggal libur nasional
 * ikut ditandai. Itu benar: hari itu memang libur nasional juga, dan
 * kolom ini menjawab "baris libur ini jatuh di libur nasional?", bukan
 * "alasan utamanya apa".
 */
class BackfillLiburNasionalJadwal extends Command
{
    protected $signature = 'jadwal:backfill-libur-nasional
                            {--dry-run : Cuma hitung, tidak menulis apa pun}';

    protected $description = 'Tandai baris Jadwal libur yang jatuh di tanggal libur nasional (sekali pakai setelah kolom libur_nasional ditambahkan)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // Per instansi: tanggal libur nasional berbeda antar instansi,
        // dan karyawan terikat ke instansinya sendiri.
        $liburPerInstansi = HariLibur::query()
            ->meliburkan()
            ->get()
            ->groupBy('instansi_id')
            ->map(fn ($grup) => $grup->pluck('tanggal')->map(fn ($t) => $t->format('Y-m-d'))->all());

        if ($liburPerInstansi->isEmpty()) {
            $this->info('Tidak ada hari libur yang meliburkan. Tidak ada yang perlu ditandai.');

            return self::SUCCESS;
        }

        $total = 0;

        foreach ($liburPerInstansi as $instansiId => $tanggalLibur) {
            $query = Jadwal::query()
                ->where('jenis', Jadwal::JENIS_LIBUR)
                ->where('libur_nasional', false)
                ->whereIn('tanggal', $tanggalLibur)
                ->whereHas('karyawan', fn ($q) => $q->where('instansi_id', $instansiId));

            $jumlah = $query->count();

            if ($jumlah > 0 && ! $dryRun) {
                $query->update(['libur_nasional' => true]);
            }

            $total += $jumlah;
        }

        $this->info($dryRun
            ? "[DRY-RUN] {$total} baris akan ditandai sebagai libur nasional."
            : "{$total} baris ditandai sebagai libur nasional.");

        return self::SUCCESS;
    }
}
