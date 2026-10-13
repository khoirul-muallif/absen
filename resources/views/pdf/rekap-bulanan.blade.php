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
    .merah-teks { color: #DC2626; font-weight: bold; }
    .footer { margin-top: 16px; font-size: 8px; color: #888; text-align: right; }
</style>
</head>
<body>
    @php(\Carbon\Carbon::setLocale('id'))
    <h1>Rekap Bulanan Presensi</h1>
    <div class="subjudul">
        {{ \Carbon\Carbon::create()->month($bulan)->translatedFormat('F') }} {{ $tahun }}
        @if ($instansi) &middot; {{ $instansi->nama }} @else &middot; Semua Instansi @endif
    </div>

    <table>
        <thead>
            <tr>
                <th class="center">No</th>
                <th>NIP</th>
                <th>Nama Pegawai</th>
                <th>Unit Kerja</th>
                <th>Instansi</th>
                <th class="center">Hadir</th>
                <th class="center">Terlambat</th>
                <th class="center">Alpha</th>
                <th class="center">Izin</th>
                <th class="center">Cuti</th>
                <th class="center">Dinas</th>
                <th class="center">Lewat Toleransi</th>
            </tr>
        </thead>
            <tbody>
            @if ($rows->isEmpty())
                <tr><td colspan="12" class="center">Tidak ada data untuk filter ini.</td></tr>
            @else
                @foreach ($rows as $i => $baris)
                    <tr>
                        <td class="center">{{ $i + 1 }}</td>
                        <td>{{ $baris['karyawan']->nip }}</td>
                        <td>{{ $baris['karyawan']->nama }}</td>
                        <td>{{ $baris['karyawan']->unit_kerja }}</td>
                        <td>{{ $baris['karyawan']->instansi->nama ?? '-' }}</td>
                        <td class="center">{{ $baris['total_hadir'] }}</td>
                        <td class="center">{{ $baris['terlambat'] }}</td>
                        <td class="center">{{ $baris['alpha'] }}</td>
                        <td class="center">{{ $baris['izin'] }}</td>
                        <td class="center">{{ $baris['cuti'] }}</td>
                        <td class="center">{{ $baris['dinas'] }}</td>
                        <td class="center {{ $baris['melebihi_toleransi'] ? 'merah-teks' : '' }}">
                            {{ $baris['melebihi_toleransi'] ? 'Ya' : 'Tidak' }}
                        </td>
                    </tr>
                @endforeach
            @endif
            </tbody>
    </table>

    <div class="footer">Dicetak {{ now()->translatedFormat('d F Y H:i') }} WIB</div>
</body>
</html>
