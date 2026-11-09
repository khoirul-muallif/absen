<?php

/**
 * Audit terjemahan Filament locale `id`.
 *
 * Membandingkan `en` vs `id` di vendor/, lalu mengurangi kunci yang
 * sudah kita tambal di lang/vendor/. Jalankan setelah upgrade Filament.
 *
 * Lihat runbook.md bagian "Terjemahan Filament" untuk konteksnya —
 * khususnya kenapa TIDAK boleh pakai `vendor:publish` utuh.
 */

$root = dirname(__DIR__);

$paket = [
    'filament-panels'  => 'vendor/filament/filament/resources/lang',
    'filament-actions' => 'vendor/filament/actions/resources/lang',
    'filament-forms'   => 'vendor/filament/forms/resources/lang',
    'filament-tables'  => 'vendor/filament/tables/resources/lang',
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

$totalPerluDitambal = 0;
$totalSudahDitambal = 0;
$usang = [];

foreach ($paket as $nama => $relBase) {
    $base = "{$root}/{$relBase}";

    foreach (glob("{$base}/en/*.php") as $fileEn) {
        $namaFile = basename($fileEn);
        $fileId = "{$base}/id/{$namaFile}";
        $fileOverride = "{$root}/lang/vendor/{$nama}/id/{$namaFile}";

        $en = ratakan(require $fileEn);
        $id = file_exists($fileId) ? ratakan(require $fileId) : [];
        $override = file_exists($fileOverride) ? ratakan(require $fileOverride) : [];

        $hilangDiPaket = array_diff_key($en, $id);

        $belumDitambal = array_diff_key($hilangDiPaket, $override);
        $sudahDitambal = array_intersect_key($hilangDiPaket, $override);

        // Override untuk kunci yang SUDAH ada di paket — upstream menyusul,
        // override kita sekarang menimpanya tanpa perlu.
        $overrideUsang = array_intersect_key($override, $id);

        $totalPerluDitambal += count($belumDitambal);
        $totalSudahDitambal += count($sudahDitambal);

        if ($overrideUsang) {
            $usang["{$nama}/{$namaFile}"] = array_keys($overrideUsang);
        }

        if (empty($belumDitambal)) {
            continue;
        }

        echo "\n=== {$nama} / {$namaFile} — " . count($belumDitambal) . " kunci PERLU DITAMBAL ===\n";
        echo "    (tambahkan ke lang/vendor/{$nama}/id/{$namaFile})\n\n";
        foreach ($belumDitambal as $k => $v) {
            echo "  {$k} = " . var_export($v, true) . "\n";
        }
    }
}

if ($usang) {
    echo "\n=== OVERRIDE YANG SUDAH TIDAK PERLU ===\n";
    echo "    Upstream sudah menerjemahkan kunci ini. Hapus dari override\n";
    echo "    kita biar tidak menimpa versi upstream yang mungkin lebih baik.\n\n";
    foreach ($usang as $file => $kunci) {
        echo "  lang/vendor/{$file}\n";
        foreach ($kunci as $k) {
            echo "    - {$k}\n";
        }
    }
}

echo "\n";
echo str_repeat('-', 60) . "\n";
echo "Perlu ditambal : {$totalPerluDitambal}\n";
echo "Sudah ditambal : {$totalSudahDitambal}\n";
echo "Override usang : " . array_sum(array_map('count', $usang)) . "\n";

if ($totalPerluDitambal === 0 && empty($usang)) {
    echo "\nSemua kunci tertangani.\n";
}
