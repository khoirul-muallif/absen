<?php

use App\Exports\Sheets\KaryawanTemplateDataSheet;
use App\Imports\KaryawanImport;

it('header template cocok persis dengan kolom yang dibaca importer', function () {
    // Kalau keduanya menyimpang, admin mengisi kolom yang diabaikan dan
    // importer mencari kolom yang tidak ada — keduanya gagal diam-diam,
    // tanpa error.
    $kolomImporter = (new ReflectionClass(KaryawanImport::class))->getConstant('KOLOM');

    expect((new KaryawanTemplateDataSheet())->headings())->toBe($kolomImporter);
});
