<?php

namespace App\Filament\Resources\PolaRotasis\Schemas;

use App\Models\Instansi;
use App\Models\PolaRotasi;
use App\Models\Shift;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

class PolaRotasiForm
{
    /** Cache shift per-request supaya itemLabel & preview tidak query berulang. */
    protected static ?Collection $cacheShift = null;

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Pola')
                    ->description('Template pola rotasi yang bisa di-assign ke banyak karyawan')
                    ->icon('heroicon-o-arrow-path-rounded-square')
                    ->columns(2)
                    ->schema([
                        Select::make('instansi_id')
                            ->label('Instansi')
                            ->relationship('instansi', 'nama')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->default(fn () => Instansi::query()->value('id'))
                            ->columnSpanFull(),

                        TextInput::make('unit_kerja')
                            ->label('Unit Kerja')
                            ->required()
                            ->live(onBlur: true)
                            ->maxLength(255)
                            // unit_kerja adalah kunci yang dipakai opsi --unit
                            // pada generator rotasi. Satu typo berarti polanya
                            // tidak pernah ikut tergenerate, dan tidak ada pesan
                            // error apa pun karena generator cuma tidak menemukan
                            // apa-apa.
                            ->datalist(fn () => PolaRotasi::query()
                                ->whereNotNull('unit_kerja')
                                ->distinct()
                                ->orderBy('unit_kerja')
                                ->pluck('unit_kerja')
                                ->all())
                            ->helperText('Tulis persis sama dengan unit yang sudah ada kalau memang unit yang sama — penulisan berbeda dianggap unit berbeda.'),

                        TextInput::make('nama_pola')
                            ->label('Nama Pola')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Contoh: "Rotasi ICU 3 Shift 4 Hari"')
                            // Dua pola bernama sama dalam satu unit akan tampil
                            // identik di dropdown Shift Karyawan Rotasi — kasus
                            // yang sama dengan JenisCuti.nama di fase 25. Beda
                            // dari Shift, di sini tidak ada alasan struktural
                            // untuk mengizinkannya karena unit_kerja sudah jadi
                            // pembeda tersendiri.
                            ->rule(function (Get $get, ?PolaRotasi $record) {
                                return function (string $attribute, $value, \Closure $fail) use ($get, $record) {
                                    $instansiId = $get('instansi_id');
                                    $unitKerja = $get('unit_kerja');

                                    if (! $value || ! $instansiId || ! $unitKerja) {
                                        return;
                                    }

                                    $kembar = PolaRotasi::where('instansi_id', $instansiId)
                                        ->where('unit_kerja', $unitKerja)
                                        ->where('nama_pola', $value)
                                        ->when($record, fn ($q) => $q->where('id', '!=', $record->id))
                                        ->exists();

                                    if ($kembar) {
                                        $fail('Unit ini sudah punya pola dengan nama yang sama. Pakai nama lain supaya tidak tertukar saat di-assign ke karyawan.');
                                    }
                                };
                            }),

                        Toggle::make('berlaku_saat_libur_nasional')
                            ->label('Tetap Berlaku Saat Libur Nasional')
                            ->default(true)
                            ->live()
                            ->helperText('Aktifkan untuk unit 24 jam (IGD/ICU). Kalau nonaktif, libur nasional otomatis override jadi libur apapun posisi siklusnya.'),

                        Toggle::make('is_active')
                            ->label('Aktif')
                            ->default(true),
                    ]),

                Section::make('Langkah Siklus')
                    ->description('Urutan hari menentukan posisi siklus. Panjang siklus = jumlah langkah di bawah.')
                    ->icon('heroicon-o-queue-list')
                    ->schema([
                        Repeater::make('langkah')
                            ->label('')
                            ->live()
                            ->schema([
                                Toggle::make('libur')
                                    // Label lama cuma "Hari Libur" — tidak jelas
                                    // bahwa OFF berarti hari kerja.
                                    ->label('Hari ini libur?')
                                    ->live()
                                    ->default(false)
                                    ->helperText(fn (Get $get): string => $get('libur')
                                        ? 'LIBUR — karyawan tidak dijadwalkan di posisi siklus ini.'
                                        : 'KERJA — pilih shift-nya di sebelah.'),

                                Select::make('shift_id')
                                    ->label('Shift')
                                    // Dibatasi ke instansi pola. Sebelumnya
                                    // mengambil SEMUA shift dari semua instansi.
                                    ->options(function (Get $get): array {
                                        $instansiId = $get('../../instansi_id');

                                        return Shift::query()
                                            ->when($instansiId, fn ($q) => $q->where('instansi_id', $instansiId))
                                            ->get()
                                            ->mapWithKeys(fn (Shift $shift): array => [$shift->id => $shift->labelLengkap()])
                                            ->all();
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->required(fn (Get $get) => ! $get('libur'))
                                    ->visible(fn (Get $get) => ! $get('libur'))
                                    ->rule(function (Get $get) {
                                        return function (string $attribute, $value, \Closure $fail) use ($get) {
                                            $instansiId = $get('../../instansi_id');

                                            if (! $value || ! $instansiId) {
                                                return;
                                            }

                                            $instansiShift = Shift::whereKey($value)->value('instansi_id');

                                            if ($instansiShift && $instansiShift !== $instansiId) {
                                                $fail('Shift yang dipilih bukan milik instansi pola ini.');
                                            }
                                        };
                                    }),
                            ])
                            ->columns(2)
                            ->addActionLabel('Tambah Hari ke Siklus')
                            ->reorderableWithButtons()
                            ->minItems(1)
                            ->itemLabel(fn (array $state): ?string => self::labelLangkah($state))
                            ->columnSpanFull(),

                        // Item todo lama: form ini cuma menampilkan data mentah,
                        // admin harus membayangkan sendiri hasil jadwalnya.
                        Placeholder::make('preview_siklus')
                            ->label('Preview 14 hari ke depan')
                            ->live()
                            ->content(fn (Get $get) => self::preview($get))
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Cache shift per-request.
     *
     * itemLabel dipanggil sekali per baris tiap Repeater dirender ulang —
     * versi lama memakai Shift::find() di dalamnya, jadi siklus 21 hari berarti
     * 21 query tiap kali toggle disentuh.
     */
    public static function shifts(): Collection
    {
        return self::$cacheShift ??= Shift::query()->get()->keyBy('id');
    }

    protected static function labelLangkah(array $state): string
    {
        if ($state['libur'] ?? false) {
            return 'Libur';
        }

        $shiftId = $state['shift_id'] ?? null;

        if (! $shiftId) {
            return 'Belum dipilih';
        }

        return self::shifts()->get($shiftId)?->labelLengkap() ?? 'Shift tidak ditemukan';
    }

    /**
     * Preview dari `langkah` yang sedang diisi (belum tersimpan).
     *
     * Perhitungannya ada di PolaRotasi::hitungPreviewSiklus() supaya bisa
     * dites sebagai array dan dipakai ulang oleh infolist. Method ini murni
     * merakit HTML-nya.
     */
    protected static function preview(Get $get): HtmlString|string
    {
        $langkah = $get('langkah');

        if (empty($langkah) || ! is_array($langkah)) {
            return 'Tambah minimal satu langkah untuk melihat preview.';
        }

        $hasil = PolaRotasi::hitungPreviewSiklus(
            $langkah,
            $get('instansi_id'),
            (bool) $get('berlaku_saat_libur_nasional'),
        );

        if ($hasil === []) {
            return 'Tambah minimal satu langkah untuk melihat preview.';
        }

        return self::tabelPreview($hasil, count(array_values($langkah)));
    }

    /**
     * Rakit tabel HTML dari hasil PolaRotasi::hitungPreviewSiklus().
     * Dipakai form maupun infolist.
     */
    public static function tabelPreview(array $hasil, int $panjangSiklus): HtmlString
    {
        $baris = '';

        foreach ($hasil as $item) {
            if ($item['libur']) {
                $isi = '<span style="opacity:.6">Libur</span>';
            } elseif ($item['override_libur_nasional']) {
                $isi = '<span style="opacity:.6">Libur — di-override libur nasional</span>';
            } else {
                $isi = self::shifts()->get($item['shift_id'])?->labelLengkap() ?? 'Belum dipilih';
            }

            $tandaLibur = $item['nama_libur']
                ? " <em style=\"opacity:.6\">({$item['nama_libur']})</em>"
                : '';

            $baris .= sprintf(
                '<tr><td style="padding:2px 12px 2px 0">%s</td><td style="padding:2px 12px 2px 0">%s</td><td style="padding:2px 0">%s%s</td></tr>',
                $item['tanggal']->locale('id')->isoFormat('ddd'),
                $item['tanggal']->format('d M'),
                $isi,
                $tandaLibur
            );
        }

        return new HtmlString(
            "<p style=\"margin-bottom:6px\">Panjang siklus: <b>{$panjangSiklus} hari</b>. "
            .'Preview ini menganggap siklus dimulai <b>hari ini</b> — anchor sebenarnya adalah '
            .'tanggal mulai per karyawan di menu Shift Karyawan Rotasi, jadi urutan shift-nya benar '
            .'tapi tanggalnya belum tentu.</p>'
            ."<table style=\"font-size:.9em\">{$baris}</table>"
        );
    }
}
