<?php

/**
 * Override terjemahan Filament yang BELUM ada di paket, bukan salinan
 * lengkapnya. Laravel menggabungkan file ini ke atas terjemahan bawaan
 * (FileLoader::loadNamespaceOverrides, pakai array_replace_recursive),
 * jadi kunci yang tidak ditulis di sini tetap mengambil dari paket —
 * termasuk perbaikan yang datang di versi Filament berikutnya.
 *
 * JANGAN diganti dengan hasil `vendor:publish` utuh: begitu seluruh file
 * disalin, perbaikan terjemahan dari upstream tidak akan pernah terpakai
 * lagi untuk paket ini.
 *
 * Cara mencari kunci yang hilang setelah upgrade Filament: lihat runbook.
 */

return [
    'column_manager' => [
        'actions' => [
            'reorder' => [
                'label' => 'Ubah urutan kolom',
            ],
        ],
    ],

    'columns' => [
        'icon' => [
            'boolean' => [
                'true' => 'Ya',
                'false' => 'Tidak',
            ],
        ],
    ],

    'actions' => [
        'reorder_record' => [
            'label' => 'Ubah urutan item :key',
        ],
        'toggle_record_content' => [
            'label' => 'Buka/tutup item :key',
        ],
    ],

    'loading' => 'Memuat...',

    'result_count' => '{0} Tidak ada hasil|{1} :count hasil|[2,*] :count hasil',
];
