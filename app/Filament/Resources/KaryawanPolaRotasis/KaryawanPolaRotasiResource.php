<?php

namespace App\Filament\Resources\KaryawanPolaRotasis;

use App\Filament\Resources\KaryawanPolaRotasis\Pages\CreateKaryawanPolaRotasi;
use App\Filament\Resources\KaryawanPolaRotasis\Pages\EditKaryawanPolaRotasi;
use App\Filament\Resources\KaryawanPolaRotasis\Pages\ListKaryawanPolaRotasis;
use App\Filament\Resources\KaryawanPolaRotasis\Schemas\KaryawanPolaRotasiForm;
use App\Filament\Resources\KaryawanPolaRotasis\Tables\KaryawanPolaRotasisTable;
use App\Models\KaryawanPolaRotasi;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class KaryawanPolaRotasiResource extends Resource
{
    protected static ?string $model = KaryawanPolaRotasi::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDateRange;

    protected static ?string $navigationLabel = 'Shift Karyawan Rotasi';

    protected static ?string $pluralLabel = 'Shift Karyawan Rotasi';

    protected static ?string $label = 'Shift Karyawan Rotasi';

    protected static string|UnitEnum|null $navigationGroup = 'Presensi';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return KaryawanPolaRotasiForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return KaryawanPolaRotasisTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListKaryawanPolaRotasis::route('/'),
            'create' => CreateKaryawanPolaRotasi::route('/create'),
            'edit'   => EditKaryawanPolaRotasi::route('/{record}/edit'),
        ];
    }
}
