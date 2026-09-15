<?php

namespace App\Filament\Resources\Absensis\Tables;

use Carbon\Carbon;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AbsensisTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('karyawan.nama')
                    ->label('Karyawan')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('shift.nama_shift')
                    ->label('Shift')
                    ->placeholder('-')
                    ->sortable(),

                TextColumn::make('tanggal')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('waktu_masuk')
                    ->label('Masuk')
                    ->dateTime('H:i')
                    ->placeholder('-')
                    ->sortable(),

                TextColumn::make('waktu_pulang')
                    ->label('Pulang')
                    ->dateTime('H:i')
                    ->placeholder('-')
                    ->sortable(),

                IconColumn::make('melebihi_toleransi_bulanan')
                    ->label('Lewat toleransi bulanan')
                    ->boolean()
                    ->trueColor('danger')
                    ->falseColor('gray')
                    ->tooltip('Akumulasi keterlambatan sebulan sudah melewati toleransi shift (penanda KPI).')
                    ->toggleable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'tepat_waktu' => 'success',
                        'terlambat'   => 'warning',
                        'alpha'       => 'danger',
                        'izin'        => 'info',
                        'sakit'       => 'warning',
                        'cuti'        => 'info',
                        'dinas'       => 'info',
                        'libur'       => 'gray',
                        default       => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'tepat_waktu' => 'Tepat Waktu',
                        'terlambat'   => 'Terlambat',
                        'alpha'       => 'Alpha',
                        'izin'        => 'Izin',
                        'sakit'       => 'Sakit',
                        'cuti'        => 'Cuti',
                        'dinas'       => 'Dinas',
                        'libur'       => 'Libur',
                        default       => $state,
                    }),

                ImageColumn::make('foto_masuk')
                    ->label('Foto Masuk')
                    ->circular()
                    ->toggleable(),

                TextColumn::make('keterangan')
                    ->label('Keterangan')
                    ->limit(30)
                    ->toggleable(),

                // Kolom tersembunyi default (bisa ditampilkan via toggle)
                TextColumn::make('latitude_masuk')
                    ->label('Lat. Masuk')
                    ->numeric(decimalPlaces: 7)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('longitude_masuk')
                    ->label('Lng. Masuk')
                    ->numeric(decimalPlaces: 7)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('latitude_pulang')
                    ->label('Lat. Pulang')
                    ->numeric(decimalPlaces: 7)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('longitude_pulang')
                    ->label('Lng. Pulang')
                    ->numeric(decimalPlaces: 7)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('tanggal', 'desc')
            ->filters([
                SelectFilter::make('karyawan_id')
                    ->label('Karyawan')
                    ->relationship('karyawan', 'nama')
                    ->searchable()
                    ->preload(),

                // Tabel ini yang paling sering dibuka untuk operasional harian,
                // tapi sebelumnya sama sekali tidak bisa dipersempit per periode
                // — padahal JadwalsTable sudah punya filter serupa sejak fase 11.
                Filter::make('rentang_tanggal')
                    ->schema([
                        DatePicker::make('dari')
                            ->label('Dari tanggal')
                            ->native(false),
                        DatePicker::make('sampai')
                            ->label('Sampai tanggal')
                            ->native(false),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['dari'] ?? null, fn (Builder $q, $tanggal): Builder => $q->whereDate('tanggal', '>=', $tanggal))
                        ->when($data['sampai'] ?? null, fn (Builder $q, $tanggal): Builder => $q->whereDate('tanggal', '<=', $tanggal)))
                    ->indicateUsing(function (array $data): array {
                        $indikator = [];

                        if ($data['dari'] ?? null) {
                            $indikator[] = 'Dari '.Carbon::parse($data['dari'])->format('d M Y');
                        }

                        if ($data['sampai'] ?? null) {
                            $indikator[] = 'Sampai '.Carbon::parse($data['sampai'])->format('d M Y');
                        }

                        return $indikator;
                    }),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'tepat_waktu' => 'Tepat Waktu',
                        'terlambat'   => 'Terlambat',
                        'alpha'       => 'Alpha',
                        'izin'        => 'Izin',
                        'sakit'       => 'Sakit',
                        'cuti'        => 'Cuti',
                        'dinas'       => 'Dinas',
                        'libur'       => 'Libur',
                    ]),

                SelectFilter::make('shift_id')
                    ->label('Shift')
                    ->relationship('shift', 'nama_shift'),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
