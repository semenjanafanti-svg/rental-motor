<?php

namespace App\Filament\Resources\Rentals\Pages;

use App\Filament\Resources\Rentals\RentalResource;
use App\Models\Rental;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListRentals extends ListRecords
{
    protected static string $resource = RentalResource::class;

    public function getHeading(): string
    {
        return match ($this->activeTab) {
            'verif' => 'Verifikasi Pembayaran & Dokumen',
            'pickup' => 'Siap Diambil',
            'active' => 'Sedang Disewa',
            default => 'Semua Rental',
        };
    }

    public function getTabs(): array
    {
        $tab = fn (string $label, string $status) => Tab::make($label)
            ->modifyQueryUsing(fn (Builder $query) => $query->where('status', $status))
            ->badge(Rental::where('status', $status)->count() ?: null);

        return [
            'all' => Tab::make('Semua'),
            'verif' => $tab('Verifikasi', 'pending_verification'),
            'pickup' => $tab('Siap diambil', 'approved'),
            'active' => $tab('Sedang disewa', 'active'),
        ];
    }

    public function mount(): void
    {
        app(\App\Services\RentalExpirationService::class)->expireOverdue();

        parent::mount();
    }
}