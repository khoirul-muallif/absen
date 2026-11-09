<?php

namespace App\Notifications;

use App\Models\TukarJadwal;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TukarJadwalDiajukan extends Notification
{
    use Queueable;

    public function __construct(
        public readonly TukarJadwal $tukarJadwal,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $t = $this->tukarJadwal;
        $pengaju = $t->karyawanPengaju?->nama ?? 'Seorang rekan';

        return [
            'judul' => 'Permintaan Tukar Jadwal',
            'pesan' => "{$pengaju} ingin menukar jadwalnya {$t->labelJadwalAsal()} "
                . "dengan jadwal Anda {$t->labelJadwalTujuan()}.",
            'tipe' => 'tukar_jadwal',
            'aksi' => 'diajukan',
            'tukar_jadwal_id' => $t->id,
            'tanggal' => $t->tanggal_asal->format('Y-m-d'),
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
