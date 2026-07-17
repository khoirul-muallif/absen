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
                    ->label('Tanggal jadwal saat ini')
                    ->date(),
                TextEntry::make('jadwal.shift.nama_shift')
                    ->label('Shift'),
                TextEntry::make('mode')
                    ->label('Jenis Pengajuan')
                    ->state(fn ($record) => $record->isPindahSendiri() ? 'Pindah tanggal sendiri' : 'Tukar dengan rekan'),
                TextEntry::make('jadwalTujuan.karyawan.nama')
                    ->label('Rekan tujuan')
                    ->visible(fn ($record) => ! $record->isPindahSendiri())
                    ->placeholder('-'),
                TextEntry::make('jadwalTujuan.tanggal')
                    ->label('Tanggal jadwal tujuan')
                    ->date()
                    ->visible(fn ($record) => ! $record->isPindahSendiri()),
                TextEntry::make('tanggal_baru')
                    ->label('Pindah ke tanggal')
                    ->date()
                    ->visible(fn ($record) => $record->isPindahSendiri()),
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
