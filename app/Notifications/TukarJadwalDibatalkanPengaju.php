<?php

namespace App\Notifications;

use App\Models\TukarJadwal;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TukarJadwalDibatalkanPengaju extends Notification
{
    use Queueable;

    /**
     * $statusSaatDibatalkan di-snapshot saat notifikasi dibuat, BUKAN dibaca
     * dari $tukarJadwal->status di toDatabase(). Baris TukarJadwal-nya
     * di-delete() tepat setelah ini, jadi statusnya tidak bisa diandalkan
     * lagi saat notifikasi diproses.
     */
    public function __construct(
        public readonly TukarJadwal $tukarJadwal,
        public readonly string $statusSaatDibatalkan,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $t = $this->tukarJadwal;
        $pengaju = $t->karyawanPengaju?->nama ?? 'Rekan Anda';

        // Hapus cabang 'menunggu_admin' kalau diputuskan hanya memberi tahu
        // di tahap menunggu_rekan.
        $pesan = $this->statusSaatDibatalkan === 'menunggu_admin'
            ? "{$pengaju} membatalkan tukar jadwal {$t->labelJadwalAsal()} dengan "
                . "{$t->labelJadwalTujuan()}, padahal Anda sudah menyetujuinya. "
                . 'Jadwal Anda tidak jadi berubah.'
            : "{$pengaju} membatalkan permintaan tukar jadwal {$t->labelJadwalAsal()} "
                . 'dengan jadwal Anda. Anda tidak perlu meresponsnya lagi.';

        return [
            'judul' => 'Tukar Jadwal Dibatalkan',
            'pesan' => $pesan,
            'tipe' => 'tukar_jadwal',
            'aksi' => 'dibatalkan_pengaju',
            // Baris TukarJadwal-nya sudah dihapus saat notifikasi ini dibaca —
            // id-nya menggantung, sengaja tetap dikirim demi konsistensi bentuk
            // data (keputusan 9 Nov 2026). FE harus siap kalau detailnya 404.
            'tukar_jadwal_id' => $t->id,
            'tanggal' => $t->tanggal_asal->format('Y-m-d'),
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
