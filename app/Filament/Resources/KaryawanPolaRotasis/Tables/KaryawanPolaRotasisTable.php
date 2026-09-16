<?php

namespace App\Filament\Resources\KaryawanPolaRotasis\Tables;

use App\Models\KaryawanPolaRotasi;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class KaryawanPolaRotasisTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('karyawan.nama')
                    ->label('Karyawan')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('karyawan.unit_kerja')
                    ->label('Unit Kerja')
                    ->badge()
                    ->color('info')
                    ->toggleable(),

                TextColumn::make('polaRotasi.nama_pola')
                    ->label('Pola Rotasi')
                    ->badge()
                    ->color('primary')
                    ->sortable()
                    // Nama pola saja bisa ambigu: keputusan fase 32 mengizinkan
                    // nama yang sama di unit berbeda.
                    ->description(fn (KaryawanPolaRotasi $record): ?string => $record->polaRotasi
                        ? 'siklus '.$record->polaRotasi->panjangSiklus().' hari'
                        : null),

                TextColumn::make('tanggal_mulai')
                    ->label('Mulai (Anchor)')
                    ->date('d M Y')
                    ->sortable()
                    ->tooltip('Posisi hari ke-1 siklus dihitung dari tanggal ini.'),

                TextColumn::make('tanggal_berakhir')
                    ->label('Berlaku Sampai')
                    ->date('d M Y')
                    ->sortable()
                    ->placeholder('Sampai diganti'),

                // Menggantikan description 'Aktif' yang cuma menandai
                // tanggal_berakhir null — itu berarti "berlaku sampai diganti",
                // BUKAN "sedang berlaku". Masalah yang sama sudah diperbaiki di
                // KaryawanShift (fase 31).
                TextColumn::make('status_periode')
                    ->label('Status')
                    ->badge()
                    ->state(fn (KaryawanPolaRotasi $record): string => self::statusPeriode($record))
                    ->color(fn (string $state): string => match ($state) {
                        'Sedang berlaku' => 'success',
                        'Belum mulai'    => 'warning',
                        'Sudah berakhir' => 'gray',
                        default          => 'gray',
                    }),

                TextColumn::make('posisi_hari_ini')
                    ->label('Shift hari ini')
                    ->state(function (KaryawanPolaRotasi $record): string {
                        if (! $record->polaRotasi || $record->polaRotasi->panjangSiklus() === 0) {
                            return '-';
                        }

                        if (! $record->berlakuPada(today())) {
                            return '-';
                        }

                        $posisi = $record->posisiSiklusPada(today());
                        $step = array_values($record->polaRotasi->langkah)[$posisi] ?? null;

                        if ($step === null) {
                            return '-';
                        }

                        return ($step['libur'] ?? false) ? 'Libur' : 'Kerja';
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Kerja' => 'success',
                        'Libur' => 'gray',
                        default => 'gray',
                    })
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('tanggal_mulai', 'desc')
            ->filters([
                SelectFilter::make('karyawan_id')
                    ->label('Karyawan')
                    ->relationship('karyawan', 'nama')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('pola_rotasi_id')
                    ->label('Pola Rotasi')
                    ->relationship('polaRotasi', 'nama_pola'),

                Filter::make('sedang_berlaku')
                    ->label('Hanya yang sedang berlaku')
                    ->query(fn (Builder $query): Builder => $query
                        ->whereDate('tanggal_mulai', '<=', today())
                        ->where(fn (Builder $q) => $q
                            ->whereNull('tanggal_berakhir')
                            ->orWhereDate('tanggal_berakhir', '>=', today())))
                    ->toggle(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),

                    // Tidak ada FK RESTRICT ke karyawan_pola_rotasis, jadi
                    // penghapusan aman secara teknis. Konsekuensinya yang nyata:
                    // karyawan rotasi kehilangan sumber jadwalnya, dan
                    // RekapHarian menandainya "jadwal_hilang" (fase 13) — bukan
                    // alpha, bukan libur, melainkan anomali yang perlu dicek
                    // manual.
                    DeleteAction::make()
                        ->modalDescription('Menghapus assignment ini membuat karyawan rotasi tidak punya pola pada periode tersebut: jadwalnya tidak ikut digenerate, dan rekap harian akan menandainya "jadwal_hilang". Kalau tujuannya mengganti pola, lebih aman mengisi tanggal akhir di assignment ini lalu membuat yang baru.'),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
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
