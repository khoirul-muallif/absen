<?php

namespace App\Exports;

use App\Exports\Sheets\KaryawanTemplateDataSheet;
use App\Exports\Sheets\KaryawanTemplatePetunjukSheet;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;



/**
 * Template kosong untuk import karyawan massal (fase 82).
 *
 * Dua sheet: "Data Karyawan" (header + validasi dropdown, tanpa baris isi)
 * dan "Petunjuk" (penjelasan tiap kolom + contoh pengisian).
 *
 * Contoh baris SENGAJA tidak ditaruh di sheet data. Admin yang lupa
 * menghapusnya akan mengimpor karyawan fiktif, dan importer tidak punya
 * cara andal membedakan contoh dari data asli — menyaring berdasarkan NIP
 * sentinel cuma memindahkan masalah ke admin yang kebetulan punya NIP itu.
 */
class KaryawanTemplateExport implements WithMultipleSheets, Export
{
    /**
     * Nama sheet data. Dirujuk title() milik sheet-nya DAN KaryawanImport —
     * mengganti nama di sini otomatis ikut di keduanya. Sebelumnya nama ini
     * cuma ada di title(), dan importer mengandalkan "sheet pertama adalah
     * sheet data" yang tidak dijamin apa pun.
     */
    public const SHEET_DATA = 'Data Karyawan';

    public function __construct(protected array $unitKerjaTersedia = [])
    {
    }

    public function sheets(): array
    {
        return [
            new KaryawanTemplateDataSheet(),
            new KaryawanTemplatePetunjukSheet($this->unitKerjaTersedia),
        ];
    }
}
