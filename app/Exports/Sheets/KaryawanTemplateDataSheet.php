<?php

namespace App\Exports\Sheets;

use App\Models\Karyawan;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class KaryawanTemplateDataSheet implements FromArray, WithTitle, WithHeadings, WithStyles, WithColumnWidths, WithEvents
{
    /** Sampai baris berapa dropdown & format kolom dipasang. */
    protected const BARIS_SIAP = 300;

    public function title(): string
    {
        return 'Data Karyawan';
    }

    public function array(): array
    {
        return [];
    }

    /**
     * Header memakai nama kolom DB apa adanya, bukan label manusia.
     * Importer mencocokkan kolom lewat nama ini, jadi label yang lebih
     * "rapi" justru bikin file hasil edit admin tidak terbaca.
     */
    public function headings(): array
    {
        return [
            'nip',
            'nama',
            'username',
            'email',
            'nomor_telepon',
            'status_pegawai',
            'tipe_jadwal',
            'unit_kerja',
            'jabatan',
            'tanggal_bergabung',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 14, 'B' => 30, 'C' => 18, 'D' => 28, 'E' => 18,
            'F' => 16, 'G' => 14, 'H' => 20, 'I' => 22, 'J' => 18,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $header = [
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ];

        $opsional = $header + [
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '16A34A']],
        ];

        // Kolom wajib lebih gelap. Admin mengisi sambil melihat sheet ini,
        // bukan sambil membaca sheet Petunjuk.
        $wajib = $header + [
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '047857']],
        ];

        return [
            'A1' => $wajib,  'B1' => $wajib,  'C1' => $opsional, 'D1' => $opsional,
            'E1' => $opsional, 'F1' => $wajib, 'G1' => $wajib,  'H1' => $opsional,
            'I1' => $opsional, 'J1' => $opsional,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->freezePane('A2');

                // Kolom wajib ditandai warna, bukan cuma dijelaskan di sheet
                // lain — admin mengisi sambil melihat sheet ini, bukan sambil
                // membaca petunjuk.

                $this->pasangDropdown($sheet, 'F', ['tetap', 'kontrak', 'orientasi', 'magang']);
                $this->pasangDropdown($sheet, 'G', [Karyawan::TIPE_UMUM, Karyawan::TIPE_ROTASI]);

                // nip & tanggal_bergabung dipaksa TEXT. Tanpa ini Excel
                // mengubah "NIP-00001" jadi apa adanya (aman) tapi
                // "2026-01-15" jadi serial number, dan NIP yang kebetulan
                // seluruhnya angka kehilangan nol di depan (00123 -> 123).
                foreach (['A', 'E', 'J'] as $kolom) {
                    $sheet->getStyle("{$kolom}2:{$kolom}" . self::BARIS_SIAP)
                        ->getNumberFormat()
                        ->setFormatCode(NumberFormat::FORMAT_TEXT);
                }
            },
        ];
    }

    /**
     * Dropdown untuk kolom enum. Dua kolom ini tidak bisa ditebak admin
     * dari nama kolomnya, dan typo di sini menolak baris tanpa admin tahu
     * kenapa — "Tetap" bukan "tetap" terlihat benar di mata manusia.
     */
    protected function pasangDropdown(Worksheet $sheet, string $kolom, array $pilihan): void
    {
        for ($baris = 2; $baris <= self::BARIS_SIAP; $baris++) {
            $validasi = $sheet->getCell("{$kolom}{$baris}")->getDataValidation();

            $validasi->setType(DataValidation::TYPE_LIST)
                ->setErrorStyle(DataValidation::STYLE_STOP)
                ->setAllowBlank(false)
                ->setShowInputMessage(true)
                ->setShowErrorMessage(true)
                ->setShowDropDown(true)
                ->setErrorTitle('Nilai tidak dikenali')
                ->setError('Pilih salah satu dari daftar.')
                ->setFormula1('"' . implode(',', $pilihan) . '"');
        }
    }
}
