<?php

namespace App\Filament\Resources\Users\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class RentalsRelationManager extends RelationManager
{
    protected static string $relationship = 'rentals';

    protected static ?string $title = 'Riwayat Pesanan';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof \App\Models\User
            && $ownerRecord->role === 'customer'
            && parent::canViewForRecord($ownerRecord, $pageClass);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('booking_code')->label('Kode')->searchable(),
            TextColumn::make('bike.name')->label('Motor'),
            TextColumn::make('start_time')->label('Mulai')->dateTime('d M Y, H:i'),
            TextColumn::make('status')->label('Status')->badge(),
            TextColumn::make('total_price')->label('Total'),
        ])->defaultSort('created_at', 'desc')->recordActions([]);
    }
}
