<x-filament-panels::page>
    @if (! $ready)
        <x-filament::section>
            <p class="dw-muted">Data warehouse belum dibangun. Buka <a class="dw-link" href="{{ \App\Filament\Pages\Dashboard::getUrl() }}">Dashboard</a> lalu klik "Jalankan ETL", atau jalankan <code>php artisan dw:etl</code>.</p>
        </x-filament::section>
    @else
        <x-filament::section>
            <div class="dw-seg" role="tablist" aria-label="Pilih data">
                @foreach ($cubes as $key => $cube)
                    <button type="button" role="tab" aria-selected="{{ $this->cube === $key ? 'true' : 'false' }}"
                        @class(['dw-seg-item', 'is-active' => $this->cube === $key])
                        wire:click="pilihData('{{ $key }}')">{{ $cube['label'] }}</button>
                @endforeach
            </div>

            <div class="dw-controls dw-controls-3">
                <label class="dw-field">
                    <span>Tampilkan</span>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="measure">
                            @foreach ($measureOptions as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </label>

                <label class="dw-field">
                    <span>Kelompokkan per</span>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="lihat">
                            @foreach ($dimensionOptions as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </label>

                <div class="dw-field">
                    <span>Bandingkan per</span>
                    <div class="dw-inline">
                        <x-filament::input.wrapper class="dw-grow">
                            <x-filament::input.select wire:model.live="banding" aria-label="Bandingkan per">
                                <option value="">Tidak ada</option>
                                @foreach ($dimensionOptions as $key => $label)
                                    @continue($key === $lihat)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                        <x-filament::icon-button wire:click="swap" color="gray" icon="heroicon-m-arrows-right-left"
                            :disabled="$banding === ''" label="Tukar baris dan kolom (pivot)" tooltip="Tukar baris & kolom (pivot)" />
                    </div>
                </div>
            </div>

            <div class="dw-filters">
                @forelse ($chips as $key => $chip)
                    <span class="dw-filter-chip">
                        <span class="dw-muted-sm">{{ $chip['dimension'] }}:</span> {{ $chip['member'] }}
                        <button type="button" wire:click="removeFilter(@js($key))" aria-label="Hapus filter {{ $chip['member'] }}">&times;</button>
                    </span>
                @empty
                    <span class="dw-muted">Klik nama di tabel untuk melihat rinciannya.</span>
                @endforelse
                @if (count($chips) > 1)
                    <button type="button" class="dw-clear" wire:click="clearFilters">Hapus semua</button>
                @endif
            </div>
        </x-filament::section>

        <x-filament::section>
            @if ($error)
                <p class="dw-error">{{ $error }}</p>
            @elseif (! $result || $result['rows'] === [])
                <p class="dw-muted">Tidak ada data untuk filter ini.</p>
            @else
                <div class="dw-table-wrap" wire:loading.class="dw-loading">
                    <table class="dw-table dw-pivot">
                        <thead>
                            <tr>
                                <th>
                                    {{ $rowLevel['label'] }}
                                    @if ($colLevel)
                                        <span class="dw-th-sub">× {{ $colLevel['label'] }}</span>
                                    @endif
                                </th>
                                @foreach ($result['cols'] as $colLabel)
                                    <th class="dw-num">{{ $colLabel }}</th>
                                @endforeach
                                @if ($cols !== '')
                                    <th class="dw-num dw-total">Total</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($result['rows'] as $rowKey => $rowLabel)
                                <tr wire:key="row-{{ md5($rowKey) }}">
                                    <th scope="row">
                                        @if ($canDrillDown)
                                            <button type="button" class="dw-drill" wire:click="drillInto(@js($rowKey))" title="Lihat rincian {{ $rowLabel }}">{{ $rowLabel }}</button>
                                        @else
                                            {{ $rowLabel }}
                                        @endif
                                    </th>
                                    @foreach (array_keys($result['cols']) as $colKey)
                                        <td class="dw-num">{{ \App\Filament\Pages\OlapExplorer::formatValue($result['cells'][$rowKey][$colKey] ?? null, $result['format']) }}</td>
                                    @endforeach
                                    @if ($cols !== '')
                                        <td class="dw-num dw-total">{{ \App\Filament\Pages\OlapExplorer::formatValue($result['rowTotals'][$rowKey] ?? null, $result['format']) }}</td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <th scope="row">Total</th>
                                @foreach (array_keys($result['cols']) as $colKey)
                                    <td class="dw-num">{{ \App\Filament\Pages\OlapExplorer::formatValue($cols !== '' ? ($result['colTotals'][$colKey] ?? null) : $result['total'], $result['format']) }}</td>
                                @endforeach
                                @if ($cols !== '')
                                    <td class="dw-num dw-total">{{ \App\Filament\Pages\OlapExplorer::formatValue($result['total'], $result['format']) }}</td>
                                @endif
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <details class="dw-sql">
                    <summary>Cara kerja & query SQL</summary>
                    <ul class="dw-howto">
                        <li><strong>Drill-down</strong>: klik nama baris untuk turun satu level (mis. tahun → kuartal → bulan).</li>
                        <li><strong>Roll-up</strong>: hapus chip filter untuk naik kembali.</li>
                        <li><strong>Slice / dice</strong>: setiap chip adalah filter; satu chip = slice, lebih dari satu = dice.</li>
                        <li><strong>Pivot</strong>: tombol <span aria-hidden="true">⇄</span> menukar baris dan kolom.</li>
                    </ul>
                    <pre>{{ $result['sql'] }}</pre>
                </details>
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>
