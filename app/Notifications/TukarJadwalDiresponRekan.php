<?php

namespace App\Notifications;

use App\Models\TukarJadwal;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TukarJadwalDiresponRekan extends Notification
{
    use Queueable;

    public function __construct(
        public readonly TukarJadwal $tukarJadwal,
        public readonly bool $setuju,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $t = $this->tukarJadwal;
        $rekan = $t->karyawanTujuan?->nama ?? 'Rekan tujuan';

        if ($this->setuju) {
            $judul = 'Tukar Jadwal Disetujui Rekan';
            $pesan = "{$rekan} menyetujui tukar jadwal {$t->labelJadwalAsal()}. "
                . 'Sekarang menunggu persetujuan admin.';
        } else {
            $judul = 'Tukar Jadwal Ditolak Rekan';
            $pesan = "{$rekan} menolak tukar jadwal {$t->labelJadwalAsal()}.";

            if (filled($t->catatan_penolakan_rekan)) {
                $pesan .= " Alasan: {$t->catatan_penolakan_rekan}";
            }
        }

        return [
            'judul' => $judul,
            'pesan' => $pesan,
            'tipe' => 'tukar_jadwal',
            'aksi' => $this->setuju ? 'disetujui_rekan' : 'ditolak_rekan',
            'tukar_jadwal_id' => $t->id,
            'tanggal' => $t->tanggal_asal->format('Y-m-d'),
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
