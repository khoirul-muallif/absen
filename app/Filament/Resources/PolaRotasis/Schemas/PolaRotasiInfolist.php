<?php

namespace App\Filament\Resources\PolaRotasis\Schemas;

use App\Models\KaryawanPolaRotasi;
use App\Models\PolaRotasi;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class PolaRotasiInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Pola')
                    ->icon('heroicon-o-arrow-path-rounded-square')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('nama_pola')
                            ->label('Nama pola'),
                        TextEntry::make('unit_kerja')
                            ->label('Unit kerja')
                            ->badge()
                            ->color('info')
                            ->helperText('Nilai ini yang dipakai opsi --unit saat jadwal rotasi dibuat per unit.'),
                        TextEntry::make('instansi.nama')
                            ->label('Instansi'),
                        TextEntry::make('panjang_siklus')
                            ->label('Panjang siklus')
                            ->state(fn (PolaRotasi $record): string => $record->panjangSiklus().' hari')
                            ->badge()
                            ->color(fn (PolaRotasi $record): string => $record->panjangSiklus() === 0 ? 'danger' : 'gray'),
                        IconEntry::make('is_active')
                            ->label('Aktif')
                            ->boolean(),
                        IconEntry::make('berlaku_saat_libur_nasional')
                            ->label('Tetap saat libur nasional')
                            ->boolean(),

                        TextEntry::make('arti_libur_nasional')
                            ->label('Artinya')
                            ->state(fn (PolaRotasi $record): string => $record->berlaku_saat_libur_nasional
                                ? 'Unit 24 jam: karyawan tetap dijadwalkan sesuai posisi siklus walau tanggalnya libur nasional.'
                                : 'Libur nasional menimpa siklus: apa pun posisi siklusnya, karyawan dijadwalkan libur pada tanggal tersebut.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Urutan Siklus')
                    ->icon('heroicon-o-queue-list')
                    ->description('Urutan ini berulang terus. Posisi 0 jatuh pada tanggal mulai masing-masing karyawan.')
                    ->schema([
                        TextEntry::make('daftar_langkah')
                            ->label('')
                            ->state(function (PolaRotasi $record): HtmlString|string {
                                $langkah = is_array($record->langkah) ? array_values($record->langkah) : [];

                                if ($langkah === []) {
                                    return 'Pola ini belum punya langkah sama sekali — generator akan melewatinya.';
                                }

                                $baris = '';

                                foreach ($langkah as $posisi => $step) {
                                    $isi = ($step['libur'] ?? false)
                                        ? '<span style="opacity:.6">Libur</span>'
                                        : (PolaRotasiForm::shifts()->get($step['shift_id'] ?? null)?->labelLengkap() ?? 'Belum dipilih');

                                    $baris .= sprintf(
                                        '<tr><td style="padding:2px 12px 2px 0;opacity:.6">Hari ke-%d</td><td style="padding:2px 0">%s</td></tr>',
                                        $posisi + 1,
                                        $isi
                                    );
                                }

                                return new HtmlString("<table style=\"font-size:.9em\">{$baris}</table>");
                            })
                            ->columnSpanFull(),
                    ]),

                Section::make('Preview 14 Hari')
                    ->icon('heroicon-o-calendar-days')
                    ->collapsed()
                    ->schema([
                        TextEntry::make('preview')
                            ->label('')
                            ->state(fn (PolaRotasi $record): HtmlString|string => $record->panjangSiklus() === 0
                                ? 'Tidak ada langkah untuk di-preview.'
                                : PolaRotasiForm::tabelPreview($record->previewSiklus(), $record->panjangSiklus()))
                            ->columnSpanFull(),
                    ]),

                // Bagian yang bikin konsep siklus jadi konkret: menunjukkan
                // posisi siklus tiap karyawan HARI INI. Staggered start (dua
                // karyawan di pola sama dengan tanggal_mulai berbeda) baru
                // kelihatan artinya di sini.
                Section::make('Dipakai Oleh')
                    ->icon('heroicon-o-users')
                    ->schema([
                        TextEntry::make('karyawan_terassign')
                            ->label('')
                            ->state(function (PolaRotasi $record): HtmlString|string {
                                $assignments = KaryawanPolaRotasi::with('karyawan')
                                    ->where('pola_rotasi_id', $record->id)
                                    ->orderBy('tanggal_mulai')
                                    ->get();

                                if ($assignments->isEmpty()) {
                                    return 'Belum ada karyawan yang di-assign ke pola ini. Assign lewat menu Shift Karyawan Rotasi.';
                                }

                                $panjang = $record->panjangSiklus();
                                $baris = '';

                                foreach ($assignments as $item) {
                                    $posisiHariIni = $panjang > 0
                                        ? $item->posisiSiklusPada(today())
                                        : null;

                                    $isiHariIni = '-';

                                    if ($posisiHariIni !== null) {
                                        $step = array_values($record->langkah)[$posisiHariIni] ?? null;

                                        if ($step !== null) {
                                            $isiHariIni = ($step['libur'] ?? false)
                                                ? 'Libur'
                                                : (PolaRotasiForm::shifts()->get($step['shift_id'] ?? null)?->labelLengkap() ?? 'Belum dipilih');
                                        }
                                    }

                                    $baris .= sprintf(
                                        '<tr><td style="padding:2px 12px 2px 0">%s</td><td style="padding:2px 12px 2px 0;opacity:.6">mulai %s</td><td style="padding:2px 0">hari ini: <b>%s</b></td></tr>',
                                        $item->karyawan?->nama ?? 'karyawan terhapus',
                                        $item->tanggal_mulai?->format('d M Y') ?? '-',
                                        $isiHariIni
                                    );
                                }

                                return new HtmlString("<table style=\"font-size:.9em\">{$baris}</table>");
                            })
                            ->helperText('Selama masih ada karyawan di sini, pola tidak bisa dihapus.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Sistem')
                    ->icon('heroicon-o-clock')
                    ->columns(2)
                    ->collapsed()
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Dibuat')
                            ->dateTime('d M Y H:i'),
                        TextEntry::make('updated_at')
                            ->label('Terakhir diubah')
                            ->dateTime('d M Y H:i'),
                    ]),
            ]);
    }
}
