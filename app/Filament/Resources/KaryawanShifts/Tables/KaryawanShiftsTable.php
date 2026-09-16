<?php

namespace App\Filament\Resources\KaryawanShifts\Tables;

use App\Models\KaryawanShift;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class KaryawanShiftsTable
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

                TextColumn::make('shift.nama_shift')
                    ->label('Shift')
                    ->sortable()
                    ->badge()
                    ->color('primary')
                    // Nama shift saja bisa ambigu: "Pagi" IGD dan "Pagi" Rawat
                    // Jalan adalah dua baris berbeda dengan jam berbeda (fase 30).
                    ->description(fn (KaryawanShift $record): ?string => $record->shift
                        ? $record->shift->jam_masuk->format('H:i').'–'.$record->shift->jam_pulang->format('H:i')
                        : null),

                TextColumn::make('shift.jam_masuk')
                    ->label('Jam Masuk')
                    ->time('H:i')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('shift.jam_pulang')
                    ->label('Jam Pulang')
                    ->time('H:i')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('tanggal_berlaku')
                    ->label('Berlaku Mulai')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('tanggal_berakhir')
                    ->label('Berlaku Sampai')
                    ->date('d M Y')
                    ->sortable()
                    ->placeholder('Sampai diganti'),

                // Kolom baru. Sebelumnya kolom tanggal_berakhir punya
                // description 'Aktif' yang cuma menandai tanggal_berakhir null —
                // itu berarti "berlaku sampai diganti", BUKAN "sedang berlaku".
                // Assignment yang mulai bulan depan & open-ended ikut ditandai
                // "Aktif", sementara assignment yang berakhir akhir bulan ini —
                // yang justru sedang berlaku — tidak ditandai apa pun.
                TextColumn::make('status_periode')
                    ->label('Status')
                    ->badge()
                    ->state(fn (KaryawanShift $record): string => self::statusPeriode($record))
                    ->color(fn (string $state): string => match ($state) {
                        'Sedang berlaku' => 'success',
                        'Belum mulai'    => 'warning',
                        'Sudah berakhir' => 'gray',
                        default          => 'gray',
                    }),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('tanggal_berlaku', 'desc')
            ->filters([
                SelectFilter::make('karyawan_id')
                    ->label('Karyawan')
                    ->relationship('karyawan', 'nama')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('shift_id')
                    ->label('Shift')
                    ->relationship('shift', 'nama_shift'),

                // Tabel ini menumpuk assignment lama yang sudah berakhir.
                // Tanpa filter ini admin harus membaca tanggalnya satu per satu
                // untuk tahu mana yang benar-benar berlaku sekarang.
                Filter::make('sedang_berlaku')
                    ->label('Hanya yang sedang berlaku')
                    ->query(fn (Builder $query): Builder => $query
                        ->whereDate('tanggal_berlaku', '<=', today())
                        ->where(fn (Builder $q) => $q
                            ->whereNull('tanggal_berakhir')
                            ->orWhereDate('tanggal_berakhir', '>=', today())))
                    ->toggle(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    protected static function statusPeriode(KaryawanShift $record): string
    {
        $hariIni = today();

        if ($record->tanggal_berlaku > $hariIni) {
            return 'Belum mulai';
        }

        if ($record->tanggal_berakhir !== null && $record->tanggal_berakhir < $hariIni) {
            return 'Sudah berakhir';
        }

        return 'Sedang berlaku';
    }
}
