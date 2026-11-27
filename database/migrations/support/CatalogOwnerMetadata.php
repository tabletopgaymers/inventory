<?php

namespace Database\Migrations\Support;

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use LogicException;
use stdClass;

/** Transition from the legacy metadata table while its values are still authoritative. */
final class CatalogOwnerMetadata
{
    private const FIELDS = [
        'categories' => ['state', 'description'],
        'collections' => ['state', 'description'],
        'items' => ['state'],
        'storage_locations' => ['state', 'description', 'mailing_address'],
    ];

    private const STATES = ['active', 'inactive', 'archived'];

    private const MISSING_ADDRESS = 'address missing';

    public static function expand(Connection $db): void
    {
        $schema = $db->getSchemaBuilder();
        $existing = [];
        foreach (self::FIELDS as $table => $fields) {
            if (! $schema->hasTable($table)) {
                throw new LogicException("Catalog owner table is missing: {$table}.");
            }
            foreach (array_intersect($fields, array_map(strtolower(...), $schema->getColumnListing($table))) as $field) {
                $existing[] = "{$table}.{$field}";
            }
        }
        if ($existing !== []) {
            throw new LogicException('Catalog owner columns already exist. Inspect migration progress before repair: '.implode(', ', $existing));
        }

        self::assertLegacyShape($db);
        self::assertLegacy($db);
        foreach (self::FIELDS as $table => $fields) {
            $schema->table($table, function (Blueprint $blueprint) use ($fields) {
                $blueprint->string('state', 10)->default('active');
                if (in_array('description', $fields, true)) {
                    $blueprint->text('description')->nullable();
                }
                if (in_array('mailing_address', $fields, true)) {
                    $blueprint->text('mailing_address')->default(self::MISSING_ADDRESS);
                }
            });
        }

        self::refresh($db);
    }

    /** Require the historical expansion contract before final copy and drop. */
    public static function assertExpanded(Connection $db): void
    {
        if (! $db->table('migrations')->where('migration', '2026_11_27_000000_expand_catalog_owner_metadata')->exists()) {
            throw new LogicException('The Catalog owner expansion is not recorded. Inspect before cutover.');
        }
        $contracts = [
            'state' => ['varchar(10)', false, ['active', "'active'"]],
            'description' => ['text', true, [null, 'NULL']],
            'mailing_address' => ['text', false, [self::MISSING_ADDRESS, "'".self::MISSING_ADDRESS."'"]],
        ];
        $schema = $db->getSchemaBuilder();
        foreach (self::FIELDS as $table => $fields) {
            $columns = array_column($schema->getColumns($table), null, 'name');
            foreach ($fields as $field) {
                $column = $columns[$field] ?? null;
                [$type, $nullable, $defaults] = $contracts[$field];
                if (! $column || strtolower($column['type']) !== $type || $column['nullable'] !== $nullable
                    || $column['auto_increment'] || $column['generation'] !== null || ! in_array($column['default'], $defaults, true)) {
                    throw new LogicException("Expanded Catalog field {$table}.{$field} does not match its contract. Inspect before cutover.");
                }
            }
        }
    }

    /** Copy the last legacy values and verify them before the legacy table is retired. */
    public static function refresh(Connection $db): array
    {
        self::assertLegacyShape($db);

        // MariaDB commits each ALTER independently. Only the value copy and its checks are transactional.
        return $db->transaction(function () use ($db) {
            $counts = self::assertLegacy($db);
            $notes = self::itemNotesFingerprint($db);
            foreach (self::FIELDS as $table => $fields) {
                self::rows($db, $table, $fields)->chunkById(500, function ($rows) use ($db, $table, $fields) {
                    foreach ($rows as $row) {
                        $values = self::expected($row, $fields);
                        if (self::differs($row, $values)) {
                            $db->table($table)->where('id', $row->id)->update($values);
                        }
                    }
                }, 'owner.id', 'id');
            }
            self::verifyCopy($db);
            if ($notes !== self::itemNotesFingerprint($db)) {
                throw new LogicException('Canonical Item notes changed during the Catalog owner copy.');
            }

            return $counts;
        });
    }

    /** Count unsupported rows and populated retired fields without printing their contents. */
    public static function legacyAssessment(Connection $db): array
    {
        $counts = array_fill_keys([
            'unknown_kind', 'orphan', 'invalid_state', 'inactive_central',
            'item_description', 'duplicate_item_notes', 'item_notes_disagreement',
            'other_notes', 'non_location_address',
        ], 0);

        $db->table('catalog_metadata as metadata')
            ->leftJoin('item_metadata as canonical', function ($join) {
                $join->on('canonical.item_id', '=', 'metadata.record_id')->where('metadata.kind', 'items');
            })
            ->select('metadata.*', 'canonical.notes as canonical_notes')
            ->chunkById(500, function ($rows) use (&$counts) {
                foreach ($rows as $row) {
                    $counts['unknown_kind'] += (int) ! array_key_exists($row->kind, self::FIELDS);
                    $counts['invalid_state'] += (int) ! in_array($row->state, self::STATES, true);
                    if ($row->kind === 'items') {
                        $counts['item_description'] += (int) self::filled($row->description);
                        $counts['duplicate_item_notes'] += (int) self::filled($row->notes);
                        $counts['item_notes_disagreement'] += (int) ($row->notes !== $row->canonical_notes);
                    } else {
                        $counts['other_notes'] += (int) self::filled($row->notes);
                    }
                    if ($row->kind !== 'storage_locations') {
                        $counts['non_location_address'] += (int) self::filled($row->mailing_address);
                    }
                }
            }, 'metadata.id', 'id');

        foreach (self::FIELDS as $table => $fields) {
            $counts['orphan'] += $db->table('catalog_metadata as metadata')
                ->where('metadata.kind', $table)
                ->whereNotExists(fn ($query) => $query->from($table)->whereColumn($table.'.id', 'metadata.record_id'))
                ->count();
        }
        $counts['inactive_central'] = $db->table('storage_locations as location')
            ->join('catalog_metadata as metadata', function ($join) {
                $join->on('metadata.record_id', '=', 'location.id')->where('metadata.kind', 'storage_locations');
            })
            ->where('location.is_central', true)->where('metadata.state', '<>', 'active')->count();
        $counts['central_locations'] = $db->table('storage_locations')->where('is_central', true)->count();

        return $counts;
    }

    private static function assertLegacyShape(Connection $db): void
    {
        $schema = $db->getSchemaBuilder();
        $columns = [];
        foreach ($schema->getColumns('catalog_metadata') as $column) {
            if ($column['generation'] !== null) {
                throw new LogicException('The legacy Catalog metadata table has generated columns. Inspect before removal.');
            }
            $columns[$column['name']] = [preg_replace('/\bbigint\(\d+\)/', 'bigint', strtolower($column['type'])), $column['nullable'], $column['auto_increment']];
        }
        $expected = [
            'id' => ['bigint unsigned', false, true],
            'kind' => ['varchar(30)', false, false],
            'record_id' => ['bigint unsigned', false, false],
            'state' => ['varchar(10)', false, false],
            'description' => ['text', true, false],
            'notes' => ['text', true, false],
            'created_at' => ['timestamp', true, false],
            'updated_at' => ['timestamp', true, false],
            'mailing_address' => ['text', true, false],
        ];
        $indexes = $schema->getIndexes('catalog_metadata');
        $primary = array_column(array_filter($indexes, fn ($index) => $index['primary']), 'columns');
        $unique = array_column(array_filter($indexes, fn ($index) => $index['unique'] && ! $index['primary']), 'columns');
        $table = $db->selectOne('SELECT ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', ['catalog_metadata']);
        if ($columns != $expected || $primary !== [['id']] || $unique !== [['kind', 'record_id']]
            || $schema->getForeignKeys('catalog_metadata') !== [] || strtolower($table->engine ?? '') !== 'innodb') {
            throw new LogicException('The legacy Catalog metadata table does not match its historical columns and keys. Inspect before removal.');
        }
    }

    public static function verifyCopy(Connection $db): void
    {
        self::assertLegacy($db);
        foreach (self::FIELDS as $table => $fields) {
            self::rows($db, $table, $fields)->chunkById(500, function ($rows) use ($table, $fields) {
                foreach ($rows as $row) {
                    if (self::differs($row, self::expected($row, $fields))) {
                        throw new LogicException("Catalog owner values differ from the legacy source in {$table}, record {$row->id}.");
                    }
                }
            }, 'owner.id', 'id');
        }
    }

    private static function assertLegacy(Connection $db): array
    {
        $counts = self::legacyAssessment($db);
        $blocking = array_filter(array_intersect_key($counts, array_flip([
            'unknown_kind', 'orphan', 'invalid_state', 'inactive_central',
        ])));
        if ($blocking !== []) {
            throw new LogicException('Catalog metadata needs inspection: '.json_encode($blocking, JSON_THROW_ON_ERROR));
        }

        return $counts;
    }

    private static function rows(Connection $db, string $table, array $fields)
    {
        return $db->table($table.' as owner')
            ->leftJoin('catalog_metadata as metadata', function ($join) use ($table) {
                $join->on('metadata.record_id', '=', 'owner.id')->where('metadata.kind', $table);
            })
            ->select('owner.id', ...array_map(fn ($field) => 'owner.'.$field, $fields))
            ->addSelect(array_map(fn ($field) => 'metadata.'.$field.' as legacy_'.$field, $fields));
    }

    private static function expected(stdClass $row, array $fields): array
    {
        $values = ['state' => $row->legacy_state ?? 'active'];
        if (in_array('description', $fields, true)) {
            $values['description'] = $row->legacy_description;
        }
        if (in_array('mailing_address', $fields, true)) {
            $values['mailing_address'] = self::filled($row->legacy_mailing_address)
                ? $row->legacy_mailing_address : self::MISSING_ADDRESS;
        }

        return $values;
    }

    private static function differs(stdClass $row, array $values): bool
    {
        foreach ($values as $field => $value) {
            // PHP strict comparison preserves case, trailing whitespace and NULL vs empty text.
            if ($row->$field !== $value) {
                return true;
            }
        }

        return false;
    }

    private static function filled(?string $value): bool
    {
        return $value !== null && trim($value) !== '';
    }

    private static function itemNotesFingerprint(Connection $db): string
    {
        $hash = hash_init('sha256');
        $db->table('item_metadata')->select('item_id', 'notes')->chunkById(500, function ($rows) use ($hash) {
            foreach ($rows as $row) {
                hash_update($hash, serialize([$row->item_id, $row->notes]));
            }
        }, 'item_id');

        return hash_final($hash);
    }
}
