<?php

namespace App\Filament\Resources\Dinas;

use App\Filament\Resources\Dinas\Pages\CreateDinas;
use App\Filament\Resources\Dinas\Pages\EditDinas;
use App\Filament\Resources\Dinas\Pages\ListDinas;
use App\Filament\Resources\Dinas\Pages\ViewDinas;
use App\Filament\Resources\Dinas\Schemas\DinasForm;
use App\Filament\Resources\Dinas\Schemas\DinasInfolist;
use App\Filament\Resources\Dinas\Tables\DinasTable;
use App\Models\Dinas;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class DinasResource extends Resource
{
    protected static ?string $model = Dinas::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'tujuan';

    protected static string|UnitEnum|null $navigationGroup = 'Pengajuan & Cuti';

    protected static ?int $navigationSort = 4;
    
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
        return DinasForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DinasInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DinasTable::configure($table);
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
            'index' => ListDinas::route('/'),
            'create' => CreateDinas::route('/create'),
            'view' => ViewDinas::route('/{record}'),
            'edit' => EditDinas::route('/{record}/edit'),
        ];
    }
}
