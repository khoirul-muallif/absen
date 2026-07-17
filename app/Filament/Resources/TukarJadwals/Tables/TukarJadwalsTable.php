<?php

namespace App\Filament\Resources\Jadwals\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
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
                    ->searchable()
                    ->sortable(),
                TextColumn::make('tanggal')
                    ->date()
                    ->sortable(),
                TextColumn::make('jenis')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'piket' ? 'warning' : 'gray'),
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
                SelectFilter::make('jenis')
                    ->options([
                        'reguler' => 'Reguler',
                        'piket' => 'Piket',
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
                            ->when($data['dari_tanggal'], fn ($q, $tanggal) => $q->whereDate('tanggal', '>=', $tanggal))
                            ->when($data['sampai_tanggal'], fn ($q, $tanggal) => $q->whereDate('tanggal', '<=', $tanggal));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['dari_tanggal'] ?? null) {
                            $indicators[] = 'Dari: ' . \Carbon\Carbon::parse($data['dari_tanggal'])->format('d M Y');
                        }
                        if ($data['sampai_tanggal'] ?? null) {
                            $indicators[] = 'Sampai: ' . \Carbon\Carbon::parse($data['sampai_tanggal'])->format('d M Y');
                        }
                        return $indicators;
                    }),
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
}
