<?php

namespace App\Filament\Resources\Izins;

use App\Filament\Resources\Izins\Pages\CreateIzin;
use App\Filament\Resources\Izins\Pages\EditIzin;
use App\Filament\Resources\Izins\Pages\ListIzins;
use App\Filament\Resources\Izins\Pages\ViewIzin;
use App\Filament\Resources\Izins\Schemas\IzinForm;
use App\Filament\Resources\Izins\Schemas\IzinInfolist;
use App\Filament\Resources\Izins\Tables\IzinsTable;
use App\Models\Izin;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class IzinResource extends Resource
{
    protected static ?string $model = Izin::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Pengajuan & Cuti';

    protected static ?int $navigationSort = 2;
    /**
     * Badge jumlah pengajuan yang menunggu admin. Dipilih daripada
     * notifikasi per-pengajuan (9 Nov 2026): dengan 150 karyawan,
     * puluhan cuti menjelang Lebaran akan membanjiri lonceng sampai
     * admin berhenti membacanya. Badge tidak pernah membanjir, dan
     * menjawab pertanyaan yang sebenarnya: "berapa yang menunggu saya".
     */
    public static function getNavigationBadge(): ?string
    {
        $jumlah = static::getModel()::pending()->count();

        return $jumlah > 0 ? (string) $jumlah : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return IzinForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return IzinInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return IzinsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIzins::route('/'),
            'create' => CreateIzin::route('/create'),
            'view' => ViewIzin::route('/{record}'),
            'edit' => EditIzin::route('/{record}/edit'),
        ];
    }
}
