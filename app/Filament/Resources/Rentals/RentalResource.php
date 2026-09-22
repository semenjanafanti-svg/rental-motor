<?php

namespace App\Filament\Resources\Rentals;

use App\Filament\Resources\Rentals\Pages\ListRentals;
use App\Filament\Resources\Rentals\Pages\ViewRental;
use App\Models\Rental;
use App\Support\Format;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Navigation\NavigationItem;

class RentalResource extends Resource
{
    protected static ?string $model = Rental::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $modelLabel = 'Pesanan';

    protected static ?string $pluralModelLabel = 'Pesanan';

    protected static ?string $recordTitleAttribute = 'booking_code';

    protected static string|\UnitEnum|null $navigationGroup = 'Operasional';

    protected static ?int $navigationSort = 1;

    public const STATUS_LABELS = [
        'pending_payment' => 'Menunggu pembayaran DP',
        'pending_verification' => 'Menunggu verifikasi',
        'approved' => 'Disetujui',
        'active' => 'Sedang disewa',
        'completed' => 'Selesai',
        'cancelled' => 'Dibatalkan',
        'expired' => 'Kedaluwarsa',
        'no_show' => 'Tidak hadir',
    ];

    public const PAYMENT_LABELS = [
        'unpaid' => 'Belum dibayar',
        'dp_paid' => 'DP terbayar',
        'fully_paid' => 'Lunas',
        'refunded' => 'Dikembalikan',
    ];

    public static function statusColor(string $status): string
    {
        return match ($status) {
            'pending_payment', 'pending_verification' => 'warning',
            'approved', 'active' => 'success',
            'cancelled', 'no_show' => 'danger',
            default => 'gray', // completed, expired
        };
    }

    /** Pesanan dibuat oleh penyewa lewat website, bukan dari panel. */
    public static function canCreate(): bool
    {
        return false;
    }

    /** Badge menu: jumlah pesanan yang menunggu verifikasi admin. */
    public static function getNavigationItems(): array
    {
        $item = fn (string $label, string $icon, int $sort, ?string $tab, ?string $status = null) =>
            NavigationItem::make($label)
                ->group('Operasional')
                ->icon($icon)
                ->sort($sort)
                ->badge(
                    $status ? (Rental::where('status', $status)->count() ?: null) : null,
                    'danger'
                )
                ->url(fn () => static::getUrl('index', $tab ? ['tab' => $tab] : []))
                ->isActiveWhen(fn () => request()->routeIs(static::getRouteBaseName() . '.index')
                    && request()->query('tab') === $tab);

        return [
            $item('Verifikasi', 'heroicon-o-check-circle', 1, 'verif', 'pending_verification'),
            $item('Siap Diambil', 'heroicon-o-key', 2, 'pickup', 'approved'),
            $item('Sedang Disewa', 'heroicon-o-clock', 3, 'active', 'active'),
            $item('Semua Rental', 'heroicon-o-list-bullet', 4, null),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user', 'bike']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('booking_code')->label('Kode')->searchable()->sortable(),
                TextColumn::make('user.name')->label('Penyewa')->searchable(),
                TextColumn::make('bike.name')->label('Motor'),
                TextColumn::make('start_time')->label('Mulai')->dateTime('d M, H:i')->sortable(),
                TextColumn::make('end_time')->label('Batas kembali')->dateTime('d M, H:i'),
                TextColumn::make('total_price')->label('Total')
                    ->formatStateUsing(fn ($state) => Format::rupiah($state)),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (string $state) => self::STATUS_LABELS[$state] ?? $state)
                    ->color(fn (string $state) => self::statusColor($state)),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(self::STATUS_LABELS),
            ])
            ->recordActions([
                ViewAction::make()->label('Periksa'),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Pesanan')->columnSpanFull()->columns(3)->components([
                TextEntry::make('booking_code')->label('Kode')->copyable(),
                TextEntry::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (string $state) => self::STATUS_LABELS[$state] ?? $state)
                    ->color(fn (string $state) => self::statusColor($state)),
                TextEntry::make('payment_status')->label('Pembayaran')->badge()
                    ->formatStateUsing(fn (string $state) => self::PAYMENT_LABELS[$state] ?? $state),
                TextEntry::make('user.name')->label('Penyewa'),
                TextEntry::make('user.phone_number')->label('No. HP'),
                TextEntry::make('bike.name')->label('Motor'),
                TextEntry::make('start_time')->label('Mulai')->dateTime('d M Y, H:i'),
                TextEntry::make('end_time')->label('Batas kembali')->dateTime('d M Y, H:i'),
                TextEntry::make('total_hours')->label('Durasi')
                    ->formatStateUsing(fn ($state) => intdiv((int) $state, 24) . ' hari (' . $state . ' jam)'),
                TextEntry::make('verification.verifier.name')->label('Diverifikasi oleh')
                    ->visible(fn (Rental $record) => filled($record->verification?->verified_at)),
                TextEntry::make('verification.verified_at')->label('Waktu verifikasi')->dateTime('d M Y, H:i')
                    ->visible(fn (Rental $record) => filled($record->verification?->verified_at)),
            ]),

            Section::make('Pembayaran DP (QRIS)')->columnSpanFull()->columns(3)->components([
                TextEntry::make('total_price')->label('Total harga')
                    ->formatStateUsing(fn ($state) => Format::rupiah($state)),
                TextEntry::make('dp_amount')->label('DP yang harus dibayar (cocokkan dengan bukti)')
                    ->formatStateUsing(fn ($state) => Format::rupiah($state))
                    ->weight('bold'),
                TextEntry::make('balance_amount')->label('Sisa pelunasan (di lokasi)')
                    ->formatStateUsing(fn ($state) => Format::rupiah($state)),
                TextEntry::make('expires_at')->label('Batas bayar DP')->dateTime('d M Y, H:i')
                    ->visible(fn (Rental $record) => $record->status === 'pending_payment'),
                TextEntry::make('dp_rejection')->label('Bukti sebelumnya ditolak karena')
                    ->getStateUsing(fn (Rental $record) => $record->payments->firstWhere('type', 'dp')?->rejection_reason)
                    ->visible(fn (Rental $record) => filled($record->payments->firstWhere('type', 'dp')?->rejection_reason)),
            ]),

            Section::make('Bukti pembayaran & dokumen identitas')->columnSpanFull()->columns(3)->components([
                ImageEntry::make('proof_preview')->label('Bukti pembayaran')
                    ->getStateUsing(function (Rental $record) {
                        $payment = $record->payments->firstWhere('type', 'dp');

                        return $payment?->proof_photo ? route('files.payment', $payment) : null;
                    })
                    ->url(function (Rental $record) {
                        $payment = $record->payments->firstWhere('type', 'dp');

                        return $payment?->proof_photo ? route('files.payment', $payment) : null;
                    }, shouldOpenInNewTab: true)
                    ->imageHeight(300)
                    ->placeholder('Belum diunggah'),
                ImageEntry::make('ktp_preview')->label('Foto KTP')
                    ->getStateUsing(fn (Rental $record) => $record->verification
                        ? route('files.verification', [$record->verification, 'ktp']) : null)
                    ->url(fn (Rental $record) => $record->verification
                        ? route('files.verification', [$record->verification, 'ktp']) : null, shouldOpenInNewTab: true)
                    ->imageHeight(300)
                    ->placeholder('-'),
                ImageEntry::make('sim_preview')->label('Foto SIM C')
                    ->getStateUsing(fn (Rental $record) => $record->verification
                        ? route('files.verification', [$record->verification, 'sim']) : null)
                    ->url(fn (Rental $record) => $record->verification
                        ? route('files.verification', [$record->verification, 'sim']) : null, shouldOpenInNewTab: true)
                    ->imageHeight(300)
                    ->placeholder('-'),
                TextEntry::make('verification.rejection_reason')->label('Alasan penolakan dokumen')
                    ->visible(fn (Rental $record) => filled($record->verification?->rejection_reason))
                    ->columnSpanFull(),
            ]),

            Section::make('Catatan')->columnSpanFull()->components([
                TextEntry::make('cancelled_reason')->label('Alasan pembatalan')
                    ->visible(fn (Rental $record) => filled($record->cancelled_reason)),
                TextEntry::make('notes')->label('Catatan')
                    ->visible(fn (Rental $record) => filled($record->notes)),
            ])->visible(fn (Rental $record) => filled($record->cancelled_reason) || filled($record->notes)),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRentals::route('/'),
            'view' => ViewRental::route('/{record}'),
        ];
    }
}
