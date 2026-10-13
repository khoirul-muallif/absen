<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 20px 24px; }
        body { font-family: sans-serif; font-size: 10px; color: #111; }
        h1 { font-size: 16px; text-align: center; margin: 0 0 4px; text-transform: uppercase; }
        .subjudul { text-align: center; font-size: 10px; color: #555; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: left; }
        th { background-color: #16A34A; color: #fff; text-transform: uppercase; font-size: 9px; }
        td { font-size: 9px; }
        .center { text-align: center; }
        .hijau { background-color: #D1FAE5; }
        .merah { background-color: #FEE2E2; }
        .kuning { background-color: #FEF3C7; }
        .abu { background-color: #F3F4F6; }
        .footer { margin-top: 16px; font-size: 8px; color: #888; text-align: right; }
    </style>
</head>
<body>
    @php(\Carbon\Carbon::setLocale('id'))
    <h1>Daftar Presensi{{ $shift ? ' '.$shift->nama_shift : '' }}</h1>
    <div class="subjudul">
        {{ $tanggal->translatedFormat('l, d F Y') }}
        @if ($shift)
            &middot; Shift: {{ $shift->labelLengkap() }}
        @else
            &middot; Semua Shift
        @endif
    </div>

    <table>
        <thead>
            <tr>
                <th class="center">No</th>
                <th>NIP</th>
                <th>Nama Pegawai</th>
                <th>Unit Kerja</th>
                <th>Jabatan</th>
                <th>Status Pegawai</th>
                <th>Jadwal Masuk</th>
                <th>Presensi Masuk</th>
                <th>Keterlambatan</th>
                <th>Presensi Pulang</th>
            </tr>
        </thead>
            <tbody>
            @if ($rows->isEmpty())
                <tr><td colspan="10" class="center">Tidak ada data untuk filter ini.</td></tr>
            @else
                @foreach ($rows as $i => $absensi)
                    <tr>
                        <td class="center">{{ $i + 1 }}</td>
                        <td>{{ $absensi->karyawan->nip }}</td>
                        <td>{{ $absensi->karyawan->nama }}</td>
                        <td>{{ $absensi->karyawan->unit_kerja }}</td>
                        <td>{{ $absensi->karyawan->jabatan }}</td>
                        <td>{{ ucfirst($absensi->karyawan->status_pegawai) }}</td>
                        <td>
                            @if ($absensi->shift)
                                {{ \Carbon\Carbon::parse($tanggal->format('Y-m-d').' '.$absensi->shift->jamMasukString())->format('d M Y H:i') }}
                            @else
                                -
                            @endif
                        </td>
                        <td>{{ $absensi->waktu_masuk?->format('d M Y H:i:s') ?? ucfirst(str_replace('_',' ',$absensi->status)) }}</td>
                        <td class="{{ $absensi->status === 'tepat_waktu' ? 'hijau' : ($absensi->status === 'terlambat' ? 'merah' : 'abu') }}">
                            @if (in_array($absensi->status, ['tepat_waktu','terlambat'], true))
                                @if ($absensi->menitTerlambat() <= 0)
                                    Tepat Waktu
                                @else
                                    Terlambat {{ intdiv($absensi->menitTerlambat(),60) > 0 ? intdiv($absensi->menitTerlambat(),60).' jam ' : '' }}{{ $absensi->menitTerlambat() % 60 }} menit
                                @endif
                            @else
                                {{ ucfirst(str_replace('_',' ',$absensi->status)) }}
                            @endif
                        </td>
                        <td class="{{ ! $absensi->waktu_pulang ? 'kuning' : '' }}">
                            {{ $absensi->waktu_pulang?->format('d M Y H:i:s') ?? 'Belum Presensi Pulang' }}
                        </td>
                    </tr>
                @endforeach
            @endif
            </tbody>
    </table>

    <div class="footer">Dicetak {{ now()->translatedFormat('d F Y H:i') }} WIB</div>
</body>
</html>
