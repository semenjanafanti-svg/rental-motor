<?php

namespace Tests\Feature;

use App\Filament\Widgets\Operational\FleetStatusChart;
use App\Filament\Widgets\Operational\IncomingOrdersChart;
use App\Filament\Widgets\Operational\ScheduleChart;
use App\Filament\Widgets\Operational\TodayTasksTable;
use App\Filament\Widgets\OwnerOverview;
use App\Models\Bike;
use App\Models\Rental;
use App\Models\User;
use Database\Seeders\BikeSeeder;
use Database\Seeders\DummyRentalSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Dashboard admin/petugas: tugas hari ini dan grafik operasional berfilter. */
class OperationalDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([UserSeeder::class, BikeSeeder::class, DummyRentalSeeder::class]);
        $this->actingAs(User::where('role', 'admin')->firstOrFail());
    }

    /** @return array{labels: array, datasets: array} */
    private function chartData(object $chart): array
    {
        return (fn () => $this->getData())->call($chart);
    }

    public function test_admin_dashboard_shows_tasks_and_operational_charts_without_revenue(): void
    {
        $this->get('/admin')
            ->assertOk()
            ->assertSeeLivewire(TodayTasksTable::class)
            ->assertSeeLivewire(ScheduleChart::class)
            ->assertSeeLivewire(IncomingOrdersChart::class)
            ->assertSeeLivewire(FleetStatusChart::class)
            ->assertDontSee('Pendapatan');
    }

    public function test_summary_cards_include_todays_pickups_and_overdue(): void
    {
        Rental::where('status', 'active')->firstOrFail()->update(['end_time' => now()->subHour()]);

        Livewire::test(OwnerOverview::class)
            ->assertSee('Diambil hari ini')
            ->assertSee('Terlambat kembali')
            ->assertSee('Segera hubungi penyewa');
    }

    public function test_task_table_lists_verifications_and_overdue_rentals_and_filters_by_task(): void
    {
        $waiting = Rental::where('status', 'pending_verification')->get();
        $late = Rental::where('status', 'active')->firstOrFail();
        $late->update(['end_time' => now()->subHours(3)]);

        $this->assertNotEmpty($waiting);
        $this->assertSame('overdue', TodayTasksTable::taskOf($late->fresh()));

        Livewire::test(TodayTasksTable::class)
            ->set('tableRecordsPerPage', 25)
            ->assertCanSeeTableRecords($waiting->concat([$late]))
            ->filterTable('tugas', 'verify')
            ->assertCanSeeTableRecords($waiting)
            ->assertCanNotSeeTableRecords([$late]);
    }

    public function test_schedule_chart_range_filter_changes_number_of_days(): void
    {
        $chart = Livewire::test(ScheduleChart::class);
        $this->assertCount(7, $this->chartData($chart->instance())['labels']);

        $chart->set('filters.rentang', '14');
        $this->assertCount(14, $this->chartData($chart->instance())['labels']);
    }

    public function test_incoming_orders_chart_counts_bookings_and_filters_by_status(): void
    {
        $chart = Livewire::test(IncomingOrdersChart::class);
        $all = array_sum($this->chartData($chart->instance())['datasets'][0]['data']);
        $this->assertSame(Rental::where('created_at', '>=', today()->subDays(13))->count(), $all);

        $chart->set('filters.kelompok', 'Gagal');
        $failed = array_sum($this->chartData($chart->instance())['datasets'][0]['data']);
        $this->assertSame(
            Rental::where('created_at', '>=', today()->subDays(13))->whereIn('status', ['cancelled', 'expired', 'no_show'])->count(),
            $failed,
        );
    }

    public function test_fleet_chart_covers_every_active_bike_and_drills_into_a_category(): void
    {
        $chart = Livewire::test(FleetStatusChart::class);
        $total = collect($this->chartData($chart->instance())['datasets'])->sum(fn ($d) => array_sum($d['data']));
        $this->assertSame(Bike::where('status', '!=', 'inactive')->count(), $total);

        $chart->set('filters.kategori', 'matic');
        $this->assertEqualsCanonicalizing(
            Bike::where('status', '!=', 'inactive')->where('category', 'matic')->pluck('name')->all(),
            $this->chartData($chart->instance())['labels'],
        );
    }

    public function test_owner_dashboard_keeps_warehouse_analytics(): void
    {
        $this->actingAs(User::where('role', 'super_admin')->firstOrFail())->get('/admin')
            ->assertOk()
            ->assertDontSeeLivewire(TodayTasksTable::class);
    }
}
