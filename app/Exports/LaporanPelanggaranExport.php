<?php

namespace App\Exports;

use App\Models\Absensi;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LaporanPelanggaranExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths
{
    public function __construct(
        protected int $bulan,
        protected int $tahun,
        protected ?int $instansiId = null,
    ) {
    }

    /**
     * Reuse agregasi dasar dari LaporanBulananExport -- satu sumber
     * kebenaran query (lihat catatan di sana), supaya angka di dua laporan
     * ini tidak pernah bisa menyimpang. Tinggal nambah total menit
     * terlambat, lalu urutkan berdasarkan tingkat pelanggaran (bukan
     * alfabetis) -- yang paling bermasalah tampil duluan.
     */
    public static function agregasi(int $bulan, int $tahun, ?int $instansiId): Collection
    {
        $dasar = LaporanBulananExport::agregasi($bulan, $tahun, $instansiId);

        if ($dasar->isEmpty()) {
            return $dasar;
        }

        $karyawanIds = $dasar->pluck('karyawan.id');

        $totalMenitTerlambat = Absensi::query()
            ->whereIn('karyawan_id', $karyawanIds)
            ->whereYear('tanggal', $tahun)
            ->whereMonth('tanggal', $bulan)
            ->where('status', 'terlambat')
            ->selectRaw('karyawan_id, sum(menit_terlambat) as total')
            ->groupBy('karyawan_id')
            ->pluck('total', 'karyawan_id');

        return $dasar
            ->map(function (array $baris) use ($totalMenitTerlambat) {
                $baris['total_menit_terlambat'] = (int) $totalMenitTerlambat->get($baris['karyawan']->id, 0);
                $baris['skor_pelanggaran'] = $baris['alpha'] + $baris['terlambat'];

                return $baris;
            })
            ->sortByDesc('skor_pelanggaran')
            ->values();
    }

    public function collection(): Collection
    {
        return self::agregasi($this->bulan, $this->tahun, $this->instansiId);
    }

    public function headings(): array
    {
        return [
            'No', 'NIP', 'Nama Pegawai', 'Unit Kerja', 'Instansi',
            'Alpha', 'Terlambat', 'Total Menit Terlambat', 'Melebihi Toleransi Bulanan',
        ];
    }

    public function map($baris): array
    {
        static $no = 0;
        $no++;

        $k = $baris['karyawan'];

        return [
            $no,
            $k->nip,
            $k->nama,
            $k->unit_kerja,
            $k->instansi->nama ?? '-',
            $baris['alpha'],
            $baris['terlambat'],
            $baris['total_menit_terlambat'],
            $baris['melebihi_toleransi'] ? 'Ya' : 'Tidak',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 5, 'B' => 12, 'C' => 28, 'D' => 20, 'E' => 20,
            'F' => 10, 'G' => 12, 'H' => 20, 'I' => 24,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DC2626']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
        ];
    }
}
