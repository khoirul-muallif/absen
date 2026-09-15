<?php

namespace App\Filament\Resources\Jadwals\Tables;

use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class JadwalsTable
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
                    ->searchable()
                    ->sortable(),
                TextColumn::make('tanggal')
                    ->date()
                    ->sortable(),
                TextColumn::make('jenis')
                    ->badge()
                    // Sebelumnya 'reguler' dan 'libur' sama-sama jatuh ke
                    // default 'gray' — dua jenis yang artinya berlawanan
                    // tampil identik. Kasus yang sama dengan badge status
                    // TukarJadwal di fase 25.
                    ->color(fn (string $state): string => match ($state) {
                        'reguler' => 'success',
                        'piket'   => 'warning',
                        'libur'   => 'gray',
                        'cuti'    => 'info',
                        'dinas'   => 'info',
                        default   => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'reguler' => 'Reguler',
                        'piket'   => 'Piket',
                        'libur'   => 'Libur',
                        'cuti'    => 'Cuti',
                        'dinas'   => 'Dinas',
                        default   => $state,
                    }),
                // Kolom ini sebelumnya tidak muncul di mana pun — tidak di
                // tabel, tidak di form, tidak di filter. Padahal dialah yang
                // menentukan apakah baris ini bertahan saat
                // `jadwal:generate-rotasi --overwrite-generate` dijalankan.
                TextColumn::make('sumber')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'manual' ? 'primary' : 'gray')
                    ->formatStateUsing(fn (string $state): string => $state === 'manual' ? 'Manual' : 'Generate')
                    ->tooltip(fn (string $state): string => $state === 'manual'
                        ? 'Dilindungi dari generate ulang.'
                        : 'Boleh ditimpa oleh jadwal:generate-rotasi --overwrite-generate.')
                    ->sortable(),
                TextColumn::make('keterangan')
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('tanggal', 'asc')
            ->filters([
                SelectFilter::make('karyawan_id')
                    ->label('Karyawan')
                    ->relationship('karyawan', 'nama')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('jenis')
                    ->options([
                        'reguler' => 'Reguler',
                        'piket' => 'Piket',
                        'libur' => 'Libur',
                        'cuti' => 'Cuti',
                        'dinas' => 'Dinas',
                    ]),
                SelectFilter::make('sumber')
                    ->options([
                        'generate' => 'Generate',
                        'manual'   => 'Manual',
                    ]),
                SelectFilter::make('shift_id')
                    ->label('Shift')
                    ->relationship('shift', 'nama_shift'),
                Filter::make('tanggal')
                    ->schema([
                        DatePicker::make('dari_tanggal')->label('Dari tanggal'),
                        DatePicker::make('sampai_tanggal')->label('Sampai tanggal'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['dari_tanggal'] ?? null, fn ($q, $tanggal) => $q->whereDate('tanggal', '>=', $tanggal))
                            ->when($data['sampai_tanggal'] ?? null, fn ($q, $tanggal) => $q->whereDate('tanggal', '<=', $tanggal));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['dari_tanggal'] ?? null) {
                            $indicators[] = 'Dari: '.\Carbon\Carbon::parse($data['dari_tanggal'])->format('d M Y');
                        }
                        if ($data['sampai_tanggal'] ?? null) {
                            $indicators[] = 'Sampai: '.\Carbon\Carbon::parse($data['sampai_tanggal'])->format('d M Y');
                        }

                        return $indicators;
                    }),
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
