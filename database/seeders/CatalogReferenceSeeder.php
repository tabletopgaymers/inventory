<?php

namespace Database\Seeders;

use App\Support\CatalogRecords;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use LogicException;

abstract class CatalogReferenceSeeder extends Seeder
{
    protected function seedReferences(string $kind, array $detailFields, ?string $file): array
    {
        $name = CatalogRecords::SINGULAR[$kind];
        $label = ucfirst($name);
        if (! in_array(DB::connection()->getConfig('driver'), ['mysql', 'mariadb'], true)) {
            throw new LogicException("{$label} seeding requires the configured MySQL/MariaDB connection.");
        }
        $rows = $this->readRows($file ?? database_path("fixtures/{$kind}.csv"), $kind, $detailFields);

        $summary = DB::transaction(function () use ($rows, $kind, $name) {
            DB::table('authentication_bootstraps')->where('key', 'initial-admin')->lockForUpdate()->firstOrFail();
            $summary = ['rows' => count($rows), 'created' => 0, 'updated' => 0, 'unchanged' => 0];
            $seen = [];

            foreach ($rows as $row) {
                $identity = ['kind' => $kind, 'name' => $row['name']];
                $existing = DB::table('catalog_references')->where($identity)->lockForUpdate()->first();
                unset($row['name']);
                $details = array_filter($row, fn ($value) => $value !== '');

                if (! $existing) {
                    $now = now('UTC');
                    $id = DB::table('catalog_references')->insertGetId($identity + $details + [
                        'state' => 'active', 'created_at' => $now, 'updated_at' => $now,
                    ]);
                    $seen[$id] = true;
                    $summary['created']++;

                    continue;
                }

                if (isset($seen[$existing->id])) {
                    throw new LogicException("Two CSV rows match the same {$name} name.");
                }
                $seen[$existing->id] = true;

                $changes = array_filter($details, fn ($value, $field) => $existing->{$field} !== $value, ARRAY_FILTER_USE_BOTH);
                if ($changes === []) {
                    $summary['unchanged']++;

                    continue;
                }

                DB::table('catalog_references')->where('id', $existing->id)->update($changes + ['updated_at' => now('UTC')]);
                $summary['updated']++;
            }

            return $summary;
        }, 3);

        $this->command?->info("SUCCESS: {$label} seed completed. ".json_encode($summary, JSON_THROW_ON_ERROR));

        return $summary;
    }

    private function readRows(string $file, string $kind, array $detailFields): array
    {
        $name = CatalogRecords::SINGULAR[$kind];
        $label = ucfirst($name);
        $expectedHeaders = ['name', ...$detailFields];
        $contents = is_file($file) ? file_get_contents($file) : false;
        if ($contents === false || ! mb_check_encoding($contents, 'UTF-8')) {
            throw new LogicException("Supply a readable UTF-8 CSV in database/fixtures/{$kind}.csv, or select one with --file.");
        }
        $handle = fopen('php://temp', 'w+');
        fwrite($handle, preg_replace('/\A\xEF\xBB\xBF/', '', $contents));
        rewind($handle);

        try {
            $header = fgetcsv($handle, 0, ',', '"', '');
            if (! is_array($header) || count($header) !== count($expectedHeaders) || array_diff($expectedHeaders, $header)) {
                throw new LogicException('Expected columns: '.implode(', ', $expectedHeaders).'.');
            }
            $rows = $names = [];
            $line = 1;
            $multiline = ['nullable', 'string', 'max:5000', 'not_regex:/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u'];
            $rules = ['name' => ['required', 'string', 'max:255', 'not_regex:/\p{Cc}/u']]
                + array_fill_keys($detailFields, $multiline);
            while (($values = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                $line++;
                if ($values === [null]) {
                    continue;
                }
                if (count($values) !== count($header)) {
                    throw new LogicException("{$label} CSV row {$line} has the wrong number of columns.");
                }
                $row = array_map('trim', array_combine($header, $values));
                $validation = Validator::make($row, $rules);
                if ($validation->fails()) {
                    throw new LogicException("{$label} CSV row {$line} has invalid fields: ".implode(', ', $validation->errors()->keys()).'.');
                }
                $key = mb_strtolower($row['name']);
                if (isset($names[$key])) {
                    throw new LogicException("{$label} CSV row {$line} repeats a {$name} name.");
                }
                $names[$key] = true;
                $rows[] = $row;
            }
            if ($rows === []) {
                throw new LogicException("The {$label} CSV contains no records.");
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }
}
