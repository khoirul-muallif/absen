<?php

namespace App\Filament\Resources\TukarJadwals\Schemas;

use App\Models\Jadwal;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class TukarJadwalForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('jadwal_id')
                    ->label('Jadwal milik pengaju')
                    ->options(fn () => self::opsiJadwal())
                    ->searchable()
                    ->required(),
                Select::make('jadwal_tujuan_id')
                    ->label('Jadwal tujuan (rekan)')
                    ->options(fn () => self::opsiJadwal())
                    ->searchable()
                    ->different('jadwal_id')
                    ->required(),
                Textarea::make('alasan')
                    ->required()
                    ->columnSpanFull(),
            ]);
    }

    protected static function opsiJadwal(): array
    {
        return Jadwal::with(['karyawan', 'shift'])
            ->orderByDesc('tanggal')
            ->get()
            ->mapWithKeys(fn (Jadwal $jadwal) => [
                $jadwal->id => "{$jadwal->karyawan->nama} — {$jadwal->tanggal->format('d M Y')} ({$jadwal->shift->nama_shift})",
            ])
            ->toArray();
    }
}
