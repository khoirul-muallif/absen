<?php

namespace App\Filament\Resources\KaryawanShifts;

use App\Filament\Resources\KaryawanShifts\Pages\CreateKaryawanShift;
use App\Filament\Resources\KaryawanShifts\Pages\EditKaryawanShift;
use App\Filament\Resources\KaryawanShifts\Pages\ListKaryawanShifts;
use App\Filament\Resources\KaryawanShifts\Pages\ViewKaryawanShift;
use App\Filament\Resources\KaryawanShifts\Schemas\KaryawanShiftForm;
use App\Filament\Resources\KaryawanShifts\Schemas\KaryawanShiftInfolist;
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

    // Sebelumnya OutlinedCalendarDays — sama persis dengan HariLiburResource
    // setelah ikonnya diganti di fase 29.
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $navigationLabel = 'Shift Karyawan Umum';

    protected static ?string $pluralLabel = 'Shift Karyawan Umum';

    protected static ?string $label = 'Shift Karyawan Umum';

    protected static ?string $recordTitleAttribute = 'id';

    protected static string|UnitEnum|null $navigationGroup = 'Manajemen Shift';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return KaryawanShiftForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return KaryawanShiftInfolist::configure($schema);
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
            'view'   => ViewKaryawanShift::route('/{record}'),
            'edit'   => EditKaryawanShift::route('/{record}/edit'),
        ];
    }
}
