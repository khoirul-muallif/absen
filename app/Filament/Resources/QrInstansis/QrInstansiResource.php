<?php

namespace App\Filament\Resources\QrInstansis;

use App\Filament\Resources\QrInstansis\Pages\CreateQrInstansi;
use App\Filament\Resources\QrInstansis\Pages\EditQrInstansi;
use App\Filament\Resources\QrInstansis\Pages\ListQrInstansis;
use App\Filament\Resources\QrInstansis\Schemas\QrInstansiForm;
use App\Filament\Resources\QrInstansis\Tables\QrInstansisTable;
use App\Models\QrInstansi;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class QrInstansiResource extends Resource
{
    protected static ?string $model = QrInstansi::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQrCode;

    protected static ?string $recordTitleAttribute = 'kode_qr';

    protected static ?string $navigationLabel = 'QR Instansi';

    protected static ?string $pluralLabel = 'QR Instansi';

    protected static ?string $label = 'QR Instansi';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return QrInstansiForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return QrInstansisTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListQrInstansis::route('/'),
            'create' => CreateQrInstansi::route('/create'),
            'edit'   => EditQrInstansi::route('/{record}/edit'),
        ];
    }
}
