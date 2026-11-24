<?php

namespace Tests\Feature;

use App\Filament\Pages\OlapExplorer;
use App\Filament\Widgets\Warehouse\RentalsByMonthChart;
use App\Filament\Widgets\Warehouse\RevenueByMonthChart;
use App\Models\User;
use App\Services\DataWarehouse\EtlService;
use App\Services\DataWarehouse\OlapService;
use App\Services\DataWarehouse\WarehouseAnalytics;
use App\Services\DataWarehouse\WarehouseSchema;
use Database\Seeders\BikeSeeder;
use Database\Seeders\DummyRentalSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Database\Connection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * ETL harus merekonsiliasi angka DW dengan database operasional,
 * dan OLAP harus konsisten saat roll-up / drill-down / pivot.
 * Koneksi dw memakai sqlite :memory: (lihat phpunit.xml).
 */
class DataWarehouseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([UserSeeder::class, BikeSeeder::class, DummyRentalSeeder::class]);
        app(WarehouseSchema::class)->drop();
        app(EtlService::class)->run();
    }

    private function dw(): Connection
    {
        return DB::connection('dw');
    }

    public function test_etl_loads_every_rental_payment_and_return(): void
    {
        $this->assertTrue(app(WarehouseSchema::class)->isReady());
        $this->assertSame(DB::table('rentals')->count(), $this->dw()->table('fact_penyewaan')->count());
        $this->assertSame(DB::table('payments')->count(), $this->dw()->table('fact_pembayaran')->count());
        $this->assertSame(DB::table('rental_returns')->count(), $this->dw()->table('fact_pengembalian')->count());
        $this->assertSame(30, $this->dw()->table('dim_jenis_pembayaran')->count());
        $this->assertSame('sukses', $this->dw()->table('etl_log')->latest('id')->value('status'));
    }

    public function test_measures_reconcile_with_operational_database(): void
    {
        $this->assertEqualsWithDelta(
            (float) DB::table('rentals')->sum('total_price'),
            (float) $this->dw()->table('fact_penyewaan')->sum('total_harga'),
            0.01,
        );

        $this->assertEqualsWithDelta(
            (float) DB::table('rental_returns')->sum(DB::raw('late_fee + damage_fee + fuel_fee')),
            (float) $this->dw()->table('fact_pengembalian')->sum('total_denda'),
            0.01,
        );

        // Setiap fakta harus menunjuk ke anggota dimensi yang ada (tidak ada kunci yatim).
        $orphans = $this->dw()->table('fact_penyewaan as f')
            ->leftJoin('dim_motor as m', 'm.motor_key', '=', 'f.motor_key')
            ->leftJoin('dim_waktu as w', 'w.waktu_key', '=', 'f.waktu_booking_key')
            ->whereNull('m.motor_key')->orWhereNull('w.waktu_key')
            ->count();
        $this->assertSame(0, $orphans);
    }

    public function test_etl_is_idempotent(): void
    {
        $first = $this->dw()->table('fact_penyewaan')->sum('total_harga');
        app(EtlService::class)->run();

        $this->assertSame(DB::table('rentals')->count(), $this->dw()->table('fact_penyewaan')->count());
        $this->assertEquals($first, $this->dw()->table('fact_penyewaan')->sum('total_harga'));
    }

    public function test_roll_up_and_drill_down_keep_the_same_total(): void
    {
        $olap = app(OlapService::class);

        $byYear = $olap->pivot('penyewaan', 'jumlah_sewa', 'waktu.tahun', null);
        $byQuarter = $olap->pivot('penyewaan', 'jumlah_sewa', 'waktu.kuartal', null);
        $byMonth = $olap->pivot('penyewaan', 'jumlah_sewa', 'waktu.bulan', null);

        $this->assertEquals(DB::table('rentals')->count(), $byYear['total']);
        $this->assertEquals($byYear['total'], $byQuarter['total']);
        $this->assertEquals($byQuarter['total'], $byMonth['total']);
        $this->assertSame('waktu.kuartal', $olap->neighbour('penyewaan', 'waktu.tahun', 1));
        $this->assertSame('waktu.tahun', $olap->neighbour('penyewaan', 'waktu.kuartal', -1));
    }

    public function test_pivot_cells_sum_to_row_and_column_totals(): void
    {
        $result = app(OlapService::class)->pivot('penyewaan', 'jumlah_sewa', 'waktu.kuartal', 'motor.kategori');

        foreach ($result['rowTotals'] as $rowKey => $rowTotal) {
            $this->assertEquals($rowTotal, array_sum($result['cells'][$rowKey] ?? []));
        }
        $this->assertEquals($result['total'], array_sum($result['colTotals']));

        $swapped = app(OlapService::class)->pivot('penyewaan', 'jumlah_sewa', 'motor.kategori', 'waktu.kuartal');
        $this->assertEquals($result['total'], $swapped['total']);
    }

    public function test_dashboard_kpis_come_from_the_warehouse(): void
    {
        $kpi = app(WarehouseAnalytics::class)->kpis(null);

        $this->assertSame(DB::table('rentals')->count(), $kpi['pesanan']);
        $this->assertSame(DB::table('rentals')->where('status', 'completed')->count(), $kpi['sewa_berhasil']);
    }

    public function test_owner_dashboard_shows_warehouse_analytics(): void
    {
        $owner = User::where('role', 'super_admin')->firstOrFail();

        $this->actingAs($owner)->get('/admin')
            ->assertOk()
            ->assertSee('Jalankan ETL');
        $this->actingAs($owner)->get('/admin/olap')->assertOk();
    }

    public function test_olap_page_drills_rolls_up_and_pivots(): void
    {
        $this->actingAs(User::where('role', 'super_admin')->firstOrFail());
        $year = (string) $this->dw()->table('dim_waktu')->orderBy('tahun')->value('tahun');

        Livewire::test(OlapExplorer::class)
            ->assertSet('rows', 'waktu.tahun')
            ->assertSet('cols', 'motor.kategori')
            ->call('drillInto', $year)
            ->assertSet('rows', 'waktu.kuartal')
            ->assertSet('filters', ['waktu:tahun' => $year])
            ->call('swap')
            ->assertSet('rows', 'motor.kategori')
            ->assertSet('cols', 'waktu.kuartal')
            ->call('swap')
            ->call('removeFilter', 'waktu:tahun')
            ->assertSet('rows', 'waktu.tahun')
            ->assertSet('filters', [])
            ->call('pilihData', 'pembayaran')
            ->assertSet('measure', 'pendapatan_bersih')
            ->assertSet('cols', 'jenis.tipe')
            ->assertOk();
    }

    public function test_admin_dashboard_shows_operational_summary_only(): void
    {
        $admin = User::where('role', 'admin')->firstOrFail();

        $this->actingAs($admin)->get('/admin')
            ->assertOk()
            ->assertDontSee('Jalankan ETL');
        $this->actingAs($admin)->get('/admin/olap')->assertForbidden();
    }

    public function test_dummy_data_covers_every_month_of_2025(): void
    {
        $this->assertContains(2025, app(WarehouseAnalytics::class)->years());
        $this->assertContains(2026, app(WarehouseAnalytics::class)->years());

        $months = app(WarehouseAnalytics::class)->rentalsByMonth(2025)->pluck('label');
        $this->assertCount(12, $months, 'Setiap bulan 2025 harus punya sewa berhasil.');
    }

    public function test_each_chart_has_its_own_filters(): void
    {
        $this->actingAs(User::where('role', 'super_admin')->firstOrFail());

        // Filter tahun grafik tidak bergantung pada filter Periode halaman.
        $chart = Livewire::test(RentalsByMonthChart::class, ['pageFilters' => ['tahun' => '2026']])
            ->set('filters.tahun', '2025');
        $labels = $this->chartLabels($chart->instance());
        $this->assertNotEmpty($labels);
        $this->assertTrue(collect($labels)->every(fn ($l) => str_ends_with($l, ' 25')));

        // Kosong = ikut filter Periode halaman.
        $labels = $this->chartLabels(Livewire::test(RentalsByMonthChart::class, ['pageFilters' => ['tahun' => '2026']])->instance());
        $this->assertTrue(collect($labels)->every(fn ($l) => str_ends_with($l, ' 26')));

        // Filter dimensi: hanya pembayaran tunai.
        $cashOnly = app(WarehouseAnalytics::class)->revenueByMonth(2025, 'cash')->sum(fn ($r) => $r['DP'] + $r['Pelunasan'] + $r['Denda']);
        $all = app(WarehouseAnalytics::class)->revenueByMonth(2025)->sum(fn ($r) => $r['DP'] + $r['Pelunasan'] + $r['Denda']);
        $this->assertGreaterThan(0, $cashOnly);
        $this->assertLessThan($all, $cashOnly);
        Livewire::test(RevenueByMonthChart::class)->set('filters.metode', 'cash')->assertOk();
    }

    public function test_owner_dashboard_has_no_formula_text_under_titles(): void
    {
        $this->actingAs(User::where('role', 'super_admin')->firstOrFail())->get('/admin')
            ->assertOk()
            ->assertDontSee('Fact_')
            ->assertDontSee('÷')
            ->assertDontSee('DP + pelunasan');
    }

    public function test_customer_dashboard_shows_own_analytics(): void
    {
        $customer = User::where('role', 'customer')->where('is_active', true)->whereHas('rentals', fn ($q) => $q->where('status', 'completed'))->firstOrFail();

        $this->actingAs($customer)->get('/dashboard')->assertRedirect(route('customer.dashboard'));

        $response = $this->actingAs($customer)->get('/dashboard-saya')
            ->assertOk()
            ->assertSee('Dashboard saya')
            ->assertSee('Pengeluaran per bulan')
            ->assertSee('Ikuti periode');

        $payload = json_decode(str($response->getContent())->betweenFirst('<script type="application/json" id="dash-data">', '</script>')->toString(), true);
        $this->assertCount($customer->rentals()->count(), $payload['rentals']);
        $this->assertLessThan(DB::table('rentals')->count(), count($payload['rentals']), 'Hanya sewa milik customer ini.');
    }

    /** @return array<int, string> */
    private function chartLabels(object $chart): array
    {
        return (fn () => $this->getData()['labels'])->call($chart);
    }

    public function test_staff_cannot_open_customer_dashboard(): void
    {
        $this->actingAs(User::where('role', 'admin')->firstOrFail())->get('/dashboard-saya')->assertForbidden();
    }
}
