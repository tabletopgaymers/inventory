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
            foreach (array_intersect($fields, $schema->getColumnListing($table)) as $field) {
                $existing[] = "{$table}.{$field}";
            }
        }
        if ($existing !== []) {
            throw new LogicException('Catalog owner columns already exist. Inspect migration progress before repair: '.implode(', ', $existing));
        }

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

        // MariaDB commits each ALTER independently. Only the value copy and its checks are transactional.
        $db->transaction(function () use ($db) {
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

        return $counts;
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

    private static function assertLegacy(Connection $db): void
    {
        $blocking = array_filter(array_intersect_key(self::legacyAssessment($db), array_flip([
            'unknown_kind', 'orphan', 'invalid_state', 'inactive_central',
        ])));
        if ($blocking !== []) {
            throw new LogicException('Catalog metadata needs inspection: '.json_encode($blocking, JSON_THROW_ON_ERROR));
        }
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
