<?php

namespace App\Filament\Widgets;

use App\Models\Receipt;
use App\Models\SteuerDocument;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Speicherplatz-Übersicht fürs Dashboard: Größe der Datenbank und des
 * Beleg-Ordners (wo die hochgeladenen PDFs liegen). Ergebnisse werden kurz
 * gecacht, damit das Dashboard schnell bleibt.
 */
class SpeicherplatzWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 9;

    protected function getStats(): array
    {
        [$dbBytes, $dbTables] = Cache::remember('speicher.db', 300, fn () => $this->databaseSize());
        [$belegBytes, $belegCount] = Cache::remember('speicher.belege', 300, fn () => $this->belegeSize());

        return [
            Stat::make('Datenbank', $this->human($dbBytes))
                ->description($dbTables . ' Tabellen')
                ->icon('heroicon-o-circle-stack')
                ->color('info'),

            Stat::make('Beleg-Ordner (PDFs)', $this->human($belegBytes))
                ->description($belegCount . ' Dateien')
                ->icon('heroicon-o-folder')
                ->color('warning'),

            Stat::make('Gesamt', $this->human($dbBytes + $belegBytes))
                ->description('Datenbank + Belege')
                ->icon('heroicon-o-server')
                ->color('success'),
        ];
    }

    /** @return array{0:int,1:int} [Bytes, Tabellenanzahl] */
    private function databaseSize(): array
    {
        try {
            $driver = DB::connection()->getDriverName();

            if ($driver === 'mysql' || $driver === 'mariadb') {
                $row = DB::selectOne(
                    'SELECT COALESCE(SUM(data_length + index_length),0) AS bytes, COUNT(*) AS tables
                     FROM information_schema.tables WHERE table_schema = ?',
                    [DB::getDatabaseName()]
                );

                return [(int) ($row->bytes ?? 0), (int) ($row->tables ?? 0)];
            }

            if ($driver === 'sqlite') {
                $pc = DB::selectOne('PRAGMA page_count');
                $ps = DB::selectOne('PRAGMA page_size');
                $tables = DB::selectOne("SELECT COUNT(*) AS c FROM sqlite_master WHERE type='table'");

                return [
                    (int) ($pc->page_count ?? 0) * (int) ($ps->page_size ?? 0),
                    (int) ($tables->c ?? 0),
                ];
            }
        } catch (Throwable) {
            // still 0 zurück
        }

        return [0, 0];
    }

    /**
     * Größe des Beleg-Ordners aus der DB summieren (die Tabellen führen je Datei
     * die Bytegröße) – eine Aggregat-Query statt tausender Datei-Zugriffe.
     *
     * @return array{0:int,1:int} [Bytes, Dateianzahl]
     */
    private function belegeSize(): array
    {
        try {
            $bytes = (int) Receipt::sum('file_size') + (int) SteuerDocument::sum('file_size');
            $count = (int) Receipt::whereNotNull('file_path')->count()
                + (int) SteuerDocument::whereNotNull('file_path')->count();

            return [$bytes, $count];
        } catch (Throwable) {
            return [0, 0];
        }
    }

    private function human(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $value = (float) $bytes;
        $i = 0;
        while ($value >= 1024 && $i < count($units) - 1) {
            $value /= 1024;
            $i++;
        }

        return number_format($value, $i >= 2 ? 1 : 0, ',', '.') . ' ' . $units[$i];
    }
}
