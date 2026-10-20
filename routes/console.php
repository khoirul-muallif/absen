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

// ── Generator jadwal bulanan (karyawan umum) ────────────────────────────
// Jalan tanggal terakhir tiap bulan jam 23:30 — generate Jadwal bulan
// depan dari assignment KaryawanShift. Tanpa --bulan/--tahun, command ini
// default ke bulan depan (lihat jadwal:generate-bulanan). Idempoten: skip
// tanggal yang sudah punya baris Jadwal (manual atau hasil generate
// sebelumnya), jadi aman kalau scheduler ini sempat gagal jalan atau
// di-trigger ulang manual.
Schedule::command('jadwal:generate-bulanan')
    ->lastDayOfMonth('23:30')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-jadwal-bulanan.log'));

// ── Generator jadwal rotasi (semua unit) ────────────────────────────────
// Jalan tanggal terakhir tiap bulan jam 23:35 (setelah generate-bulanan
// 23:30, sebelum rekap harian 23:59) — generate Jadwal bulan depan untuk
// SEMUA karyawan tipe_jadwal=rotasi di SEMUA unit_kerja sekaligus (tanpa
// --unit, query tidak difilter — lihat GenerateJadwalRotasi::handle()).
// Idempoten tanpa --overwrite-generate: baris manual maupun baris hasil
// generate sebelumnya tidak ditimpa.
Schedule::call(function () {
    $bulanDepan = now()->addMonthNoOverflow();
    \Illuminate\Support\Facades\Artisan::call('jadwal:generate-rotasi', [
        'bulan' => $bulanDepan->month,
        'tahun' => $bulanDepan->year,
    ]);
})
    ->name('generate-jadwal-rotasi-bulanan')
    ->lastDayOfMonth('23:35')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/scheduler-jadwal-rotasi.log'));

// ── Bersihkan log lama (opsional) ───────────────────────────────────────
Schedule::command('queue:prune-failed --hours=168') // hapus failed job > 7 hari
    ->weekly();
