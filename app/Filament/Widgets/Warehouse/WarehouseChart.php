<?php

namespace App\Filament\Widgets\Warehouse;

use App\Services\DataWarehouse\WarehouseAnalytics;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * Dasar semua grafik dashboard DW.
 * Warna kategorikal mengikuti urutan tetap (biru, oranye, aqua) yang lolos uji
 * buta warna; satu seri selalu memakai biru. Tidak ada grafik dua sumbu-y.
 */
abstract class WarehouseChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static bool $isDiscovered = false;

    public const SERIES = ['#2a78d6', '#eb6834', '#1baf7a'];

    protected const GRID = '#efeee9';

    protected const INK_MUTED = '#6e6b63';

    protected ?string $maxHeight = '280px';

    protected function analytics(): WarehouseAnalytics
    {
        return app(WarehouseAnalytics::class);
    }

    protected function year(): ?int
    {
        $year = $this->pageFilters['tahun'] ?? null;

        return filled($year) ? (int) $year : null;
    }

    /** Dataset batang dengan ujung membulat 4px dan celah 2px antarbatang. */
    protected function barDataset(string $label, array $data, int $slot = 0, array $extra = []): array
    {
        return [
            'label' => $label,
            'data' => array_values($data),
            'backgroundColor' => self::SERIES[$slot],
            'hoverBackgroundColor' => self::SERIES[$slot],
            'borderWidth' => 0,
            'borderRadius' => 4,
            'borderSkipped' => 'start',
            'maxBarThickness' => 28,
            'categoryPercentage' => 0.8,
            'barPercentage' => 0.9,
        ] + $extra;
    }

    /**
     * Opsi umum: grid tipis, legenda hanya bila >= 2 seri, tooltip per indeks.
     *
     * @param  'rupiah'|'angka'|'persen'  $format
     */
    protected function baseOptions(string $format = 'angka', bool $legend = false, bool $horizontal = false, bool $stacked = false): RawJs
    {
        return $this->toRawJs($this->baseOptionsArray($format, $legend, $horizontal, $stacked));
    }

    /**
     * Chart.js butuh fungsi JS untuk format Rupiah. Filament hanya menerima RawJs utuh,
     * jadi opsi di-encode ke JSON lalu placeholder diganti dengan kode fungsi.
     */
    protected function toRawJs(array $options): RawJs
    {
        $functions = [];
        array_walk_recursive($options, function (&$value) use (&$functions) {
            if ($value instanceof RawJs) {
                $placeholder = '__js_'.count($functions).'__';
                $functions['"'.$placeholder.'"'] = $value->toHtml();
                $value = $placeholder;
            }
        });

        // Hasilnya dicetak di dalam atribut x-data="...", jadi tanda kutip di-escape sebagai entitas HTML
        // (browser mengembalikannya ke karakter asli sebelum Alpine mengevaluasi ekspresinya).
        $js = strtr(json_encode($options, JSON_UNESCAPED_UNICODE), $functions);

        return RawJs::make(htmlspecialchars($js, ENT_QUOTES));
    }

    protected function baseOptionsArray(string $format = 'angka', bool $legend = false, bool $horizontal = false, bool $stacked = false): array
    {
        $valueAxis = $horizontal ? 'x' : 'y';
        $categoryAxis = $horizontal ? 'y' : 'x';

        return [
            'indexAxis' => $horizontal ? 'y' : 'x',
            'maintainAspectRatio' => false,
            'interaction' => ['mode' => 'index', 'intersect' => false],
            'plugins' => [
                'legend' => [
                    'display' => $legend,
                    'position' => 'top',
                    'align' => 'start',
                    'labels' => ['usePointStyle' => true, 'pointStyle' => 'rectRounded', 'boxWidth' => 10, 'boxHeight' => 10, 'color' => '#1d1d1f'],
                ],
                'tooltip' => [
                    'callbacks' => ['label' => $this->tooltipFormatter($format)],
                ],
            ],
            'scales' => [
                $categoryAxis => [
                    'stacked' => $stacked,
                    'grid' => ['display' => false],
                    'border' => ['color' => self::GRID],
                    'ticks' => ['color' => self::INK_MUTED],
                ],
                $valueAxis => [
                    'stacked' => $stacked,
                    'beginAtZero' => true,
                    'grid' => ['color' => self::GRID],
                    'border' => ['display' => false],
                    'ticks' => ['color' => self::INK_MUTED, 'callback' => $this->tickFormatter($format), 'precision' => 0],
                ],
            ],
        ];
    }

    private function tickFormatter(string $format): RawJs
    {
        return match ($format) {
            'rupiah' => RawJs::make(<<<'JS'
                (value) => value >= 1e6 ? 'Rp' + (value / 1e6).toLocaleString('id-ID', { maximumFractionDigits: 1 }) + ' jt'
                    : value >= 1e3 ? 'Rp' + (value / 1e3).toLocaleString('id-ID') + ' rb' : 'Rp' + value
            JS),
            'persen' => RawJs::make("(value) => value + '%'"),
            default => RawJs::make("(value) => value.toLocaleString('id-ID')"),
        };
    }

    private function tooltipFormatter(string $format): RawJs
    {
        return match ($format) {
            'rupiah' => RawJs::make(<<<'JS'
                (ctx) => (ctx.dataset.label ? ctx.dataset.label + ': ' : '') + 'Rp' + Number(ctx.parsed[ctx.chart.options.indexAxis === 'y' ? 'x' : 'y']).toLocaleString('id-ID')
            JS),
            'persen' => RawJs::make(<<<'JS'
                (ctx) => (ctx.dataset.label ? ctx.dataset.label + ': ' : '') + Number(ctx.parsed[ctx.chart.options.indexAxis === 'y' ? 'x' : 'y']).toLocaleString('id-ID', { maximumFractionDigits: 1 }) + '%'
            JS),
            default => RawJs::make(<<<'JS'
                (ctx) => (ctx.dataset.label ? ctx.dataset.label + ': ' : '') + Number(ctx.parsed[ctx.chart.options.indexAxis === 'y' ? 'x' : 'y']).toLocaleString('id-ID')
            JS),
        };
    }

    public static function canView(): bool
    {
        return app(WarehouseAnalytics::class)->isReady();
    }
}
