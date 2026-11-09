<?php

namespace App\Filament\Resources\Lemburs;

use App\Filament\Resources\Lemburs\Pages\CreateLembur;
use App\Filament\Resources\Lemburs\Pages\EditLembur;
use App\Filament\Resources\Lemburs\Pages\ListLemburs;
use App\Filament\Resources\Lemburs\Pages\ViewLembur;
use App\Filament\Resources\Lemburs\Schemas\LemburForm;
use App\Filament\Resources\Lemburs\Schemas\LemburInfolist;
use App\Filament\Resources\Lemburs\Tables\LembursTable;
use App\Models\Lembur;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class LemburResource extends Resource
{
    protected static ?string $model = Lembur::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Pengajuan & Cuti';

    protected static ?int $navigationSort = 3;
    
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
        return LemburForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return LemburInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LembursTable::configure($table);
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
            'index' => ListLemburs::route('/'),
            'create' => CreateLembur::route('/create'),
            'view' => ViewLembur::route('/{record}'),
            'edit' => EditLembur::route('/{record}/edit'),
        ];
    }
}
