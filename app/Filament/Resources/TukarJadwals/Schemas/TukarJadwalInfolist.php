<?php

namespace App\Filament\Resources\TukarJadwals\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class TukarJadwalInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('jadwal.karyawan.nama')
                    ->label('Pengaju'),
                TextEntry::make('jadwal.tanggal')
                    ->label('Tanggal jadwal pengaju')
                    ->date(),
                TextEntry::make('jadwal.shift.nama_shift')
                    ->label('Shift pengaju'),
                TextEntry::make('jadwalTujuan.karyawan.nama')
                    ->label('Rekan tujuan'),
                TextEntry::make('jadwalTujuan.tanggal')
                    ->label('Tanggal jadwal tujuan')
                    ->date(),
                TextEntry::make('jadwalTujuan.shift.nama_shift')
                    ->label('Shift tujuan'),
                TextEntry::make('alasan')
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
