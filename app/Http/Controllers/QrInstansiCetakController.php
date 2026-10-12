<?php

namespace App\Http\Controllers;

use App\Models\QrInstansi;
use Illuminate\Http\Request;

class QrInstansiCetakController extends Controller
{
    public function __invoke(Request $request, QrInstansi $qrInstansi)
    {
        // Guard `web` = tabel users (admin Filament), terpisah dari karyawan.
        abort_unless($request->user() !== null, 403);

        return view('qr-instansi.cetak', [
            'qr'     => $qrInstansi->load('instansi'),
            'ukuran' => $request->query('ukuran') === 'a4' ? 'A4' : 'A5',
        ]);
    }
}
