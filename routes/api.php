<?php

use App\Http\Controllers\Api\AbsensiController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\NotifikasiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Aplikasi Absensi
|--------------------------------------------------------------------------
*/

// ── Public ──────────────────────────────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
});

// ── Protected ───────────────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::prefix('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me',     [AuthController::class, 'me']);
    });

    // Absensi
    Route::prefix('absensi')->group(function () {
        Route::get('status',  [AbsensiController::class, 'status']);
        Route::post('masuk',  [AbsensiController::class, 'masuk']);
        Route::post('pulang', [AbsensiController::class, 'pulang']);
        Route::get('riwayat', [AbsensiController::class, 'riwayat']);
        Route::get('rekap',   [AbsensiController::class, 'rekap']);
    });

    // QR
    Route::get('instansi/qr/{kode}', [AbsensiController::class, 'validasiQr']);

    // Notifikasi
    Route::prefix('notifikasi')->group(function () {
        Route::get('/',            [NotifikasiController::class, 'index']);
        Route::get('jumlah',       [NotifikasiController::class, 'jumlah']);
        Route::post('baca-semua',  [NotifikasiController::class, 'bacaSemua']);
        Route::post('{id}/baca',   [NotifikasiController::class, 'baca']);
        Route::delete('{id}',      [NotifikasiController::class, 'hapus']);
    });
});
