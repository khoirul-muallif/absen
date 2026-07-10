<?php

namespace App\Filament\Resources\Dinas\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class DinasInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('karyawan.nama')
                    ->label('Karyawan'),
                TextEntry::make('tanggal_mulai')
                    ->date(),
                TextEntry::make('tanggal_selesai')
                    ->date(),
                TextEntry::make('tujuan'),
                TextEntry::make('keperluan')
                    ->columnSpanFull(),
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
