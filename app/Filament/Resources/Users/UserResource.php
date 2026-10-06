<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\RelationManagers\RentalsRelationManager;
use App\Models\User;
use App\Support\PhoneNumber;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'users';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|\UnitEnum|null $navigationGroup = 'Owner';

    protected static ?string $navigationLabel = 'Manajemen User';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'User';

    protected static ?string $pluralModelLabel = 'User';

    protected static ?string $recordTitleAttribute = 'name';

    public static function canViewAny(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canView($record): bool
    {
        return static::canViewAny() && $record instanceof User && $record->role === 'customer';
    }

    public static function canEdit($record): bool
    {
        return static::canViewAny()
            && $record instanceof User
            && ! $record->isSuperAdmin()
            && $record->getKey() !== auth()->id();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withCount('rentals')
            ->withCount([
                'rentals as cancelled_rentals_count' => fn (Builder $query) => $query->where('status', 'cancelled'),
                'rentals as no_show_rentals_count' => fn (Builder $query) => $query->where('status', 'no_show'),
            ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Informasi akun')->columnSpanFull()->columns(['default' => 1, 'md' => 2, '2xl' => 3])->components([
                TextInput::make('name')->label('Nama')->required()->maxLength(255),
                TextInput::make('email')->label('Email')->email()->required()->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->dehydrateStateUsing(fn ($state) => Str::lower(trim((string) $state))),
                TextInput::make('phone_number')->label('No. HP')->tel()->maxLength(20)
                    ->placeholder('081234567890')
                    ->rules([
                        fn (): Closure => function (string $attribute, $value, Closure $fail): void {
                            if (filled($value) && PhoneNumber::normalize((string) $value) === null) {
                                $fail('Nomor HP tidak valid. Contoh: 081234567890.');
                            }
                        },
                    ])
                    ->dehydrateStateUsing(fn ($state) => filled($state) ? PhoneNumber::normalize((string) $state) : null),
                Select::make('role')->label('Role')
                    ->options(fn (string $operation): array => $operation === 'create'
                        ? ['admin' => 'Admin']
                        : ['customer' => 'Customer', 'admin' => 'Admin'])
                    ->default('admin')
                    ->disabled(fn (string $operation): bool => $operation === 'create')
                    ->required(),
                Toggle::make('is_active')->label('Akun aktif')->default(true)->required(),
                TextInput::make('password')->label('Password baru')
                    ->password()->revealable()->minLength(8)
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn ($state): bool => filled($state))
                    ->helperText(fn (string $operation): ?string => $operation === 'edit'
                        ? 'Kosongkan jika password tidak ingin diganti.'
                        : 'Password sementara untuk staf baru.'),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Ringkasan customer')->columnSpanFull()->columns(4)->components([
                \Filament\Infolists\Components\TextEntry::make('rentals_count')->label('Jumlah pesanan')->numeric(),
                \Filament\Infolists\Components\TextEntry::make('cancelled_rentals_count')->label('Pesanan batal')->numeric(),
                \Filament\Infolists\Components\TextEntry::make('no_show_rentals_count')->label('No-show')->numeric(),
                \Filament\Infolists\Components\TextEntry::make('created_at')->label('Tanggal daftar')->dateTime('d M Y'),
                \Filament\Infolists\Components\TextEntry::make('name')->label('Nama'),
                \Filament\Infolists\Components\TextEntry::make('email')->label('Email'),
                \Filament\Infolists\Components\TextEntry::make('phone_number')->label('No. HP'),
                \Filament\Infolists\Components\TextEntry::make('is_active')->label('Status')->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Aktif' : 'Nonaktif')
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')->columns([
            TextColumn::make('name')->label('Nama')->searchable()->sortable(),
            TextColumn::make('email')->label('Email')->searchable(),
            TextColumn::make('phone_number')->label('No. HP')->searchable()->placeholder('-')
                ->url(fn (User $record): ?string => ($phone = PhoneNumber::normalize($record->phone_number))
                    ? 'https://wa.me/'.$phone
                    : null)
                ->openUrlInNewTab()
                ->color('success'),
            TextColumn::make('role')->label('Role')->badge()->sortable()
                ->formatStateUsing(fn (string $state): string => match ($state) {
                    'super_admin' => 'Super Admin',
                    'admin' => 'Admin',
                    default => 'Customer',
                })
                ->color(fn (string $state): string => match ($state) {
                    'super_admin' => 'warning',
                    'admin' => 'primary',
                    default => 'gray',
                }),
            TextColumn::make('is_active')->label('Status')->badge()
                ->formatStateUsing(fn (bool $state): string => $state ? 'Aktif' : 'Nonaktif')
                ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
            TextColumn::make('rentals_count')->label('Jumlah pesanan')->sortable(),
            TextColumn::make('created_at')->label('Tanggal daftar')->dateTime('d M Y')->sortable(),
        ])->filters([
            SelectFilter::make('role')->label('Role')->options([
                'customer' => 'Customer',
                'admin' => 'Admin',
                'super_admin' => 'Super Admin',
            ]),
            SelectFilter::make('is_active')->label('Status')->options([1 => 'Aktif', 0 => 'Nonaktif']),
        ])->recordActions([
            ViewAction::make()->label('Detail')
                ->visible(fn (User $record): bool => $record->role === 'customer'),
            EditAction::make()->label('Ubah')
                ->visible(fn (User $record): bool => static::canEdit($record)),
            Action::make('toggleActive')
                ->label(fn (User $record): string => $record->is_active ? 'Nonaktifkan' : 'Aktifkan')
                ->color(fn (User $record): string => $record->is_active ? 'danger' : 'success')
                ->requiresConfirmation()
                ->visible(fn (User $record): bool => ! $record->isSuperAdmin() && $record->getKey() !== auth()->id())
                ->action(function (User $record): void {
                    $record->forceFill(['is_active' => ! $record->is_active])->save();
                    Notification::make()
                        ->title($record->is_active ? 'Akun diaktifkan.' : 'Akun dinonaktifkan.')
                        ->success()->send();
                }),
            Action::make('sendPasswordReset')
                ->label('Kirim link reset')
                ->icon('heroicon-o-envelope')
                ->requiresConfirmation()
                ->visible(fn (User $record): bool => ! $record->isSuperAdmin() && $record->getKey() !== auth()->id())
                ->action(function (User $record): void {
                    $status = Password::sendResetLink(['email' => $record->email]);

                    Notification::make()
                        ->title($status === Password::RESET_LINK_SENT
                            ? 'Link reset password dikirim ke email user.'
                            : 'Link reset password gagal dikirim.')
                        ->color($status === Password::RESET_LINK_SENT ? 'success' : 'danger')
                        ->send();
                }),
        ]);
    }

    public static function getRelations(): array
    {
        return [RentalsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'view' => ViewUser::route('/{record}'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
