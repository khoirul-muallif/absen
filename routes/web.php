<?php

use App\Http\Controllers\QrInstansiCetakController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/qr-instansi/{qrInstansi}/cetak', QrInstansiCetakController::class)
    ->name('qr-instansi.cetak');
