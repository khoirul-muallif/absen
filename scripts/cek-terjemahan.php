<?php

$paket = [
    'filament-panels' => 'vendor/filament/filament/resources/lang',
    'filament-forms'  => 'vendor/filament/forms/resources/lang',
    'filament-tables' => 'vendor/filament/tables/resources/lang',
];

function ratakan(array $arr, string $prefix = ''): array
{
    $hasil = [];
    foreach ($arr as $k => $v) {
        $key = $prefix === '' ? (string) $k : "{$prefix}.{$k}";
        if (is_array($v)) {
            $hasil = array_merge($hasil, ratakan($v, $key));
        } else {
            $hasil[$key] = $v;
        }
    }
    return $hasil;
}

foreach ($paket as $nama => $base) {
    foreach (glob("{$base}/en/*.php") as $fileEn) {
        $namaFile = basename($fileEn);
        $fileId = "{$base}/id/{$namaFile}";

        $en = ratakan(require $fileEn);
        $id = file_exists($fileId) ? ratakan(require $fileId) : [];

        $hilang = array_diff_key($en, $id);
        if (empty($hilang)) {
            continue;
        }

        echo "\n=== {$nama} / {$namaFile} — " . count($hilang) . " kunci hilang ===\n";
        foreach ($hilang as $k => $v) {
            echo "  {$k} = " . var_export($v, true) . "\n";
        }
    }
}
