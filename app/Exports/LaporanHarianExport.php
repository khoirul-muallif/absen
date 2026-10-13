<?php

namespace App\Exports;

use App\Models\Absensi;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LaporanHarianExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths, WithEvents
{
    /** Dicache dari collection() supaya AfterSheet bisa pakai status asli per baris tanpa query ulang. */
    protected Collection $baris;

    public function __construct(
        protected Carbon $tanggal,
        protected ?int $shiftId = null,
    ) {
    }

    /**
     * Opsi B (keputusan disepakati): filter literal berdasarkan shift_id di
     * Absensi apa adanya. Karyawan cuti/dinas/alpha-tanpa-jadwal otomatis
     * tidak ikut kalau filter shift aktif -- admin pilih "Semua Shift" kalau
     * mau lihat mereka. Lihat diskusi todo.md soal kenapa ini sengaja, bukan
     * kelupaan.
     */
    public function collection(): Collection
    {
        $query = Absensi::whereDate('tanggal', $this->tanggal)
            ->with(['karyawan', 'shift']);

        if ($this->shiftId) {
            $query->where('shift_id', $this->shiftId);
        }

        $this->baris = $query
            ->get()
            ->sortBy(fn (Absensi $a) => $a->karyawan->nama)
            ->values();

        return $this->baris;
    }

    public function headings(): array
    {
        return [
            'No', 'NIP', 'Nama Pegawai', 'Unit Kerja', 'Jabatan', 'Status Pegawai',
            'Jadwal Masuk', 'Presensi Masuk', 'Keterlambatan', 'Presensi Pulang',
        ];
    }

    public function map($absensi): array
    {
        static $no = 0;
        $no++;

        $karyawan = $absensi->karyawan;
        $shift = $absensi->shift;

        return [
            $no,
            $karyawan->nip,
            $karyawan->nama,
            $karyawan->unit_kerja,
            $karyawan->jabatan,
            ucfirst($karyawan->status_pegawai),
            $shift ? $this->formatJadwalMasuk($shift) : '-',
            $absensi->waktu_masuk?->format('d M Y H:i:s') ?? $this->labelStatus($absensi->status),
            $this->labelKeterlambatan($absensi),
            $absensi->waktu_pulang?->format('d M Y H:i:s') ?? 'Belum Presensi Pulang',
        ];
    }

    /**
     * Gabungkan tanggal laporan + jam shift. WAJIB lewat jamMasukString(),
     * bukan $shift->jam_masuk langsung -- lihat komentar di Shift model soal
     * jebakan cast datetime:H:i (fase 14).
     */
    protected function formatJadwalMasuk($shift): string
    {
        return Carbon::parse($this->tanggal->format('Y-m-d').' '.$shift->jamMasukString())
            ->format('d M Y H:i:s');
    }

    protected function labelKeterlambatan(Absensi $absensi): string
    {
        if (! in_array($absensi->status, ['tepat_waktu', 'terlambat'], true)) {
            return $this->labelStatus($absensi->status);
        }

        $menit = $absensi->menitTerlambat();

        if ($menit <= 0) {
            return 'Tepat Waktu';
        }

        $jam = intdiv($menit, 60);
        $sisaMenit = $menit % 60;

        return $jam > 0
            ? "Terlambat {$jam} jam {$sisaMenit} menit"
            : "Terlambat {$sisaMenit} menit";
    }

    protected function labelStatus(string $status): string
    {
        return ucfirst(str_replace('_', ' ', $status));
    }

    public function columnWidths(): array
    {
        return [
            'A' => 5, 'B' => 12, 'C' => 28, 'D' => 22, 'E' => 18, 'F' => 14,
            'G' => 20, 'H' => 20, 'I' => 24, 'J' => 20,
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

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->freezePane('A2');

                foreach ($this->baris as $i => $absensi) {
                    $row = $i + 2; // +1 header, +1 karena index mulai 0

                    $warnaKeterlambatan = match (true) {
                        $absensi->status === 'tepat_waktu' => 'D1FAE5',
                        $absensi->status === 'terlambat' => 'FEE2E2',
                        default => 'F3F4F6',
                    };
                    $sheet->getStyle("I{$row}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB($warnaKeterlambatan);

                    if (! $absensi->waktu_pulang) {
                        $sheet->getStyle("J{$row}")->getFill()
                            ->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()->setRGB('FEF3C7');
                    }
                }
            },
        ];
    }
}
