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

class LemburResource extends Resource
{
    protected static ?string $model = Lembur::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

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
