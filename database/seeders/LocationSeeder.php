<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use LogicException;

class LocationSeeder extends Seeder
{
    private const HEADERS = ['isCentral', 'name', 'description', 'status', 'addressTo', 'address', 'city', 'state', 'zipCode'];

    public function run(?string $file = null): array
    {
        if (! in_array(DB::connection()->getConfig('driver'), ['mysql', 'mariadb'], true)) {
            throw new LogicException('Location seeding requires the configured MySQL/MariaDB connection.');
        }
        $rows = $this->readRows($file ?? database_path('fixtures/locations.csv'));

        $summary = DB::transaction(function () use ($rows) {
            DB::table('authentication_bootstraps')->where('key', 'initial-admin')->lockForUpdate()->firstOrFail();
            $central = DB::table('storage_locations')->where('is_central', true)->lockForUpdate()->get();
            if ($central->count() > 1) {
                throw new LogicException('Multiple existing Central locations require correction before importing.');
            }
            $plans = [];
            $seen = [];
            foreach ($rows as $row) {
                $matches = DB::table('storage_locations')->where('name', $row['name'])->lockForUpdate()->get();
                if ($matches->count() > 1) {
                    throw new LogicException('An existing location name is ambiguous; no locations changed.');
                }
                $existing = $matches->first();
                if ($existing && (isset($seen[$existing->id]) || (bool) $existing->is_central !== $row['is_central'])) {
                    throw new LogicException('Duplicate location identity or conflicting Central flag; no locations changed.');
                }
                if ($row['is_central'] && $central->isNotEmpty() && (int) ($existing->id ?? 0) !== (int) $central->first()->id) {
                    throw new LogicException('The fixture cannot replace the existing Central location.');
                }
                if ($existing) $seen[$existing->id] = true;
                $plans[] = [$row, $existing];
            }

            $summary = ['rows' => count($rows), 'created' => 0, 'updated' => 0, 'unchanged' => 0];
            foreach ($plans as [$row, $existing]) {
                $now = now('UTC');
                $id = $existing ? (int) $existing->id : (int) DB::table('storage_locations')->insertGetId([
                    'name' => $row['name'], 'is_central' => $row['is_central'], 'created_at' => $now, 'updated_at' => $now,
                ]);
                $identity = ['kind' => 'storage_locations', 'record_id' => $id];
                $registry = DB::table('catalog_names')->where($identity)->lockForUpdate()->first();
                $changed = false;
                if (! $registry) {
                    DB::table('catalog_names')->insert($identity + ['parent_id' => 0, 'name' => $existing->name ?? $row['name']]);
                    $changed = true;
                } elseif ((int) $registry->parent_id !== 0 || $registry->name !== ($existing->name ?? $row['name'])) {
                    throw new LogicException('Existing location name registry conflicts with the location; no locations changed.');
                }
                $metadata = DB::table('catalog_metadata')->where($identity)->lockForUpdate()->first();
                if ($row['is_central'] && $metadata && $metadata->state !== 'active') {
                    throw new LogicException('The existing Central location must be active; no locations changed.');
                }
                $fields = ['mailing_address' => $row['mailing_address']];
                if ($row['description'] !== '') $fields['description'] = $row['description'];
                if (! $metadata) {
                    DB::table('catalog_metadata')->insert($identity + $fields + ['state' => $row['status'], 'created_at' => $now, 'updated_at' => $now]);
                    $changed = true;
                } else {
                    $changes = array_filter($fields, fn ($value, $key) => $metadata->{$key} !== $value, ARRAY_FILTER_USE_BOTH);
                    if ($changes !== []) {
                        DB::table('catalog_metadata')->where('id', $metadata->id)->update($changes + ['updated_at' => $now]);
                        $changed = true;
                    }
                }
                $summary[! $existing ? 'created' : ($changed ? 'updated' : 'unchanged')]++;
            }

            return $summary;
        }, 3);

        $this->command?->info('SUCCESS: Location seed completed. '.json_encode($summary, JSON_THROW_ON_ERROR));

        return $summary;
    }

    private function readRows(string $file): array
    {
        $contents = is_file($file) ? file_get_contents($file) : false;
        if ($contents === false || ! mb_check_encoding($contents, 'UTF-8')) {
            throw new LogicException('Supply a readable UTF-8 CSV in database/fixtures/locations.csv, or select one with --file.');
        }
        $handle = fopen('php://temp', 'w+');
        fwrite($handle, preg_replace('/\A\xEF\xBB\xBF/', '', $contents));
        rewind($handle);
        try {
            $header = fgetcsv($handle, 0, ',', '"', '');
            if (! is_array($header) || count($header) !== count(self::HEADERS)
                || count(array_unique($header)) !== count($header) || array_diff(self::HEADERS, $header)) {
                throw new LogicException('Expected isCentral, name, description, status, addressTo, address, city, state and zipCode columns.');
            }
            $rows = $names = [];
            $line = 1;
            $centralCount = 0;
            while (($values = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                $line++;
                if ($values === [null]) continue;
                if (count($values) !== count($header)) throw new LogicException("Location CSV row {$line} has the wrong number of columns.");
                $row = array_map('trim', array_combine($header, $values));
                $row['status'] = strtolower($row['status']);
                $single = ['nullable', 'string', 'max:255', 'not_regex:/\p{Cc}/u'];
                Validator::make($row, [
                    'isCentral' => ['required', 'in:0,1'],
                    'name' => ['required', 'string', 'max:255', 'not_regex:/\p{Cc}/u'],
                    'description' => ['nullable', 'string', 'max:5000', 'not_regex:/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u'],
                    'status' => ['required', 'in:active,inactive,archived'],
                    'addressTo' => $single,
                    'address' => ['required', 'string', 'max:4000', 'not_regex:/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u'],
                    'city' => ['required', 'string', 'max:255', 'not_regex:/\p{Cc}/u'],
                    'state' => $single,
                    'zipCode' => $single,
                ])->validate();
                $key = mb_strtolower($row['name']);
                if (isset($names[$key])) throw new LogicException("Location CSV row {$line} repeats a location name.");
                $names[$key] = true;
                $row['is_central'] = $row['isCentral'] === '1';
                if ($row['is_central']) {
                    $centralCount++;
                    if ($row['status'] !== 'active') throw new LogicException('The Central location must be active.');
                }
                $place = implode(' ', array_filter([$row['city'], $row['state'], $row['zipCode']], fn ($part) => $part !== ''));
                $row['mailing_address'] = implode("\n", array_filter([$row['addressTo'], $row['address'], $place], fn ($part) => $part !== ''));
                if (mb_strlen($row['mailing_address']) > 5000) throw new LogicException("Location CSV row {$line} has an oversized mailing address.");
                $rows[] = $row;
            }
            if ($rows === [] || $centralCount !== 1) throw new LogicException('Supply location rows with exactly one active Central location.');

            return $rows;
        } finally {
            fclose($handle);
        }
    }
}
