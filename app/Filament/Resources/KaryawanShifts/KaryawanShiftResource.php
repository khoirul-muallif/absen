<?php

namespace App\Filament\Resources\KaryawanShifts;

use App\Filament\Resources\KaryawanShifts\Pages\CreateKaryawanShift;
use App\Filament\Resources\KaryawanShifts\Pages\EditKaryawanShift;
use App\Filament\Resources\KaryawanShifts\Pages\ListKaryawanShifts;
use App\Filament\Resources\KaryawanShifts\Schemas\KaryawanShiftForm;
use App\Filament\Resources\KaryawanShifts\Tables\KaryawanShiftsTable;
use App\Models\KaryawanShift;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class KaryawanShiftResource extends Resource
{
    protected static ?string $model = KaryawanShift::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $navigationLabel = 'Jadwal Shift';

    protected static ?string $pluralLabel = 'Jadwal Shift Karyawan';

    protected static ?string $label = 'Jadwal Shift';

    protected static string|UnitEnum|null $navigationGroup = 'Presensi';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return KaryawanShiftForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return KaryawanShiftsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListKaryawanShifts::route('/'),
            'create' => CreateKaryawanShift::route('/create'),
            'edit'   => EditKaryawanShift::route('/{record}/edit'),
        ];
    }
}
