<?php

namespace App\Filament\Widgets\Operational;

use App\Filament\Resources\RentalReminders\RentalReminderResource;
use App\Filament\Resources\Rentals\RentalResource;
use App\Models\Rental;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Daftar pekerjaan petugas hari ini (data operasional, real time):
 * verifikasi yang menunggu, motor yang diambil/kembali hari ini, dan sewa yang terlambat.
 */
class TodayTasksTable extends TableWidget
{
    protected static bool $isDiscovered = false;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    public const TASKS = [
        'overdue' => 'Terlambat kembali',
        'verify' => 'Verifikasi',
        'pickup' => 'Ambil hari ini',
        'return' => 'Kembali hari ini',
    ];

    /** Satu pesanan bisa masuk lebih dari satu tugas; yang paling mendesak ditampilkan. */
    public static function taskOf(Rental $rental): string
    {
        $now = now();

        return match (true) {
            $rental->status === 'active' && $rental->end_time->lt($now) => 'overdue',
            $rental->status === 'pending_verification' => 'verify',
            $rental->status === 'approved' && $rental->start_time->isToday() => 'pickup',
            default => 'return',
        };
    }

    /** @param  array<int, string>|null  $tasks */
    public static function query(?array $tasks = null): Builder
    {
        $tasks ??= array_keys(self::TASKS);
        [$start, $end] = [today(), today()->endOfDay()];

        return Rental::query()->with(['user', 'bike'])->where(function (Builder $q) use ($tasks, $start, $end) {
            if (in_array('overdue', $tasks, true)) {
                $q->orWhere(fn (Builder $q) => $q->where('status', 'active')->where('end_time', '<', now()));
            }
            if (in_array('verify', $tasks, true)) {
                $q->orWhere('status', 'pending_verification');
            }
            if (in_array('pickup', $tasks, true)) {
                $q->orWhere(fn (Builder $q) => $q->where('status', 'approved')->whereBetween('start_time', [$start, $end]));
            }
            if (in_array('return', $tasks, true)) {
                $q->orWhere(fn (Builder $q) => $q->where('status', 'active')->whereBetween('end_time', [now(), $end]));
            }
        });
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Tugas hari ini')
            ->description('Diurutkan dari yang paling mendesak.')
            ->query(self::query())
            ->modifyQueryUsing(fn (Builder $query) => $query
                // Terlambat dulu, lalu antrean verifikasi (terlama dulu), lalu jadwal hari ini.
                ->orderByRaw("case when status = 'active' and end_time < ? then 0 when status = 'pending_verification' then 1 else 2 end", [now()])
                ->orderByRaw("case when status = 'pending_verification' then created_at when status = 'approved' then start_time else end_time end"))
            ->poll('60s')
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->columns([
                TextColumn::make('tugas')->label('Tugas')->badge()
                    ->state(fn (Rental $record) => self::taskOf($record))
                    ->formatStateUsing(fn (string $state) => self::TASKS[$state])
                    ->color(fn (string $state) => match ($state) {
                        'overdue' => 'danger',
                        'verify' => 'warning',
                        'pickup' => 'success',
                        default => 'info',
                    }),
                TextColumn::make('waktu')->label('Waktu')
                    ->state(fn (Rental $record) => self::timeOf($record)),
                TextColumn::make('booking_code')->label('Booking')->searchable(),
                TextColumn::make('user.name')->label('Penyewa')->searchable(),
                TextColumn::make('user.phone_number')->label('WhatsApp')->placeholder('-')->fontFamily('mono'),
                TextColumn::make('bike.name')->label('Motor'),
            ])
            ->filters([
                SelectFilter::make('tugas')
                    ->label('Jenis tugas')
                    ->options(self::TASKS)
                    ->query(fn (Builder $query, array $data) => filled($data['value'] ?? null)
                        ? $query->whereIn('id', self::query([$data['value']])->select('id'))
                        : $query),
            ])
            ->recordActions([
                Action::make('reminder')
                    ->label('Kirim reminder')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('danger')
                    ->visible(fn (Rental $record) => self::taskOf($record) === 'overdue')
                    ->url(RentalReminderResource::getUrl('index')),
                Action::make('open')
                    ->label('Buka')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (Rental $record) => RentalResource::getUrl('view', ['record' => $record])),
            ])
            ->emptyStateIcon('heroicon-o-check-circle')
            ->emptyStateHeading('Tidak ada tugas yang menunggu hari ini.');
    }

    /** Waktu yang relevan untuk tugasnya, dalam bahasa sehari-hari. */
    private static function timeOf(Rental $rental): string
    {
        $fmt = fn (Carbon $time) => $time->translatedFormat('H:i');

        return match (self::taskOf($rental)) {
            'overdue' => 'telat '.$rental->end_time->locale('id')->diffForHumans(now(), ['syntax' => Carbon::DIFF_ABSOLUTE, 'parts' => 2]),
            'verify' => 'menunggu '.$rental->created_at->locale('id')->diffForHumans(now(), ['syntax' => Carbon::DIFF_ABSOLUTE, 'parts' => 1]),
            'pickup' => 'ambil '.$fmt($rental->start_time),
            default => 'kembali '.$fmt($rental->end_time),
        };
    }

    public static function canView(): bool
    {
        return auth()->user()?->isStaff() ?? false;
    }
}
