<?php

namespace App\Filament\Resources\Staff;

use App\Filament\Resources\Staff\Pages\CreateStaff;
use App\Filament\Resources\Staff\Pages\EditStaff;
use App\Filament\Resources\Staff\Pages\ListStaff;
use App\Models\User;
use App\Support\PhoneNumber;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

class StaffResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'staf';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|\UnitEnum|null $navigationGroup = 'Owner';

    protected static ?string $navigationLabel = 'Manajemen Staf';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Staf';

    protected static ?string $pluralModelLabel = 'Staf';

    protected static ?string $recordTitleAttribute = 'name';

    /** Hanya Super Admin (menu ikut tersembunyi untuk admin, URL langsung dijawab 403). */
    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    /** Hanya akun admin operasional. Customer dan Super Admin tidak ikut tampil. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('role', 'admin');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Akun staf admin')->columns(2)->components([
                TextInput::make('name')->label('Nama')->required()->maxLength(255),

                TextInput::make('email')->label('Email')
                    ->email()->required()->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->dehydrateStateUsing(fn ($state) => Str::lower(trim((string) $state))),

                TextInput::make('phone_number')->label('No. WhatsApp')
                    ->required()->tel()->maxLength(20)
                    ->placeholder('081234567890')
                    ->helperText('Otomatis diubah ke format 62xxx. Dipakai untuk kontak internal.')
                    ->rules([
                        fn (): Closure => function (string $attribute, $value, Closure $fail) {
                            if (PhoneNumber::normalize((string) $value) === null) {
                                $fail('Nomor WhatsApp tidak valid. Contoh: 081234567890.');
                            }
                        },
                    ])
                    ->dehydrateStateUsing(fn ($state) => PhoneNumber::normalize((string) $state) ?? $state),

                TextInput::make('password')->label('Password')
                    ->password()->revealable()->minLength(8)
                    ->required(fn (string $operation) => $operation === 'create')
                    // model User sudah punya cast 'hashed', jadi cukup kirim teks biasa
                    ->dehydrated(fn ($state) => filled($state))
                    ->helperText(fn (string $operation) => $operation === 'edit'
                        ? 'Kosongkan jika tidak ingin mengganti password.'
                        : 'Minimal 8 karakter.'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('email')->label('Email')->searchable(),
                TextColumn::make('phone_number')->label('No. WhatsApp')
                    ->placeholder('-')->fontFamily('mono'),
                TextColumn::make('created_at')->label('Dibuat')->dateTime('d M Y')->sortable(),
            ])
            ->recordActions([
                EditAction::make()->label('Ubah'),

                DeleteAction::make()
                    ->label('Hapus')
                    ->modalHeading('Hapus akun staf?')
                    ->modalDescription('Akun yang sudah punya riwayat verifikasi atau serah terima tidak bisa dihapus.')
                    ->action(function (User $record) {
                        try {
                            $record->delete();
                        } catch (QueryException) {
                            Notification::make()
                                ->title('Staf ini punya riwayat transaksi, jadi tidak bisa dihapus.')
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()->title('Staf dihapus.')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStaff::route('/'),
            'create' => CreateStaff::route('/create'),
            'edit' => EditStaff::route('/{record}/edit'),
        ];
    }
}