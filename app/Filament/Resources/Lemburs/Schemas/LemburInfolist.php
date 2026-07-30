<?php

namespace App\Filament\Resources\Lemburs\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class LemburInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Data Pengajuan')
                    ->schema([
                        TextEntry::make('karyawan.nama')
                            ->label('Karyawan'),
                        TextEntry::make('tanggal')
                            ->date(),
                        TextEntry::make('jam_mulai')
                            ->time(),
                        TextEntry::make('jam_selesai')
                            ->time(),
                        TextEntry::make('alasan')
                            ->placeholder('-')
                            ->columnSpanFull(),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'pending' => 'warning',
                                'approved' => 'success',
                                'rejected' => 'danger',
                                default => 'gray',
                            }),
                    ]),
                Section::make('Approval')
                    ->schema([
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
                    ]),
            ]);
    }
}
