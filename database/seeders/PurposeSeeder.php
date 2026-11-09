<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use LogicException;

class PurposeSeeder extends Seeder
{
    private const HEADERS = ['name', 'description', 'examples'];

    public function run(?string $file = null): array
    {
        if (! in_array(DB::connection()->getConfig('driver'), ['mysql', 'mariadb'], true)) {
            throw new LogicException('Purpose seeding requires the configured MySQL/MariaDB connection.');
        }
        $rows = $this->readRows($file ?? database_path('fixtures/purposes.csv'));

        $summary = DB::transaction(function () use ($rows) {
            DB::table('authentication_bootstraps')->where('key', 'initial-admin')->lockForUpdate()->firstOrFail();
            $summary = ['rows' => count($rows), 'created' => 0, 'updated' => 0, 'unchanged' => 0];
            $seen = [];

            foreach ($rows as $row) {
                $identity = ['kind' => 'purposes', 'name' => $row['name']];
                $existing = DB::table('catalog_references')->where($identity)->lockForUpdate()->first();
                $details = array_filter(['description' => $row['description'], 'examples' => $row['examples']], fn ($value) => $value !== '');

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
                    throw new LogicException('Two CSV rows match the same purpose name.');
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

        $this->command?->info('SUCCESS: Purpose seed completed. '.json_encode($summary, JSON_THROW_ON_ERROR));

        return $summary;
    }

    private function readRows(string $file): array
    {
        $contents = is_file($file) ? file_get_contents($file) : false;
        if ($contents === false || ! mb_check_encoding($contents, 'UTF-8')) {
            throw new LogicException('Supply a readable UTF-8 CSV in database/fixtures/purposes.csv, or select one with --file.');
        }
        $handle = fopen('php://temp', 'w+');
        fwrite($handle, preg_replace('/\A\xEF\xBB\xBF/', '', $contents));
        rewind($handle);

        try {
            $header = fgetcsv($handle, 0, ',', '"', '');
            if (! is_array($header) || count($header) !== count(self::HEADERS) || array_diff(self::HEADERS, $header)) {
                throw new LogicException('Expected name, description and examples columns.');
            }
            $rows = $names = [];
            $line = 1;
            $multiline = ['nullable', 'string', 'max:5000', 'not_regex:/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u'];
            while (($values = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                $line++;
                if ($values === [null]) {
                    continue;
                }
                if (count($values) !== count($header)) {
                    throw new LogicException("Purpose CSV row {$line} has the wrong number of columns.");
                }
                $row = array_map('trim', array_combine($header, $values));
                $validation = Validator::make($row, [
                    'name' => ['required', 'string', 'max:255', 'not_regex:/\p{Cc}/u'],
                    'description' => $multiline,
                    'examples' => $multiline,
                ]);
                if ($validation->fails()) {
                    throw new LogicException("Purpose CSV row {$line} has invalid fields: ".implode(', ', $validation->errors()->keys()).'.');
                }
                $key = mb_strtolower($row['name']);
                if (isset($names[$key])) {
                    throw new LogicException("Purpose CSV row {$line} repeats a purpose name.");
                }
                $names[$key] = true;
                $rows[] = $row;
            }
            if ($rows === []) {
                throw new LogicException('The purpose CSV contains no purpose rows.');
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }
}
