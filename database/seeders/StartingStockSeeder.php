<?php

namespace Database\Seeders;

use App\Support\CatalogRecords;
use App\Support\StockNumbers;
use App\Support\StockPosting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

class StartingStockSeeder extends Seeder
{
    public function run(?string $file = null, ?int $actorId = null): array
    {
        if (! in_array(DB::connection()->getConfig('driver'), ['mysql', 'mariadb'], true)) {
            throw new LogicException('Starting stock requires the configured MySQL/MariaDB connection.');
        }
        [$headers, $rows] = $this->readRows($file ?? database_path('seeders/fixtures/starting-stock.csv'));

        $summary = DB::transaction(function () use ($headers, $rows, $actorId) {
            $bootstrap = DB::table('authentication_bootstraps')->where('key', 'initial-admin')->lockForUpdate()->firstOrFail();
            $actorId ??= $bootstrap->user_id ? (int) $bootstrap->user_id : null;
            if (! $actorId) {
                throw new LogicException('Supply an existing enabled Admin/Manager with --actor, or sign in as the initial administrator first.');
            }
            app(CatalogRecords::class)->authorize($actorId);

            $locations = DB::table('storage_locations')->orderBy('id')->lockForUpdate()->get();
            $locationIds = [];
            foreach ($headers as $name) {
                $matches = DB::table('storage_locations')->where('name', $name)->get();
                if ($matches->count() !== 1) {
                    throw new LogicException("Location '{$name}' is missing or ambiguous; create it before importing. No stock changed.");
                }
                $id = (int) $matches->first()->id;
                if (in_array($id, $locationIds, true)) {
                    throw new LogicException('Two CSV columns refer to the same location. No stock changed.');
                }
                $locationIds[$name] = $id;
            }

            // Resolve every SKU before the first posting, even rows with all blanks.
            $plans = [];
            $seenItems = [];
            foreach ($rows as $row) {
                $matches = DB::table('items')->where('sku', $row['sku'])->lockForUpdate()->get();
                if ($matches->count() !== 1) {
                    throw new LogicException("CSV row {$row['row']}: SKU '{$row['sku']}' is missing or ambiguous. No stock changed.");
                }
                $itemId = (int) $matches->first()->id;
                if (isset($seenItems[$itemId])) {
                    throw new LogicException("CSV row {$row['row']}: duplicate item. No stock changed.");
                }
                $seenItems[$itemId] = true;
                $plans[] = ['id' => $itemId, 'quantities' => $row['quantities']];
            }

            $summary = ['rows' => count($rows), 'quantity_cells' => 0, 'posted_items' => 0, 'unchanged_items' => 0, 'blank_items' => 0];
            foreach ($plans as $plan) {
                $quantities = $plan['quantities'];
                $summary['quantity_cells'] += count($quantities);
                if ($quantities === []) {
                    $summary['blank_items']++;
                    continue;
                }
                $balances = DB::table('inventory_balances')->where('item_id', $plan['id'])->orderBy('storage_location_id')->lockForUpdate()->get()->keyBy('storage_location_id');
                $starting = $balances->isEmpty();
                $changed = false;
                foreach ($quantities as $name => $quantity) {
                    if ((int) ($balances->get($locationIds[$name])->quantity ?? 0) !== $quantity) {
                        $changed = true;
                    }
                }
                // A first explicit zero still establishes starting stock/history.
                if (! $starting && ! $changed) {
                    $summary['unchanged_items']++;
                    continue;
                }
                $postingRows = [];
                if (! $starting) {
                    // Normal corrections require all locations: retain omitted/blanks.
                    foreach ($locations as $location) {
                        $postingRows[$location->id] = ['set' => (string) ($balances->get($location->id)->quantity ?? 0), 'rationale' => 'Starting stock CSV: absolute quantity import'];
                    }
                }
                foreach ($quantities as $name => $quantity) {
                    $postingRows[$locationIds[$name]] = ['set' => (string) $quantity, 'rationale' => 'Starting stock CSV: absolute quantity import'];
                }
                app(StockPosting::class)->post($actorId, $plan['id'], (string) Str::uuid(), $postingRows, $starting);
                $summary['posted_items']++;
            }

            return $summary;
        }, 3);

        $this->command?->info('SUCCESS: Starting-stock seed completed. '.json_encode($summary, JSON_THROW_ON_ERROR));

        return $summary;
    }

    private function readRows(string $file): array
    {
        $contents = is_file($file) ? file_get_contents($file) : false;
        if ($contents === false || ! mb_check_encoding($contents, 'UTF-8')) {
            throw new LogicException('Supply a readable UTF-8 starting-stock CSV file.');
        }
        $handle = fopen('php://temp', 'w+');
        fwrite($handle, preg_replace('/\A\xEF\xBB\xBF/', '', $contents));
        rewind($handle);
        try {
            $header = fgetcsv($handle, 0, ',', '"', '');
            if (! is_array($header) || count($header) < 2 || ! in_array('SKU', $header, true)) {
                throw new LogicException('CSV requires a SKU header and at least one existing location header.');
            }
            $keys = [];
            foreach ($header as $name) {
                if ($name === '' || trim($name) !== $name || isset($keys[mb_strtolower($name)])) {
                    throw new LogicException('CSV headers must be nonblank, unpadded and unique.');
                }
                $keys[mb_strtolower($name)] = true;
            }
            $locations = array_values(array_diff($header, ['SKU']));
            $rows = $seen = [];
            $line = 1;
            while (($values = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                $line++;
                if ($values === [null]) {
                    continue;
                }
                if (count($values) !== count($header)) {
                    throw new LogicException("CSV row {$line} has the wrong number of columns.");
                }
                $row = array_combine($header, $values);
                $sku = trim($row['SKU']);
                if ($sku === '' || mb_strlen($sku) > 101 || isset($seen[mb_strtolower($sku)])) {
                    throw new LogicException("CSV row {$line} has a blank, invalid or duplicate SKU.");
                }
                $seen[mb_strtolower($sku)] = true;
                $quantities = [];
                foreach ($locations as $name) {
                    $text = trim($row[$name]);
                    if ($text === '') {
                        continue;
                    }
                    if (! preg_match('/\A[+-]?(?:[0-9]+|[0-9]{1,3}(?:,[0-9]{3})+)\z/D', $text)) {
                        throw new LogicException("CSV row {$line}, location '{$name}': enter a whole number or leave blank.");
                    }
                    $quantities[$name] = StockNumbers::quantity(str_replace(',', '', $text), 'csv.'.$line.'.'.$name);
                }
                $rows[] = ['row' => $line, 'sku' => $sku, 'quantities' => $quantities];
            }
            if ($rows === []) {
                throw new LogicException('Starting-stock CSV contains no item rows.');
            }

            return [$locations, $rows];
        } finally {
            fclose($handle);
        }
    }
}
