<x-filament-widgets::widget>
    <x-filament::section heading="Pelanggan paling bernilai">
        <x-slot name="afterHeader">
            <div class="dw-header-filters">
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="tahun" aria-label="Filter tahun">
                        <option value="">Ikuti filter Periode</option>
                        <option value="semua">Semua tahun</option>
                        @foreach ($years as $year)
                            <option value="{{ $year }}">Tahun {{ $year }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="segmen" aria-label="Filter segmen pelanggan">
                        <option value="">Semua segmen</option>
                        <option value="Berulang">Berulang</option>
                        <option value="Baru">Baru</option>
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
        </x-slot>
        @if ($rows->isEmpty())
            <p class="dw-muted">Belum ada pembayaran pada periode ini.</p>
        @else
            <div class="dw-table-wrap">
                <table class="dw-table">
                    <thead>
                        <tr>
                            <th>Pelanggan</th>
                            <th>Segmen</th>
                            <th class="dw-num">Sewa berhasil</th>
                            <th class="dw-num">Pendapatan bersih</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                <td>{{ $row['nama'] }}</td>
                                <td><span class="dw-chip {{ $row['segmen'] === 'Berulang' ? 'dw-chip-strong' : '' }}">{{ $row['segmen'] }}</span></td>
                                <td class="dw-num">{{ $row['sewa'] }}</td>
                                <td class="dw-num">{{ $analytics->rupiah($row['pendapatan']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
