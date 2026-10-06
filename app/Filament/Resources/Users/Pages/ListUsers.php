<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    public function getSubheading(): ?string
    {
        return 'Kelola akun customer, status pengguna, dan riwayat pesanan dalam satu tempat.';
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Tambah Staf')];
    }

    public function getTabs(): array
    {
        return [
            'semua' => Tab::make('Semua')->badge(User::query()->count()),
            'customer' => Tab::make('Customer')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('role', 'customer'))
                ->badge(User::query()->where('role', 'customer')->count()),
            'staf' => Tab::make('Staf')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('role', ['admin', 'super_admin']))
                ->badge(User::query()->whereIn('role', ['admin', 'super_admin'])->count()),
        ];
    }
}
