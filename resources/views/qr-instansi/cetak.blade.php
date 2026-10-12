<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Cetak QR — {{ $qr->instansi->nama }}</title>
    <style>
        @page { size: {{ $ukuran }} portrait; margin: 12mm; }
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; margin: 0; color: #111; background: #fff; }
        main { text-align: center; padding: 8mm; }
        h1 { font-size: 26pt; margin: 0 0 6mm; }
        .qr { width: 80%; max-width: 140mm; margin: 0 auto; }
        .qr svg { width: 100%; height: auto; display: block; }
        .petunjuk { font-size: 14pt; margin-top: 8mm; }
        .peringatan { background: #b91c1c; color: #fff; font-weight: bold; text-align: center; padding: 6mm; font-size: 16pt; }
        .toolbar { padding: 12px; text-align: center; background: #f3f4f6; }
        .toolbar a, .toolbar button { margin: 0 4px; padding: 8px 14px; font-size: 14px; cursor: pointer; }
        @media print { .toolbar { display: none; } }
    </style>
</head>
<body>
    <div class="toolbar">
        <button onclick="window.print()">Cetak</button>
        <a href="{{ request()->fullUrlWithQuery(['ukuran' => 'a5']) }}">A5</a>
        <a href="{{ request()->fullUrlWithQuery(['ukuran' => 'a4']) }}">A4</a>
        (ukuran sekarang: {{ $ukuran }})
    </div>

    @unless ($qr->isValid())
        {{-- Sengaja ikut tercetak, supaya QR mati tidak bisa tertempel tanpa ketahuan. --}}
        <div class="peringatan">
            QR INI TIDAK BERLAKU ({{ $qr->statusValiditas() }}) — JANGAN DITEMPEL.
            Pemindaian akan ditolak.
        </div>
    @endunless

    <main>
        <h1>{{ $qr->instansi->nama }}</h1>
        <div class="qr">{!! $qr->svg(600) !!}</div>
        <p class="petunjuk">Pindai QR ini lewat aplikasi absensi untuk absen masuk.</p>
    </main>
</body>
</html>
