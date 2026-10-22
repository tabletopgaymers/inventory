<?php

namespace Database\Seeders;

use App\Support\CatalogRecords;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use LogicException;

class CatalogSeeder extends Seeder
{
    private const REQUIRED = ['Category', 'Collection', 'Item', 'SkuPre', 'SkuPost', 'Sku'];
    private const OPTIONAL = ['CategoryDescription', 'CollectionDescription', 'ItemDescription', 'Notes', 'Variety'];

    public function run(?string $file = null): array
    {
        $connection = DB::connection();
        if (! in_array($connection->getConfig('driver'), ['mysql', 'mariadb'], true)) {
            throw new LogicException('Catalog seeding requires the configured MySQL/MariaDB database connection.');
        }
        $rows = $this->readRows($file ?? database_path('seeders/fixtures/catalog.tab'));

        $summary = DB::transaction(function () use ($rows) {
            // Coordinate with ordinary catalog saves; never create accounts or stock.
            if (! DB::table('authentication_bootstraps')->where('key', 'initial-admin')->lockForUpdate()->first()) {
                throw new LogicException('Run database migrations before catalog seeding.');
            }
            $created = ['categories' => 0, 'collections' => 0, 'items' => 0];
            $categories = $collections = [];
            foreach ($rows as $row) {
                $category = $categories[$row['Category']] ??= $this->record('categories', ['name' => $row['Category']], [], $created);
                $key = json_encode([$row['Category'], $row['Collection']], JSON_THROW_ON_ERROR);
                $collection = $collections[$key] ??= $this->record('collections', ['category_id' => $category, 'name' => $row['Collection']], ['sku_prefix' => $row['SkuPre']], $created);
                $item = $this->record('items', ['sku' => $row['Sku']], ['collection_id' => $collection, 'name' => $row['Item'], 'sku_suffix' => $row['SkuPost'], 'unit_cost' => '0'], $created);
                $this->metadata('categories', $category, ['description' => $row['CategoryDescription'] ?? null]);
                $this->metadata('collections', $collection, ['description' => $row['CollectionDescription'] ?? null]);
                $this->metadata('items', $item, ['description' => $row['ItemDescription'] ?? null, 'notes' => $row['Notes'] ?? null]);
                $details = array_filter(['notes' => $row['Notes'] ?? null, 'variety' => $row['Variety'] ?? null], fn ($value) => $value !== null && $value !== '');
                if (! DB::table('item_metadata')->where('item_id', $item)->exists()) {
                    DB::table('item_metadata')->insert(['item_id' => $item, 'created_at' => now('UTC'), 'updated_at' => now('UTC')] + $details);
                } elseif ($details !== []) {
                    DB::table('item_metadata')->where('item_id', $item)->update($details + ['updated_at' => now('UTC')]);
                }
            }
            $summary = ['rows' => count($rows), 'categories' => count($categories), 'collections' => count($collections), 'created' => $created];
            return $summary;
        });

        $this->command?->info('SUCCESS: Catalog seed completed. '.json_encode($summary, JSON_THROW_ON_ERROR));

        return $summary;
    }

    private function readRows(string $file): array
    {
        $contents = is_file($file) ? file_get_contents($file) : false;
        if ($contents === false || ! mb_check_encoding($contents, 'UTF-8')) {
            throw new LogicException('Supply a readable UTF-8 tab-delimited catalog file.');
        }
        $handle = fopen('php://temp', 'w+');
        fwrite($handle, preg_replace('/\A\xEF\xBB\xBF/', '', $contents));
        rewind($handle);
        try {
            $header = fgetcsv($handle, 0, "\t", '"', '');
            if (! is_array($header) || count(array_unique($header)) !== count($header)
                || array_diff(self::REQUIRED, $header) || array_diff($header, array_merge(self::REQUIRED, self::OPTIONAL))) {
                throw new LogicException('Expected Category, Collection, Item, SkuPre, SkuPost, Sku and documented optional columns.');
            }
            $rows = $skus = $prefixes = $descriptions = [];
            $line = 1;
            while (($values = fgetcsv($handle, 0, "\t", '"', '')) !== false) {
                $line++;
                if ($values === [null]) {
                    continue;
                }
                if (count($values) !== count($header)) {
                    throw new LogicException("Catalog row {$line} has the wrong number of columns.");
                }
                $row = array_combine($header, $values);
                $rules = array_fill_keys(['Category', 'Collection', 'Item'], ['required', 'string', 'max:255']);
                $rules += array_fill_keys(['SkuPre', 'SkuPost'], ['required', 'string', 'max:50', 'regex:/\A[A-Za-z0-9_-]+\z/D']);
                $rules['Sku'] = ['required', 'string', 'max:101'];
                foreach (self::OPTIONAL as $field) {
                    $rules[$field] = ['nullable', 'string', 'max:'.($field === 'Variety' ? '255' : '5000')];
                }
                Validator::make($row, $rules)->validate();
                foreach (self::REQUIRED as $field) {
                    if (trim($row[$field]) !== $row[$field] || trim($row[$field]) === '') {
                        throw new LogicException("Catalog row {$line} has blank or padded identity text.");
                    }
                }
                if ($row['Sku'] !== CatalogRecords::itemSku($row['SkuPre'], $row['SkuPost'])) {
                    throw new LogicException("Catalog row {$line} must use prefix-suffix for its SKU.");
                }
                $sku = mb_strtolower($row['Sku']);
                $key = json_encode([mb_strtolower($row['Category']), mb_strtolower($row['Collection'])], JSON_THROW_ON_ERROR);
                if (isset($skus[$sku]) || (isset($prefixes[$key]) && $prefixes[$key] !== $row['SkuPre'])) {
                    throw new LogicException("Catalog row {$line} has a duplicate SKU or inconsistent collection prefix.");
                }
                // Repeated category/collection descriptions must agree within a file.
                foreach (['CategoryDescription' => $row['Category'], 'CollectionDescription' => $key] as $field => $identity) {
                    if (($row[$field] ?? '') !== '') {
                        $descriptionKey = $field.':'.$identity;
                        if (isset($descriptions[$descriptionKey]) && $descriptions[$descriptionKey] !== $row[$field]) {
                            throw new LogicException("Catalog row {$line} has conflicting descriptions.");
                        }
                        $descriptions[$descriptionKey] = $row[$field];
                    }
                }
                $skus[$sku] = true;
                $prefixes[$key] = $row['SkuPre'];
                $rows[] = $row;
            }
            if ($rows === []) {
                throw new LogicException('The catalog file contains no item rows.');
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    private function record(string $table, array $identity, array $values, array &$created): int
    {
        $matches = DB::table($table)->where($identity)->lockForUpdate()->get();
        if ($matches->count() > 1) {
            throw new LogicException("Ambiguous existing {$table} identity; no catalog changes committed.");
        }
        $existing = $matches->first();
        if ($existing) {
            foreach ($values as $field => $value) {
                if ($field !== 'unit_cost' && (string) $existing->$field !== (string) $value) {
                    throw new LogicException("Existing {$table} identity conflicts with the file; no catalog changes committed.");
                }
            }
            $id = (int) $existing->id;
        } else {
            $id = (int) DB::table($table)->insertGetId($identity + $values + ['created_at' => now('UTC'), 'updated_at' => now('UTC')]);
            $created[$table]++;
        }
        if ($table !== 'items') {
            $registry = ['kind' => $table, 'record_id' => $id];
            $name = ['parent_id' => $identity['category_id'] ?? 0, 'name' => $identity['name']];
            $entry = DB::table('catalog_names')->where($registry)->first();
            if (! $entry) {
                DB::table('catalog_names')->insert($registry + $name);
            } elseif ((int) $entry->parent_id !== $name['parent_id'] || $entry->name !== $name['name']) {
                throw new LogicException('Existing catalog name registry conflicts with the file.');
            }
        }

        return $id;
    }

    private function metadata(string $kind, int $id, array $fields): void
    {
        $identity = ['kind' => $kind, 'record_id' => $id];
        $fields = array_filter($fields, fn ($value) => $value !== null && $value !== '');
        if (! DB::table('catalog_metadata')->where($identity)->exists()) {
            DB::table('catalog_metadata')->insert($identity + $fields + ['state' => 'active', 'created_at' => now('UTC'), 'updated_at' => now('UTC')]);
        } elseif ($fields !== []) {
            DB::table('catalog_metadata')->where($identity)->update($fields + ['updated_at' => now('UTC')]);
        }
    }
}
