<?php

namespace App\Services\DataWarehouse;

use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Mesin OLAP sederhana di atas star schema (ROLAP):
 *   - roll-up / drill-down : pindah level di hierarki dimensi
 *   - slice / dice         : filter satu / beberapa anggota dimensi
 *   - pivot                : tukar dimensi baris dan kolom
 * Hasil berupa tabel silang lengkap dengan subtotal baris, kolom, dan total keseluruhan.
 */
class OlapService
{
    private function dw(): Connection
    {
        return DB::connection(WarehouseSchema::connection());
    }

    /**
     * @param  string|null  $cols  "usage.level" atau null untuk satu kolom total saja
     * @param  array<string, string>  $filters  "usage.level" => member key
     * @return array{rows: array, cols: array, cells: array, rowTotals: array, colTotals: array, total: float|null, sql: string, format: string}
     */
    public function pivot(string $cube, string $measure, string $rows, ?string $cols, array $filters = []): array
    {
        $definition = OlapCubes::cubes()[$cube] ?? throw new InvalidArgumentException("Kubus {$cube} tidak dikenal.");
        [$measureLabel, $aggregate, $format] = $definition['measures'][$measure] ?? throw new InvalidArgumentException("Ukuran {$measure} tidak dikenal.");

        $rowLevel = $this->level($cube, $rows);
        $colLevel = $cols ? $this->level($cube, $cols) : null;

        $base = fn () => $this->baseQuery($cube, array_filter([$rows, $cols, ...array_keys($filters)]), $filters);

        // Sel: GROUP BY baris, kolom
        $cellQuery = $base()->selectRaw("{$aggregate} as nilai");
        $this->groupByLevel($cellQuery, $rowLevel, 'r');
        if ($colLevel) {
            $this->groupByLevel($cellQuery, $colLevel, 'c');
        }

        $cells = [];
        $rowMembers = [];
        $colMembers = [];
        foreach ($cellQuery->get() as $record) {
            $record = (array) $record;
            [$rowKey, $rowLabel] = $this->member($rowLevel, $record, 'r');
            [$colKey, $colLabel] = $colLevel ? $this->member($colLevel, $record, 'c') : ['__all', $measureLabel];
            $rowMembers[$rowKey] = $rowLabel;
            $colMembers[$colKey] = $colLabel;
            $cells[$rowKey][$colKey] = $record['nilai'] === null ? null : (float) $record['nilai'];
        }

        // Subtotal (roll-up) dihitung ulang dengan agregat yang sama, bukan dijumlahkan,
        // supaya ukuran rata-rata tetap benar.
        $rowTotals = [];
        $colTotals = [];
        if ($colLevel) {
            $rowQuery = $base()->selectRaw("{$aggregate} as nilai");
            $this->groupByLevel($rowQuery, $rowLevel, 'r');
            foreach ($rowQuery->get() as $record) {
                $record = (array) $record;
                $rowTotals[$this->member($rowLevel, $record, 'r')[0]] = (float) $record['nilai'];
            }

            $colQuery = $base()->selectRaw("{$aggregate} as nilai");
            $this->groupByLevel($colQuery, $colLevel, 'c');
            foreach ($colQuery->get() as $record) {
                $record = (array) $record;
                $colTotals[$this->member($colLevel, $record, 'c')[0]] = (float) $record['nilai'];
            }
        }

        $total = $base()->selectRaw("{$aggregate} as nilai")->value('nilai');

        return [
            'rows' => $rowMembers,
            'cols' => $colMembers,
            'cells' => $cells,
            'rowTotals' => $rowTotals,
            'colTotals' => $colTotals,
            'total' => $total === null ? null : (float) $total,
            'sql' => $this->interpolate($cellQuery),
            'format' => $format,
            'measureLabel' => $measureLabel,
        ];
    }

    /** Daftar anggota (member) suatu level untuk pilihan slicer. @return array<string, string> */
    public function members(string $cube, string $usageLevel): array
    {
        $level = $this->level($cube, $usageLevel);
        $query = $this->dw()->table($level['table'].' as d');
        foreach ($level['cols'] as $i => $col) {
            $query->addSelect("d.{$col} as m{$i}")->groupBy("d.{$col}")->orderBy("d.{$col}");
        }

        $members = [];
        foreach ($query->get() as $record) {
            $values = array_values((array) $record);
            $members[implode('|', $values)] = ($level['format'])($values);
        }

        return $members;
    }

    /** Level berikutnya (drill-down) atau sebelumnya (roll-up) dalam hierarki. */
    public function neighbour(string $cube, string $usageLevel, int $step): ?string
    {
        [$usage, $levelName] = explode('.', $usageLevel);
        $dimension = OlapCubes::cubes()[$cube]['dimensions'][$usage][0];
        $hierarchy = OlapCubes::dimensions()[$dimension]['hierarchy'];
        $index = array_search($levelName, $hierarchy, true);

        if ($index === false || ! isset($hierarchy[$index + $step])) {
            return null;
        }

        return $usage.'.'.$hierarchy[$index + $step];
    }

    /** @return array{table:string, alias:string, cols:array, format:callable, label:string} */
    public function level(string $cube, string $usageLevel): array
    {
        [$usage, $levelName] = array_pad(explode('.', $usageLevel, 2), 2, null);
        [$dimension, , $usageLabel] = OlapCubes::cubes()[$cube]['dimensions'][$usage]
            ?? throw new InvalidArgumentException("Dimensi {$usage} tidak ada di kubus {$cube}.");
        $definition = OlapCubes::dimensions()[$dimension];
        $level = $definition['levels'][$levelName] ?? throw new InvalidArgumentException("Level {$usageLevel} tidak dikenal.");

        return $level + ['table' => $definition['table'], 'alias' => $usage, 'usageLabel' => $usageLabel];
    }

    /** @param  array<int, string>  $usageLevels */
    private function baseQuery(string $cube, array $usageLevels, array $filters): Builder
    {
        $definition = OlapCubes::cubes()[$cube];
        $query = $this->dw()->table($definition['fact'].' as f');

        $joined = [];
        foreach ($usageLevels as $usageLevel) {
            $usage = explode('.', $usageLevel)[0];
            if (isset($joined[$usage])) {
                continue;
            }
            [$dimension, $foreignKey] = $definition['dimensions'][$usage];
            $dim = OlapCubes::dimensions()[$dimension];
            $query->join("{$dim['table']} as {$usage}", "{$usage}.{$dim['pk']}", '=', "f.{$foreignKey}");
            $joined[$usage] = true;
        }

        foreach ($filters as $usageLevel => $memberKey) {
            $level = $this->level($cube, $usageLevel);
            $values = explode('|', (string) $memberKey);
            foreach ($level['cols'] as $i => $col) {
                $query->where("{$level['alias']}.{$col}", $values[$i] ?? null);
            }
        }

        return $query;
    }

    private function groupByLevel(Builder $query, array $level, string $prefix): void
    {
        foreach ($level['cols'] as $i => $col) {
            $column = "{$level['alias']}.{$col}";
            $query->addSelect("{$column} as {$prefix}{$i}")->groupBy($column)->orderBy($column);
        }
    }

    /** @return array{0:string, 1:string} [member key, label] */
    private function member(array $level, array $record, string $prefix): array
    {
        $values = [];
        foreach (array_keys($level['cols']) as $i) {
            $values[] = $record["{$prefix}{$i}"];
        }

        return [implode('|', $values), ($level['format'])($values)];
    }

    /** SQL siap baca (binding diganti nilai) untuk ditampilkan sebagai bahan belajar. */
    private function interpolate(Builder $query): string
    {
        $sql = $query->toSql();
        foreach ($query->getBindings() as $binding) {
            $value = is_numeric($binding) ? $binding : "'".str_replace("'", "''", (string) $binding)."'";
            $sql = preg_replace('/\?/', (string) $value, $sql, 1);
        }

        return $sql;
    }
}
