<?php

namespace App\Notifications;

use App\Models\Absensi;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AbsenTerlambat extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Absensi $absensi,
        public readonly int $menitTerlambat,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Notifikasi Keterlambatan Absensi')
            ->greeting("Halo, {$notifiable->nama}!")
            ->line("Anda tercatat terlambat **{$this->menitTerlambat} menit** pada hari ini.")
            ->line("Waktu masuk: **{$this->absensi->waktu_masuk->format('H:i')}**")
            ->line("Shift: **{$this->absensi->shift->nama_shift}** (jam masuk: {$this->absensi->shift->jam_masuk})")
            ->line('Harap lebih tepat waktu ke depannya.')
            ->salutation('RSU Banyumanik 2');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'judul'           => 'Anda Terlambat',
            'pesan'           => "Anda terlambat {$this->menitTerlambat} menit. Waktu masuk: {$this->absensi->waktu_masuk->format('H:i')}",
            'tipe'            => 'terlambat',
            'absensi_id'      => $this->absensi->id,
            'menit_terlambat' => $this->menitTerlambat,
            'tanggal'         => $this->absensi->tanggal->format('Y-m-d'),
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
