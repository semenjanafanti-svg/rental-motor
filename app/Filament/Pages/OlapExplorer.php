<?php

namespace App\Filament\Pages;

use App\Services\DataWarehouse\OlapCubes;
use App\Services\DataWarehouse\OlapService;
use App\Services\DataWarehouse\WarehouseSchema;
use Filament\Pages\Page;
use Throwable;

/**
 * Penjelajah OLAP versi ringkas.
 *
 * Pengguna cukup memilih data, angka yang ditampilkan, dan "kelompokkan per".
 * Operasi OLAP terjadi lewat interaksi langsung:
 *  - klik nama baris  = drill-down (anggota itu jadi filter, level turun satu)
 *  - hapus chip filter = roll-up / lepas slice
 *  - beberapa filter   = dice
 *  - tombol Tukar      = pivot
 */
class OlapExplorer extends Page
{
    protected static ?string $slug = 'olap';

    protected static ?string $title = 'OLAP';

    protected static ?string $navigationLabel = 'OLAP';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-table-cells';

    protected static string|\UnitEnum|null $navigationGroup = 'Owner';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.olap-explorer';

    /** Angka utama per kubus; ukuran lain tetap ada di OlapCubes untuk query lanjutan. */
    private const MEASURES = [
        'penyewaan' => ['jumlah_sewa', 'total_harga', 'rata_harga'],
        'pembayaran' => ['pendapatan_bersih', 'jumlah_transaksi', 'nominal_refund'],
        'pengembalian' => ['total_denda', 'jumlah_pengembalian', 'jumlah_terlambat'],
    ];

    public string $cube = 'penyewaan';

    public string $measure = '';

    /** Dimensi baris (usage) yang dipilih di dropdown "Kelompokkan per". */
    public string $lihat = '';

    /** Dimensi kolom (usage) yang dipilih di dropdown "Bandingkan per"; '' = tanpa kolom. */
    public string $banding = '';

    /** Level aktif (usage.level) untuk baris dan kolom. */
    public string $rows = '';

    public string $cols = '';

    /**
     * Kunci memakai "usage:level" (bukan titik) karena Livewire membaca titik sebagai path bersarang.
     *
     * @var array<string, string> "usage:level" => member key
     */
    public array $filters = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public function mount(): void
    {
        $this->pilihData('penyewaan');
    }

    public function pilihData(string $cube): void
    {
        if (! isset(OlapCubes::cubes()[$cube])) {
            return;
        }

        $this->cube = $cube;
        $this->filters = [];
        $this->measure = self::MEASURES[$cube][0];
        $this->lihat = 'waktu';
        $this->banding = $cube === 'pembayaran' ? 'jenis' : 'motor';
        $this->rows = $this->levelFor($this->lihat);
        $this->cols = $this->levelFor($this->banding);
    }

    public function updatedLihat(): void
    {
        if ($this->lihat === $this->banding) {
            $this->banding = '';
        }
        $this->rows = $this->levelFor($this->lihat);
        $this->cols = $this->banding === '' ? '' : $this->levelFor($this->banding);
    }

    public function updatedBanding(): void
    {
        if ($this->banding === $this->lihat) {
            $this->banding = '';
        }
        $this->cols = $this->banding === '' ? '' : $this->levelFor($this->banding);
    }

    /** Pivot: tukar baris dan kolom. */
    public function swap(): void
    {
        if ($this->banding === '') {
            return;
        }

        [$this->lihat, $this->banding] = [$this->banding, $this->lihat];
        [$this->rows, $this->cols] = [$this->cols, $this->rows];
    }

    /** Drill-down ke satu anggota: anggota dijadikan filter, baris turun satu level. */
    public function drillInto(string $memberKey): void
    {
        $next = app(OlapService::class)->neighbour($this->cube, $this->rows, 1);
        if (! $next) {
            return;
        }

        $this->filters[self::filterKey($this->rows)] = $memberKey;
        $this->rows = $next;
    }

    /**
     * Hapus satu filter. Bila filter itu ada di dimensi baris, ini roll-up:
     * baris kembali ke level filter tersebut dan filter yang lebih dalam ikut dilepas.
     */
    public function removeFilter(string $key): void
    {
        [$usage, $level] = explode(':', $key);
        $hierarchy = $this->hierarchy($usage);
        $index = array_search($level, $hierarchy, true);

        foreach (array_keys($this->filters) as $filterKey) {
            [$filterUsage, $filterLevel] = explode(':', $filterKey);
            if ($filterUsage === $usage && array_search($filterLevel, $hierarchy, true) >= $index) {
                unset($this->filters[$filterKey]);
            }
        }

        if ($usage === $this->lihat) {
            $this->rows = $this->levelFor($usage);
        }
        if ($usage === $this->banding) {
            $this->cols = $this->levelFor($usage);
        }
    }

    public function clearFilters(): void
    {
        $this->filters = [];
        $this->rows = $this->levelFor($this->lihat);
        $this->cols = $this->banding === '' ? '' : $this->levelFor($this->banding);
    }

    /** @return array<int, string> */
    private function hierarchy(string $usage): array
    {
        $dimension = OlapCubes::cubes()[$this->cube]['dimensions'][$usage][0];

        return OlapCubes::dimensions()[$dimension]['hierarchy'];
    }

    /** Level yang dipakai saat dimensi dipilih: satu di bawah filter terdalam, atau level teratas. */
    private function levelFor(string $usage): string
    {
        $hierarchy = $this->hierarchy($usage);
        $position = 0;
        foreach ($hierarchy as $i => $level) {
            if (isset($this->filters["{$usage}:{$level}"])) {
                $position = min($i + 1, count($hierarchy) - 1);
            }
        }

        return "{$usage}.{$hierarchy[$position]}";
    }

    protected function getViewData(): array
    {
        $olap = app(OlapService::class);
        $cubes = OlapCubes::cubes();
        $cube = $cubes[$this->cube];

        // Dimensi role-playing kedua (mis. waktu booking) disembunyikan agar pilihan tetap sedikit.
        $dimensionOptions = [];
        foreach ($cube['dimensions'] as $usage => [$dimension, , $label]) {
            if ($usage === $dimension) {
                $dimensionOptions[$usage] = $label;
            }
        }

        $measureOptions = [];
        foreach (self::MEASURES[$this->cube] as $key) {
            $measureOptions[$key] = $cube['measures'][$key][0];
        }

        $ready = app(WarehouseSchema::class)->isReady();
        $result = null;
        $error = null;
        $chips = [];

        if ($ready) {
            try {
                $activeFilters = [];
                foreach ($this->filters as $key => $value) {
                    if (! is_string($value) || $value === '') {
                        continue;
                    }
                    $usageLevel = str_replace(':', '.', $key);
                    $activeFilters[$usageLevel] = $value;
                    $level = $olap->level($this->cube, $usageLevel);
                    $chips[$key] = [
                        'dimension' => $level['usageLabel'],
                        'member' => $olap->members($this->cube, $usageLevel)[$value] ?? $value,
                    ];
                }

                $result = $olap->pivot($this->cube, $this->measure, $this->rows, $this->cols ?: null, $activeFilters);
            } catch (Throwable $e) {
                report($e);
                $error = $e->getMessage();
            }
        }

        return [
            'ready' => $ready,
            'cubes' => $cubes,
            'cubeDef' => $cube,
            'dimensionOptions' => $dimensionOptions,
            'measureOptions' => $measureOptions,
            'chips' => $chips,
            'result' => $result,
            'error' => $error,
            'rowLevel' => $ready ? $olap->level($this->cube, $this->rows) : null,
            'colLevel' => $ready && $this->cols !== '' ? $olap->level($this->cube, $this->cols) : null,
            'canDrillDown' => $olap->neighbour($this->cube, $this->rows, 1) !== null,
        ];
    }

    public static function filterKey(string $usageLevel): string
    {
        return str_replace('.', ':', $usageLevel);
    }

    public static function formatValue(?float $value, string $format): string
    {
        if ($value === null) {
            return '–';
        }

        return match ($format) {
            'rupiah' => 'Rp'.number_format($value, 0, ',', '.'),
            'desimal' => number_format($value, 1, ',', '.'),
            default => number_format($value, 0, ',', '.'),
        };
    }
}
