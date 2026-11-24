<?php

namespace App\Console\Commands;

use App\Services\DataWarehouse\EtlService;
use App\Services\DataWarehouse\WarehouseSchema;
use Illuminate\Console\Command;

class RunWarehouseEtl extends Command
{
    protected $signature = 'dw:etl {--fresh : Hapus seluruh tabel DW (termasuk riwayat SCD) lalu bangun ulang}';

    protected $description = 'ETL database operasional Mitra Jalan ke data warehouse (dim_* dan fact_*)';

    public function handle(EtlService $etl, WarehouseSchema $schema): int
    {
        if ($this->option('fresh')) {
            $schema->drop();
            $this->components->warn('Tabel DW lama dihapus.');
        }

        $started = microtime(true);
        $counts = $etl->run();

        $this->components->info(sprintf('ETL selesai dalam %.2f detik.', microtime(true) - $started));
        $this->table(['Tabel', 'Jumlah baris'], collect($counts)->map(fn ($n, $t) => [$t, $n])->values()->all());

        return self::SUCCESS;
    }
}
