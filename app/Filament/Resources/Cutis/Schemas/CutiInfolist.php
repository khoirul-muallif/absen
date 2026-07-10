<?php

namespace App\Filament\Resources\Cutis\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class CutiInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('karyawan.nama')
                    ->label('Karyawan'),
                TextEntry::make('jenisCuti.nama')
                    ->label('Jenis cuti'),
                TextEntry::make('tanggal_mulai')
                    ->date(),
                TextEntry::make('tanggal_selesai')
                    ->date(),
                TextEntry::make('jumlah_hari')
                    ->numeric()
                    ->suffix(' hari'),
                TextEntry::make('alasan')
                    ->columnSpanFull(),
                TextEntry::make('lampiran')
                    ->placeholder('-'),
                TextEntry::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
                TextEntry::make('approver.name')
                    ->label('Disetujui oleh')
                    ->placeholder('-'),
                TextEntry::make('approved_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('catatan_approval')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('created_at')
                    ->dateTime(),
            ]);
    }
}
