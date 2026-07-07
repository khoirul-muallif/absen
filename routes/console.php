<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduler — Aplikasi Absensi RSU Banyumanik 2
|--------------------------------------------------------------------------
|
| Untuk mengaktifkan scheduler di server, tambahkan cron job berikut:
|
|   * * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
|
*/

// ── Pengingat belum absen masuk ─────────────────────────────────────────
// Jalan setiap 30 menit antara jam 06:00–10:00
// Akan cek sendiri apakah sudah lewat batas waktu shift
Schedule::command('absensi:pengingat-belum-absen')
    ->everyThirtyMinutes()
    ->between('06:00', '10:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-masuk.log'));

// ── Pengingat belum absen pulang ────────────────────────────────────────
// Jalan setiap 30 menit antara jam 13:00–22:00
Schedule::command('absensi:pengingat-belum-pulang')
    ->everyThirtyMinutes()
    ->between('13:00', '22:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-pulang.log'));

// ── Rekap harian ────────────────────────────────────────────────────────
// Jalan setiap hari jam 23:59 — tandai yang tidak hadir sebagai alpha
Schedule::command('absensi:rekap-harian')
    ->dailyAt('23:59')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-rekap.log'));

// ── Bersihkan log lama (opsional) ───────────────────────────────────────
Schedule::command('queue:prune-failed --hours=168') // hapus failed job > 7 hari
    ->weekly();
