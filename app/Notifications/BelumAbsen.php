<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

class BelumAbsen extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $jenisAbsen, // 'masuk' | 'pulang'
        public readonly string $namaShift,
        public readonly string $jamShift,
        public readonly Carbon $tanggalShift, // tanggal shift, bukan tanggal kirim
        public readonly ?int $absensiId = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $label = $this->jenisAbsen;

        return [
            'judul'       => "Pengingat: Belum Absen {$label}",
            'pesan'       => "Anda belum melakukan absen {$label} untuk shift {$this->namaShift} ({$this->jamShift}).",
            'tipe'        => "belum_absen_{$label}",
            'nama_shift'  => $this->namaShift,
            'jam_shift'   => $this->jamShift,
            'tanggal'     => $this->tanggalShift->toDateString(),
            'absensi_id'  => $this->absensiId,
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
