<?php

namespace App\Filament\Resources\HariLiburs\Tables;

use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class HariLibursTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tanggal')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('nama')
                    ->searchable()
                    ->weight('medium'),
                TextColumn::make('keterangan')
                    ->limit(40)
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_cuti_bersama')
                    ->label('Cuti bersama')
                    ->boolean()
                    ->tooltip('Penanda saja — belum dibedakan dari libur nasional oleh generator jadwal maupun rekap harian.'),
                TextColumn::make('instansi.nama')
                    ->label('Instansi')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('tanggal')
            ->filters([
                SelectFilter::make('instansi_id')
                    ->label('Instansi')
                    ->relationship('instansi', 'nama')
                    ->searchable()
                    ->preload(),

                TernaryFilter::make('is_cuti_bersama')
                    ->label('Cuti bersama'),

                // Tabel ini bertambah tiap tahun dan default sort-nya tanggal
                // menaik, jadi libur lama menumpuk di atas. Tanpa filter
                // periode, admin harus scroll untuk sampai ke tahun berjalan.
                Filter::make('rentang_tanggal')
                    ->schema([
                        DatePicker::make('dari')->label('Dari tanggal'),
                        DatePicker::make('sampai')->label('Sampai tanggal'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['dari'] ?? null, fn (Builder $q, $tanggal): Builder => $q->whereDate('tanggal', '>=', $tanggal))
                        ->when($data['sampai'] ?? null, fn (Builder $q, $tanggal): Builder => $q->whereDate('tanggal', '<=', $tanggal)))
                    ->indicateUsing(function (array $data): array {
                        $indikator = [];

                        if ($data['dari'] ?? null) {
                            $indikator[] = 'Dari: '.\Carbon\Carbon::parse($data['dari'])->format('d M Y');
                        }

                        if ($data['sampai'] ?? null) {
                            $indikator[] = 'Sampai: '.\Carbon\Carbon::parse($data['sampai'])->format('d M Y');
                        }

                        return $indikator;
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
