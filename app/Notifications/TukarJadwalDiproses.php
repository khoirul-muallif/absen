<?php

namespace App\Notifications;

use App\Models\TukarJadwal;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TukarJadwalDiproses extends Notification
{
    use Queueable;

    public const HASIL_APPROVED = 'approved';
    public const HASIL_REJECTED = 'rejected';
    public const HASIL_DIBATALKAN_ADMIN = 'dibatalkan_admin';

    public function __construct(
        public readonly TukarJadwal $tukarJadwal,
        public readonly string $hasil,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $t = $this->tukarJadwal;
        $sayaPengaju = $notifiable->id === $t->karyawan_pengaju_id;

        $objek = $t->isPindahSendiri()
            ? "pindah jadwal {$t->labelJadwalAsal()} ke {$t->tanggal_baru?->format('d M Y')}"
            : ($sayaPengaju
                ? "tukar jadwal {$t->labelJadwalAsal()} dengan {$t->labelJadwalTujuan()}"
                : "tukar jadwal dengan " . ($t->karyawanPengaju?->nama ?? 'rekan Anda')
                    . " ({$t->labelJadwalAsal()})");

        $judul = match ($this->hasil) {
            self::HASIL_APPROVED => 'Pengajuan Disetujui Admin',
            self::HASIL_REJECTED => 'Pengajuan Ditolak Admin',
            self::HASIL_DIBATALKAN_ADMIN => 'Pengajuan Dibatalkan Admin',
        };

        $pesan = match ($this->hasil) {
            self::HASIL_APPROVED => "Admin menyetujui {$objek}. Jadwal Anda sudah diperbarui.",
            self::HASIL_REJECTED => "Admin menolak {$objek}.",
            self::HASIL_DIBATALKAN_ADMIN => "Admin membatalkan {$objek} karena rekan tujuan tidak merespons.",
        };

        if ($this->hasil !== self::HASIL_APPROVED && filled($t->catatan_approval)) {
            $pesan .= " Catatan: {$t->catatan_approval}";
        }

        return [
            'judul' => $judul,
            'pesan' => $pesan,
            'tipe' => 'tukar_jadwal',
            'aksi' => $this->hasil,
            'tukar_jadwal_id' => $t->id,
            'tanggal' => $t->tanggal_asal->format('Y-m-d'),
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
