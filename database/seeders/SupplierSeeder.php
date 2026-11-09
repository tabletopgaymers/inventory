<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use LogicException;

class SupplierSeeder extends Seeder
{
    private const HEADERS = ['name', 'status', 'website', 'contactName', 'email', 'phone', 'address', 'city', 'state', 'zip', 'notes'];

    public function run(?string $file = null): array
    {
        if (! in_array(DB::connection()->getConfig('driver'), ['mysql', 'mariadb'], true)) {
            throw new LogicException('Supplier seeding requires the configured MySQL/MariaDB connection.');
        }
        $rows = $this->readRows($file ?? database_path('fixtures/suppliers.csv'));

        $summary = DB::transaction(function () use ($rows) {
            DB::table('authentication_bootstraps')->where('key', 'initial-admin')->lockForUpdate()->firstOrFail();
            $summary = ['rows' => count($rows), 'created' => 0, 'updated' => 0, 'unchanged' => 0];
            $seen = [];

            foreach ($rows as $row) {
                $identity = ['kind' => 'suppliers', 'name' => $row['name']];
                $existing = DB::table('catalog_references')->where($identity)->lockForUpdate()->first();
                $fields = array_filter($row['details'], fn ($value) => $value !== '');

                if (! $existing) {
                    $now = now('UTC');
                    $id = DB::table('catalog_references')->insertGetId($identity + $fields + [
                        'state' => $row['status'], 'created_at' => $now, 'updated_at' => $now,
                    ]);
                    $seen[$id] = true;
                    $summary['created']++;

                    continue;
                }

                if (isset($seen[$existing->id])) {
                    throw new LogicException('Two CSV rows match the same supplier name.');
                }
                $seen[$existing->id] = true;

                $changes = array_filter($fields, fn ($value, $key) => $existing->{$key} !== $value, ARRAY_FILTER_USE_BOTH);
                if ($changes === []) {
                    $summary['unchanged']++;

                    continue;
                }

                DB::table('catalog_references')->where('id', $existing->id)->update($changes + ['updated_at' => now('UTC')]);
                $summary['updated']++;
            }

            return $summary;
        }, 3);

        $this->command?->info('SUCCESS: Supplier seed completed. '.json_encode($summary, JSON_THROW_ON_ERROR));

        return $summary;
    }

    private function readRows(string $file): array
    {
        $contents = is_file($file) ? file_get_contents($file) : false;
        if ($contents === false || ! mb_check_encoding($contents, 'UTF-8')) {
            throw new LogicException('Supply a readable UTF-8 CSV in database/fixtures/suppliers.csv, or select one with --file.');
        }
        $handle = fopen('php://temp', 'w+');
        fwrite($handle, preg_replace('/\A\xEF\xBB\xBF/', '', $contents));
        rewind($handle);

        try {
            $header = fgetcsv($handle, 0, ',', '"', '');
            if (! is_array($header) || count($header) !== count(self::HEADERS) || array_diff(self::HEADERS, $header)) {
                throw new LogicException('Expected name, status, website, contactName, email, phone, address, city, state, zip and notes columns.');
            }

            $rows = $names = [];
            $line = 1;
            $single = ['nullable', 'string', 'max:255', 'not_regex:/\p{Cc}/u'];
            $multiline = ['nullable', 'string', 'max:5000', 'not_regex:/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u'];
            while (($values = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                $line++;
                if ($values === [null]) {
                    continue;
                }
                if (count($values) !== count($header)) {
                    throw new LogicException("Supplier CSV row {$line} has the wrong number of columns.");
                }

                $row = array_map('trim', array_combine($header, $values));
                $row['status'] = strtolower($row['status']);
                Validator::make($row, [
                    'name' => ['required', 'string', 'max:255', 'not_regex:/\p{Cc}/u'],
                    'status' => ['required', 'in:active,inactive,archived,unverified'],
                    'website' => ['nullable', 'string', 'max:255', 'url:http,https'],
                    'contactName' => $single,
                    'email' => ['nullable', 'string', 'max:255', 'email'],
                    'phone' => $single,
                    'address' => $multiline,
                    'city' => $single,
                    'state' => $single,
                    'zip' => $single,
                    'notes' => $multiline,
                ])->validate();

                $key = mb_strtolower($row['name']);
                if (isset($names[$key])) {
                    throw new LogicException("Supplier CSV row {$line} repeats a supplier name.");
                }
                $names[$key] = true;

                $place = implode(' ', array_filter([$row['city'], $row['state'], $row['zip']], fn ($part) => $part !== ''));
                $address = implode("\n", array_filter([$row['address'], $place], fn ($part) => $part !== ''));
                if (mb_strlen($address) > 5000) {
                    throw new LogicException("Supplier CSV row {$line} has an oversized mailing address.");
                }

                $rows[] = ['name' => $row['name'], 'status' => $row['status'], 'details' => [
                    'website' => $row['website'], 'contact_name' => $row['contactName'], 'email' => $row['email'],
                    'phone' => $row['phone'], 'address' => $address, 'notes' => $row['notes'],
                ]];
            }
            if ($rows === []) {
                throw new LogicException('The supplier CSV contains no supplier rows.');
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }
}
