<?php

namespace App\Filament\Resources\KaryawanPolaRotasis\Schemas;

use App\Filament\Resources\PolaRotasis\Schemas\PolaRotasiForm;
use App\Models\KaryawanPolaRotasi;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class KaryawanPolaRotasiInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Assignment')
                    ->icon('heroicon-o-user')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('karyawan.nama')
                            ->label('Karyawan'),
                        TextEntry::make('karyawan.unit_kerja')
                            ->label('Unit kerja')
                            ->placeholder('-'),
                        TextEntry::make('polaRotasi.nama_pola')
                            ->label('Pola rotasi'),
                        TextEntry::make('panjang_siklus')
                            ->label('Panjang siklus')
                            ->state(fn (KaryawanPolaRotasi $record): string => ($record->polaRotasi?->panjangSiklus() ?? 0).' hari')
                            ->badge()
                            ->color(fn (KaryawanPolaRotasi $record): string => ($record->polaRotasi?->panjangSiklus() ?? 0) === 0 ? 'danger' : 'gray'),
                        TextEntry::make('status_periode')
                            ->label('Status')
                            ->badge()
                            ->state(fn (KaryawanPolaRotasi $record): string => self::statusPeriode($record))
                            ->color(fn (string $state): string => match ($state) {
                                'Sedang berlaku' => 'success',
                                'Belum mulai'    => 'warning',
                                'Sudah berakhir' => 'gray',
                                default          => 'gray',
                            }),
                    ]),

                Section::make('Periode & Anchor')
                    ->icon('heroicon-o-calendar-days')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('tanggal_mulai')
                            ->label('Tanggal mulai (anchor siklus)')
                            ->date('d M Y'),
                        TextEntry::make('tanggal_berakhir')
                            ->label('Berlaku sampai')
                            ->date('d M Y')
                            ->placeholder('Sampai diganti'),
                        TextEntry::make('arti_anchor')
                            ->label('Artinya')
                            ->state(fn (KaryawanPolaRotasi $record): string => sprintf(
                                'Hari ke-1 siklus jatuh pada %s. Karyawan lain di pola yang sama dengan tanggal mulai berbeda akan berada di posisi siklus berbeda pada tanggal yang sama — itulah cara semua shift tetap ter-cover (staggered).',
                                $record->tanggal_mulai->format('d M Y')
                            ))
                            ->columnSpanFull(),
                    ]),

                // Inilah yang bikin konsep anchor jadi konkret: preview dihitung
                // memakai tanggal_mulai assignment INI, bukan "hari ini" seperti
                // preview generik di halaman Pola Rotasi (fase 32).
                Section::make('Jadwal 14 Hari ke Depan')
                    ->icon('heroicon-o-queue-list')
                    ->description('Dihitung dari anchor karyawan ini, jadi tanggalnya benar-benar sesuai jadwal yang akan digenerate.')
                    ->schema([
                        TextEntry::make('preview')
                            ->label('')
                            ->state(function (KaryawanPolaRotasi $record): HtmlString|string {
                                $pola = $record->polaRotasi;

                                if (! $pola || $pola->panjangSiklus() === 0) {
                                    return 'Pola ini belum punya langkah siklus, jadi tidak ada yang bisa di-preview.';
                                }

                                // Mulai dari hari ini kalau assignment sudah
                                // berjalan; kalau belum mulai, dari tanggal
                                // mulainya supaya preview-nya bermakna.
                                $mulai = $record->berlakuPada(today())
                                    ? today()
                                    : $record->tanggal_mulai->copy();

                                // Offset posisi disamakan dengan anchor karyawan
                                // ini lewat parameter $mulai.
                                $hasil = $pola->previewSiklus(14, $mulai);

                                // Geser posisi supaya sesuai anchor assignment,
                                // bukan posisi 0 di hari pertama preview.
                                $geser = $record->posisiSiklusPada($mulai);
                                $langkah = array_values($pola->langkah);
                                $panjang = count($langkah);

                                foreach ($hasil as $i => &$item) {
                                    $posisi = ($geser + $i) % $panjang;
                                    $step = $langkah[$posisi];

                                    $item['posisi'] = $posisi;
                                    $item['libur'] = (bool) ($step['libur'] ?? false);
                                    $item['shift_id'] = $item['libur'] ? null : ($step['shift_id'] ?? null);
                                    $item['override_libur_nasional'] = $item['nama_libur'] !== null
                                        && ! $pola->berlaku_saat_libur_nasional
                                        && ! $item['libur'];
                                }
                                unset($item);

                                return PolaRotasiForm::tabelPreview($hasil, $panjang);
                            })
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

    protected static function statusPeriode(KaryawanPolaRotasi $record): string
    {
        $hariIni = today();

        if ($record->tanggal_mulai > $hariIni) {
            return 'Belum mulai';
        }

        if ($record->tanggal_berakhir !== null && $record->tanggal_berakhir < $hariIni) {
            return 'Sudah berakhir';
        }

        return 'Sedang berlaku';
    }
}
