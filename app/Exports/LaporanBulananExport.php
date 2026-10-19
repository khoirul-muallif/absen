<?php

namespace App\Exports;

use App\Models\Absensi;
use App\Models\Izin;
use App\Models\Karyawan;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LaporanBulananExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths
{
    protected int $nomor = 0;

    public function __construct(
        protected int $bulan,
        protected int $tahun,
        protected ?int $instansiId = null,
    ) {
    }

    /**
     * Dipanggil juga dari Page::getRows() buat preview -- supaya preview dan
     * file yang didownload selalu dari agregasi yang sama persis, nggak ada
     * dua jalur query yang bisa menyimpang. Lihat catatan sama di
     * LaporanHarianExport soal kenapa ini penting.
     */
    public static function agregasi(int $bulan, int $tahun, ?int $instansiId): Collection
    {
        $karyawan = Karyawan::query()
            ->where('is_active', true)
            ->when($instansiId, fn ($q) => $q->where('instansi_id', $instansiId))
            ->with('instansi')
            ->orderBy('nama')
            ->get();

        if ($karyawan->isEmpty()) {
            return collect();
        }

        $karyawanIds = $karyawan->pluck('id');

        // Satu query ter-grouping per status, bukan N+1 per karyawan.
        $rekapAbsensi = Absensi::query()
            ->whereIn('karyawan_id', $karyawanIds)
            ->whereYear('tanggal', $tahun)
            ->whereMonth('tanggal', $bulan)
            ->selectRaw('karyawan_id, status, count(*) as total')
            ->groupBy('karyawan_id', 'status')
            ->get()
            ->groupBy('karyawan_id');

        // Izin SENGAJA tidak sync ke Absensi (lihat QUICK_CONTEXT Larangan),
        // jadi dihitung terpisah dari tabel izins sendiri.
        $rekapIzin = Izin::query()
            ->whereIn('karyawan_id', $karyawanIds)
            ->whereYear('tanggal', $tahun)
            ->whereMonth('tanggal', $bulan)
            ->where('status', 'approved')
            ->selectRaw('karyawan_id, count(*) as total')
            ->groupBy('karyawan_id')
            ->pluck('total', 'karyawan_id');

        // Flag kumulatif -- begitu true di satu baris, sisa bulan itu tetap
        // true (akumulasi cuma naik). Cukup cek exists(), tidak perlu ambil
        // baris terakhir.
        $melebihiToleransi = Absensi::query()
            ->whereIn('karyawan_id', $karyawanIds)
            ->whereYear('tanggal', $tahun)
            ->whereMonth('tanggal', $bulan)
            ->where('melebihi_toleransi_bulanan', true)
            ->distinct()
            ->pluck('karyawan_id')
            ->flip();

        return $karyawan->map(function (Karyawan $k) use ($rekapAbsensi, $rekapIzin, $melebihiToleransi) {
            $statusKaryawan = $rekapAbsensi->get($k->id, collect())->pluck('total', 'status');

            $tepatWaktu = (int) $statusKaryawan->get('tepat_waktu', 0);
            $terlambat = (int) $statusKaryawan->get('terlambat', 0);

            return [
                'karyawan' => $k,
                'total_hadir' => $tepatWaktu + $terlambat,
                'terlambat' => $terlambat,
                'alpha' => (int) $statusKaryawan->get('alpha', 0),
                'izin' => (int) $rekapIzin->get($k->id, 0),
                'cuti' => (int) $statusKaryawan->get('cuti', 0),
                'dinas' => (int) $statusKaryawan->get('dinas', 0),
                'melebihi_toleransi' => $melebihiToleransi->has($k->id),
            ];
        });
    }

    public function collection(): Collection
    {
        return self::agregasi($this->bulan, $this->tahun, $this->instansiId);
    }

    public function headings(): array
    {
        return [
            'No', 'NIP', 'Nama Pegawai', 'Unit Kerja', 'Instansi',
            'Total Hadir', 'Terlambat', 'Alpha', 'Izin', 'Cuti', 'Dinas',
            'Melebihi Toleransi Bulanan',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 5, 'B' => 12, 'C' => 28, 'D' => 20, 'E' => 20,
            'F' => 12, 'G' => 12, 'H' => 10, 'I' => 10, 'J' => 10, 'K' => 10,
            'L' => 24,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '16A34A']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
        ];
    }
    public function map($row): array
    {
        return [
            $this->nomor++,
            $row['karyawan']->nip,
            $row['karyawan']->nama,
            $row['karyawan']->unit_kerja,
            $row['karyawan']->instansi->nama ?? '-',
            $row['total_hadir'],
            $row['terlambat'],
            $row['alpha'],      // jangan: $row['alpha'] ?: ''
            $row['izin'],
            $row['cuti'],
            $row['dinas'],
            $row['melebihi_toleransi'] ? 'Ya' : 'Tidak',
        ];
    }
}
