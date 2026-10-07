<?php

namespace App\Filament\Resources\Bikes;

use App\Filament\Resources\Bikes\Pages\CreateBike;
use App\Filament\Resources\Bikes\Pages\EditBike;
use App\Filament\Resources\Bikes\Pages\ListBike;
use App\Models\Bike;
use App\Support\Format;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BikeResource extends Resource
{
    protected static ?string $model = Bike::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    protected static string|\UnitEnum|null $navigationGroup = 'Owner';

    protected static ?string $navigationLabel = 'Manajemen Katalog';

    protected static ?string $modelLabel = 'Motor';

    protected static ?string $pluralModelLabel = 'Motor';

    protected static ?string $recordTitleAttribute = 'name';

    public const STATUS_LABELS = [
        'available' => 'Tersedia',
        'maintenance' => 'Perawatan',
        'inactive' => 'Nonaktif',
    ];

    public const CATEGORY_LABELS = [
        'matic' => 'Matic',
        'manual' => 'Manual',
        'sport' => 'Sport',
    ];

    /** Hanya Super Admin (menu ikut tersembunyi untuk admin). */
    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(['default' => 1, 'lg' => 12])->columnSpanFull()->schema([
                Section::make('Data motor')->columnSpan(['default' => 1, 'lg' => 8])->columns(['default' => 1, 'md' => 2, 'xl' => 3])->components([
                    TextInput::make('name')->label('Nama')->required()->maxLength(255),
                    TextInput::make('brand')->label('Merk')->required()->maxLength(100),
                    TextInput::make('license_plate')->label('Plat nomor')
                        ->required()->maxLength(20)->unique(ignoreRecord: true)
                        ->dehydrateStateUsing(fn ($state) => strtoupper(trim((string) $state))),
                    Select::make('category')->label('Kategori')
                        ->options(self::CATEGORY_LABELS)->required(),
                    TextInput::make('cc')->label('Kapasitas mesin (cc)')
                        ->numeric()->minValue(50)->maxValue(2000),
                    TextInput::make('year')->label('Tahun')
                        ->numeric()->minValue(1990)->maxValue((int) date('Y') + 1),
                    TextInput::make('color')->label('Warna kendaraan')->maxLength(50)
                        ->placeholder('Contoh: Hitam doff'),
                    TextInput::make('daily_rate')->label('Tarif motor per 24 jam')
                        ->numeric()->prefix('Rp')->minValue(1)->required()->live(onBlur: true),
                    Placeholder::make('late_fee_preview')->label('Denda keterlambatan per jam')
                        ->content(fn (Get $get): string => filled($get('daily_rate'))
                            ? Format::rupiah(Bike::calculateHourlyRate($get('daily_rate')))
                            : 'Isi tarif motor terlebih dahulu')
                        ->helperText('Otomatis 20% dari tarif 24 jam, dibulatkan ke Rp1.000 terdekat.'),
                ]),

                Section::make('Status & foto')->columnSpan(['default' => 1, 'lg' => 4])->columns(1)->components([
                    Select::make('status')->label('Status')
                        ->options(self::STATUS_LABELS)->default('available')->required()
                        ->helperText('Hanya kondisi fisik. Ketersediaan per tanggal dihitung dari data pesanan.'),
                    FileUpload::make('photo')->label('Foto')
                        ->image()->disk(Bike::IMAGE_DISK)->directory('bikes')->maxSize(2048)
                        ->panelLayout('integrated')->panelAspectRatio('4:3')->imagePreviewHeight('280'),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                ImageColumn::make('photo')->label('')->disk(Bike::IMAGE_DISK)->square()->imageSize(40),
                TextColumn::make('name')->label('Motor')
                    ->description(fn (Bike $record) => $record->brand)
                    ->searchable(['name', 'brand'])->sortable(),
                TextColumn::make('license_plate')->label('Plat')->searchable()
                    ->fontFamily('mono'),
                TextColumn::make('category')->label('Kategori')
                    ->formatStateUsing(fn (string $state) => self::CATEGORY_LABELS[$state] ?? $state),
                TextColumn::make('color')->label('Warna')->placeholder('-')->toggleable(),
                TextColumn::make('daily_rate')->label('Tarif/24 jam')->sortable()
                    ->formatStateUsing(fn ($state) => Format::rupiah($state)),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (string $state) => self::STATUS_LABELS[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'available' => 'success',
                        'maintenance' => 'warning',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(self::STATUS_LABELS),
                SelectFilter::make('category')->label('Kategori')->options(self::CATEGORY_LABELS),
                TrashedFilter::make()->label('Motor terhapus'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
            ]);
    }

    /** Supaya motor yang di-soft-delete tetap bisa dilihat dan dipulihkan lewat filter. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBike::route('/'),
            'create' => CreateBike::route('/create'),
            'edit' => EditBike::route('/{record}/edit'),
        ];
    }
}
