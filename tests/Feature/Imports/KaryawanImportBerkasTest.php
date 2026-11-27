<?php

// Pasangan KaryawanImportTest: yang di sana memanggil collection() langsung
// dengan Collection buatan (cepat, mengunci aturan validasi), yang di sini
// membaca .xlsx SUNGGUHAN lewat Excel::import().
//
// Dua hal cuma bisa dibuktikan dari file asli: sheet dipilih berdasarkan
// nama (bukan urutan), dan header .xlsx benar-benar terbaca jadi kolom yang
// dikenali importer. Keduanya lolos dari test yang memanggil collection()
// langsung, karena di situ bentuk datanya sudah ditentukan test itu sendiri.
//
// Fixture DIBUAT di sini, bukan file biner yang di-commit — supaya tidak ada
// fixture usang yang diam-diam menguji format template lama.

use App\Exports\KaryawanTemplateExport;
use App\Imports\KaryawanImport;
use App\Models\Instansi;
use App\Models\Karyawan;
use Maatwebsite\Excel\Exceptions\SheetNotFoundException;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

beforeEach(function () {
    $this->instansi = Instansi::factory()->create();
});

afterEach(function () {
    foreach (glob(sys_get_temp_dir() . '/import-test-*.xlsx') as $berkas) {
        @unlink($berkas);
    }
});

/**
 * Menulis .xlsx dengan sheet-sheet yang diberikan, dalam urutan array-nya.
 * Tiap sheet: nama => array baris (baris pertama adalah header).
 */
function buatBerkasXlsx(array $sheets): string
{
    $spreadsheet = new Spreadsheet();
    $spreadsheet->removeSheetByIndex(0);

    foreach ($sheets as $nama => $baris) {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle($nama);
        $sheet->fromArray($baris, null, 'A1');
    }

    $path = sys_get_temp_dir() . '/import-test-' . uniqid() . '.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return $path;
}

/** Header persis seperti KaryawanTemplateDataSheet::headings(). */
function headerTemplate(): array
{
    return [
        'nip', 'nama', 'username', 'email', 'nomor_telepon',
        'status_pegawai', 'tipe_jadwal', 'unit_kerja', 'jabatan',
        'tanggal_bergabung',
    ];
}

function barisXlsx(string $nip, string $nama): array
{
    return [$nip, $nama, null, null, null, 'tetap', 'umum', null, null, null];
}

function sheetPetunjuk(): array
{
    // Bentuknya tidak penting; yang penting dia PUNYA baris, supaya kalau
    // importer keliru memprosesnya hasilnya terlihat.
    return [
        ['Kolom', 'Penjelasan'],
        ['nip', 'Nomor induk pegawai, wajib diisi'],
        ['nama', 'Nama lengkap beserta gelar'],
    ];
}

it('membaca .xlsx dengan urutan sheet seperti template', function () {
    $path = buatBerkasXlsx([
        KaryawanTemplateExport::SHEET_DATA => [
            headerTemplate(),
            barisXlsx('NIP-001', 'Budi Santoso'),
            barisXlsx('NIP-002', 'Siti Aminah'),
        ],
        'Petunjuk' => sheetPetunjuk(),
    ]);

    $import = new KaryawanImport($this->instansi->id, dryRun: false);
    Excel::import($import, $path);

    expect($import->hasil->siap)->toHaveCount(2)
        ->and($import->hasil->ditolak)->toBeEmpty()
        ->and(Karyawan::count())->toBe(2);
});

it('membaca sheet data walau urutannya terbalik', function () {
    // INI yang dulu rusak. Dengan penjaga $sudahDiproses, importer
    // memproses "Petunjuk" lalu menolak semua barisnya — dan sheet data
    // tidak pernah tersentuh.
    $path = buatBerkasXlsx([
        'Petunjuk' => sheetPetunjuk(),
        KaryawanTemplateExport::SHEET_DATA => [
            headerTemplate(),
            barisXlsx('NIP-001', 'Budi Santoso'),
            barisXlsx('NIP-002', 'Siti Aminah'),
        ],
    ]);

    $import = new KaryawanImport($this->instansi->id, dryRun: false);
    Excel::import($import, $path);

    expect($import->hasil->siap)->toHaveCount(2)
        ->and($import->hasil->ditolak)->toBeEmpty()
        ->and(Karyawan::whereIn('nip', ['NIP-001', 'NIP-002'])->count())->toBe(2);
});

it('tidak memproses sheet Petunjuk sama sekali', function () {
    // Sheet Petunjuk punya 2 baris data di bawah headernya. Kalau ikut
    // diproses, keduanya muncul sebagai baris ditolak.
    $path = buatBerkasXlsx([
        KaryawanTemplateExport::SHEET_DATA => [
            headerTemplate(),
            barisXlsx('NIP-001', 'Budi Santoso'),
        ],
        'Petunjuk' => sheetPetunjuk(),
    ]);

    $import = new KaryawanImport($this->instansi->id, dryRun: true);
    Excel::import($import, $path);

    expect($import->hasil->siap)->toHaveCount(1)
        ->and($import->hasil->ditolak)->toBeEmpty()
        ->and($import->hasil->peringatan)->toBeEmpty();
});

it('menolak berkas tanpa sheet bernama Data Karyawan', function () {
    // Admin yang menyalin datanya ke workbook baru. Tanpa penanganan,
    // ini jadi halaman error 500 di panel.
    $path = buatBerkasXlsx([
        'Sheet1' => [
            headerTemplate(),
            barisXlsx('NIP-001', 'Budi Santoso'),
        ],
    ]);

    $import = new KaryawanImport($this->instansi->id, dryRun: false);

    expect(fn () => Excel::import($import, $path))
        ->toThrow(SheetNotFoundException::class);

    expect(Karyawan::count())->toBe(0);
});

it('nomor baris dari .xlsx cocok dengan yang dilihat admin di Excel', function () {
    // Nomor baris dihitung dari index Collection. Lewat file sungguhan,
    // index-nya ditentukan maatwebsite — bukan test ini — jadi inilah yang
    // benar-benar membuktikan angkanya cocok dengan Excel.
    $path = buatBerkasXlsx([
        KaryawanTemplateExport::SHEET_DATA => [
            headerTemplate(),
            barisXlsx('NIP-001', 'Budi Santoso'),
            barisXlsx('', 'Tanpa NIP'),
        ],
        'Petunjuk' => sheetPetunjuk(),
    ]);

    $import = new KaryawanImport($this->instansi->id, dryRun: true);
    Excel::import($import, $path);

    expect($import->hasil->siap[0]['baris'])->toBe(2)
        ->and($import->hasil->ditolak[0]['baris'])->toBe(3);
});
