<?php

namespace App\Filament\Resources\PolaRotasis;

use App\Filament\Resources\PolaRotasis\Pages\CreatePolaRotasi;
use App\Filament\Resources\PolaRotasis\Pages\EditPolaRotasi;
use App\Filament\Resources\PolaRotasis\Pages\ListPolaRotasis;
use App\Filament\Resources\PolaRotasis\Schemas\PolaRotasiForm;
use App\Filament\Resources\PolaRotasis\Tables\PolaRotasisTable;
use App\Models\PolaRotasi;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class PolaRotasiResource extends Resource
{
    protected static ?string $model = PolaRotasi::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPathRoundedSquare;

    protected static ?string $navigationLabel = 'Pola Rotasi';

    protected static ?string $pluralLabel = 'Pola Rotasi';

    protected static ?string $label = 'Pola Rotasi';

    protected static string|UnitEnum|null $navigationGroup = 'Master Data';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return PolaRotasiForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PolaRotasisTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListPolaRotasis::route('/'),
            'create' => CreatePolaRotasi::route('/create'),
            'edit'   => EditPolaRotasi::route('/{record}/edit'),
        ];
    }
}
