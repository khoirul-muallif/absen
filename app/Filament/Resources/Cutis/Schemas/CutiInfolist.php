<?php

namespace App\Filament\Resources\Cutis\Schemas;

use App\Models\Cuti;
use App\Models\KuotaCuti;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CutiInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Data Pengajuan')
                    ->schema([
                        TextEntry::make('karyawan.nama')
                            ->label('Karyawan'),
                        TextEntry::make('jenisCuti.nama')
                            ->label('Jenis cuti'),
                        TextEntry::make('info_kuota')
                            ->label('Info kuota saat ini')
                            ->state(function ($record) {
                                if (! $record->jenisCuti?->potong_kuota) {
                                    return 'Jenis cuti ini tidak memotong kuota.';
                                }

                                $tahun = $record->tanggal_mulai->year;

                                $kuota = KuotaCuti::untuk(
                                    $record->karyawan_id,
                                    $record->jenis_cuti_id,
                                    $tahun
                                );

                                if (! $kuota) {
                                    return "Belum ada data kuota untuk tahun {$tahun}. "
                                        .'Approve tetap bisa dilakukan, tapi tidak akan memotong kuota.';
                                }

                                $pending = Cuti::hariPendingUntuk(
                                    $record->karyawan_id,
                                    $record->jenis_cuti_id,
                                    $tahun,
                                    $record->id
                                );

                                $ringkas = "Kuota {$tahun}: {$kuota->kuota} · Terpakai: {$kuota->terpakai}"
                                    ." · Sisa: {$kuota->sisa}";

                                if ($pending > 0) {
                                    $efektif = $kuota->sisa - $pending;
                                    $ringkas .= " · Pending lain: {$pending} · Efektif: {$efektif}";
                                }

                                return $ringkas;
                            })
                            ->columnSpanFull(),
                        TextEntry::make('tanggal_mulai')
                            ->date(),
                        TextEntry::make('tanggal_selesai')
                            ->date(),
                        TextEntry::make('jumlah_hari')
                            ->numeric()
                            ->suffix(' hari'),
                    ]),
                Section::make('Detail')
                    ->schema([
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
