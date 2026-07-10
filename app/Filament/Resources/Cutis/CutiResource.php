<?php

namespace App\Filament\Resources\Cutis;

use App\Filament\Resources\Cutis\Pages\CreateCuti;
use App\Filament\Resources\Cutis\Pages\EditCuti;
use App\Filament\Resources\Cutis\Pages\ListCutis;
use App\Filament\Resources\Cutis\Pages\ViewCuti;
use App\Filament\Resources\Cutis\Schemas\CutiForm;
use App\Filament\Resources\Cutis\Schemas\CutiInfolist;
use App\Filament\Resources\Cutis\Tables\CutisTable;
use App\Models\Cuti;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CutiResource extends Resource
{
    protected static ?string $model = Cuti::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return CutiForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CutiInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CutisTable::configure($table);
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
            'index' => ListCutis::route('/'),
            'create' => CreateCuti::route('/create'),
            'view' => ViewCuti::route('/{record}'),
            'edit' => EditCuti::route('/{record}/edit'),
        ];
    }
}
