<?php

namespace App\Filament\Resources\RentalReminders;

use App\Filament\Resources\RentalReminders\Pages\ListRentalReminders;
use App\Models\RentalReminder;
use App\Support\ReminderMessage;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RentalReminderResource extends Resource
{
    protected static ?string $model = RentalReminder::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-bell';

    protected static string|\UnitEnum|null $navigationGroup = 'Operasional';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Reminder Hari Ini';

    protected static ?string $modelLabel = 'Reminder';

    protected static ?string $pluralModelLabel = 'Reminder Hari Ini';

    public const TYPE_LABELS = [
        'pickup_confirmation' => 'Konfirmasi pengambilan',
        'return_2h' => 'Pengingat 2 jam sebelum kembali',
        'return_30m' => 'Pengingat 30 menit sebelum kembali',
        'overdue' => 'Terlambat kembali',
    ];

    public static function canCreate(): bool
    {
        return false;
    }

    /** Hanya reminder pending yang jadwalnya sudah tiba, untuk rental yang masih berjalan. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['rental.user', 'rental.bike'])
            ->where('status', 'pending')
            ->where('scheduled_at', '<=', now())
            ->whereHas('rental', fn (Builder $query) => $query->where('status', 'active'));
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'danger';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->orderByRaw("case when type = 'overdue' then 0 else 1 end")
                ->orderBy('scheduled_at'))
            ->poll('60s')
            ->recordClasses(fn (RentalReminder $record) => $record->type === 'overdue' ? 'fi-row-overdue' : null)
            ->columns([
                TextColumn::make('type')->label('Jenis')->badge()
                    ->formatStateUsing(fn (string $state) => self::TYPE_LABELS[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'overdue' => 'danger',
                        'pickup_confirmation' => 'success',
                        default => 'warning',
                    }),
                TextColumn::make('rental.user.name')->label('Penyewa')->searchable(),
                TextColumn::make('rental.user.phone_number')->label('WhatsApp')
                    ->placeholder('-')->fontFamily('mono'),
                TextColumn::make('rental.booking_code')->label('Booking')->searchable(),
                TextColumn::make('rental.bike.name')->label('Motor'),
                TextColumn::make('rental.end_time')->label('Batas kembali')->dateTime('d M, H:i'),
                TextColumn::make('scheduled_at')->label('Jadwal kirim')->dateTime('d M, H:i'),
            ])
            ->recordActions([
                Action::make('whatsapp')
                    ->label('Kirim via WhatsApp')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('success')
                    ->visible(fn (RentalReminder $record) => filled($record->rental->user->phone_number))
                    ->url(fn (RentalReminder $record) => ReminderMessage::waLink($record), shouldOpenInNewTab: true),

                Action::make('markSent')
                    ->label('Tandai terkirim')
                    ->icon('heroicon-o-check')
                    ->color('gray')
                    ->action(function (RentalReminder $record) {
                        // where status pending: klik ganda atau dua admin sekaligus tidak menimpa data
                        $updated = RentalReminder::whereKey($record->id)
                            ->where('status', 'pending')
                            ->update([
                                'status' => 'sent',
                                'sent_by' => auth()->id(),
                                'sent_at' => now(),
                                'message_snapshot' => ReminderMessage::build($record),
                            ]);

                        Notification::make()
                            ->title($updated ? 'Reminder ditandai terkirim.' : 'Reminder ini sudah diproses.')
                            ->success()
                            ->send();
                    }),
            ])
            ->emptyStateIcon('heroicon-o-check-circle')
            ->emptyStateHeading('Tidak ada reminder yang menunggu dikirim.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRentalReminders::route('/'),
        ];
    }
}