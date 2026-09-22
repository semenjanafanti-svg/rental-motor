<?php

namespace App\Filament\Resources\Rentals\Pages;

use App\Exceptions\PaymentException;
use App\Filament\Resources\Rentals\RentalResource;
use App\Services\HandoverService;
use App\Services\PaymentService;
use App\Support\Format;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewRental extends ViewRecord
{
    protected static string $resource = RentalResource::class;

    public function getTitle(): string
    {
        return 'Pesanan ' . $this->record->booking_code;
    }

    public function getBreadcrumb(): string
    {
        return 'Detail';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->label('Setujui DP & dokumen')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription('Pastikan nominal bukti bayar sesuai dan KTP/SIM valid.')
                ->visible(fn () => $this->record->status === 'pending_verification')
                ->action(fn () => $this->run(
                    PaymentService::class,
                    fn (PaymentService $s) => $s->approveDp($this->record, auth()->user()),
                    'Pesanan disetujui.'
                )),

            Action::make('rejectProof')
                ->label('Tolak bukti bayar')
                ->icon('heroicon-o-banknotes')
                ->color('warning')
                ->modalHeading('Tolak bukti pembayaran')
                ->modalDescription('Penyewa boleh mengunggah ulang bukti bayar. Batas waktu bayar diperpanjang.')
                ->schema([
                    Textarea::make('reason')->label('Alasan (dilihat penyewa)')->required()->maxLength(255),
                ])
                ->visible(fn () => $this->record->status === 'pending_verification')
                ->action(fn (array $data) => $this->run(
                    PaymentService::class,
                    fn (PaymentService $s) => $s->rejectDpProof($this->record, $data['reason']),
                    'Bukti pembayaran ditolak. Penyewa diminta mengunggah ulang.'
                )),

            Action::make('rejectDocuments')
                ->label('Tolak dokumen')
                ->icon('heroicon-o-identification')
                ->color('danger')
                ->modalHeading('Tolak dokumen identitas')
                ->modalDescription('Pesanan dibatalkan dan DP wajib dikembalikan penuh ke penyewa.')
                ->schema([
                    Textarea::make('reason')->label('Alasan (dilihat penyewa)')->required()->maxLength(255),
                ])
                ->visible(fn () => $this->record->status === 'pending_verification')
                ->action(fn (array $data) => $this->run(
                    PaymentService::class,
                    fn (PaymentService $s) => $s->rejectDocuments($this->record, auth()->user(), $data['reason']),
                    'Dokumen ditolak dan pesanan dibatalkan. Kembalikan DP ke penyewa.'
                )),

            Action::make('checkIn')
                ->label('Pelunasan & Check-in')
                ->icon('heroicon-o-key')
                ->color('success')
                ->modalHeading('Catat pelunasan & serah terima motor')
                ->modalDescription(fn () => 'Sisa pelunasan: ' . Format::rupiah($this->record->balance_amount)
                    . '. Pastikan sudah diterima sebelum motor diserahkan.')
                ->schema([
                    Select::make('method')
                        ->label('Metode pelunasan')
                        ->options(['cash' => 'Tunai', 'manual_transfer' => 'QRIS'])
                        ->default('cash')
                        ->required(),
                ])
                ->visible(fn () => $this->record->status === 'approved')
                ->action(fn (array $data) => $this->run(
                    HandoverService::class,
                    fn (HandoverService $s) => $s->checkIn($this->record, auth()->user(), $data['method']),
                    'Pelunasan dicatat, motor diserahkan. Status sewa menjadi aktif.'
                )),

            Action::make('checkOut')
                ->label('Catat Pengembalian')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('primary')
                ->modalHeading('Catat pengembalian motor')
                ->modalDescription('Denda telat dihitung otomatis dari batas kembali ('
                    . (int) config('rental.late_tolerance_minutes')
                    . ' menit toleransi). Isi denda kerusakan/bensin bila ada.')
                ->schema([
                    DateTimePicker::make('actual_return_time')
                        ->label('Waktu aktual kembali')
                        ->seconds(false)
                        ->default(now())
                        ->required(),
                    TextInput::make('damage_fee')
                        ->label('Denda kerusakan (Rp)')
                        ->numeric()->minValue(0)->default(0)->required(),
                    TextInput::make('fuel_fee')
                        ->label('Denda bensin (Rp)')
                        ->numeric()->minValue(0)->default(0)->required(),
                    Textarea::make('condition_notes')
                        ->label('Catatan kondisi motor')
                        ->maxLength(1000),
                    Select::make('method')
                        ->label('Metode pembayaran denda (jika ada)')
                        ->options(['cash' => 'Tunai', 'manual_transfer' => 'QRIS'])
                        ->default('cash')
                        ->required(),
                ])
                ->visible(fn () => $this->record->status === 'active')
                ->action(fn (array $data) => $this->run(
                    HandoverService::class,
                    fn (HandoverService $s) => $s->checkOut(
                        $this->record,
                        auth()->user(),
                        Carbon::parse($data['actual_return_time']),
                        (float) $data['damage_fee'],
                        (float) $data['fuel_fee'],
                        $data['condition_notes'] ?? null,
                        $data['method'],
                    ),
                    'Pengembalian dicatat. Pesanan selesai.'
                )),

            Action::make('recordRefund')
                ->label('Catat refund DP')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Klik setelah DP sudah ditransfer kembali ke penyewa.')
                ->visible(fn () => $this->record->status === 'cancelled' && $this->record->payment_status === 'dp_paid')
                ->action(fn () => $this->run(
                    PaymentService::class,
                    fn (PaymentService $s) => $s->recordDpRefund($this->record),
                    'Refund DP dicatat.'
                )),
        ];
    }

    /**
     * Jalankan aksi service, tampilkan notifikasi, lalu segarkan data halaman.
     *
     * @param class-string $serviceClass PaymentService::class atau HandoverService::class
     */
    private function run(string $serviceClass, callable $callback, string $successMessage): void
    {
        try {
            $callback(app($serviceClass));
        } catch (PaymentException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
            $this->record->refresh();

            return;
        }

        $this->record->refresh();

        Notification::make()->title($successMessage)->success()->send();
    }
}