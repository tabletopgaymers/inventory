<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Submitter preferences, separate from Procurement's actual order associations. */
class PurchaseRequestBasics
{
    public const FIELDS = [
        'vendor_choice', 'vendor_name', 'vendor_website', 'vendor_contact', 'vendor_email', 'vendor_phone',
        'delivery_choice', 'delivery_name', 'delivery_address', 'delivery_email', 'delivery_phone',
    ];

    public static function defaults(): array
    {
        return array_fill_keys(self::FIELDS, '') + ['vendor_other_id' => '', 'vendor_other_baseline' => []];
    }

    public static function load(?object $row): array
    {
        $saved = $row ? RequestStock::header('purchase', $row->id)['request_basics'] ?? null : null;
        if (is_array($saved)) return self::restoreOtherVendor(array_replace(self::defaults(), array_intersect_key($saved, self::defaults())), true);

        return array_replace(self::defaults(), filled($row?->suggested_merchant) ? [
            'vendor_choice' => 'other', 'vendor_name' => $row->suggested_merchant,
        ] : []);
    }

    public static function isOtherVendor(array $values): bool
    {
        return ($values['vendor_choice'] ?? '') === 'other'
            || (! empty($values['vendor_other_id']) && ($values['vendor_choice'] ?? '') === 'supplier:'.$values['vendor_other_id']);
    }

    /** Recover the request's linked Other supplier; IDs come from saved server state, never form input. */
    public static function restoreOtherVendor(array $values, bool $refresh = false): array
    {
        $id = (int) ($values['vendor_other_id'] ?? 0);
        if (! $id && preg_match('/\Asupplier:([1-9][0-9]*)\z/', $values['vendor_choice'] ?? '', $match)
            && filled($values['vendor_name'] ?? '')) {
            // Compatibility with requests saved before the explicit Other ID was recorded.
            $candidate = DB::table('catalog_references')->where('id', $match[1])->where('kind', 'suppliers')->first();
            if ($candidate?->state === 'unverified' && $candidate->name === $values['vendor_name']) $id = (int) $candidate->id;
        }
        if (! $id) return $values;
        $record = DB::table('catalog_references')->where('id', $id)->where('kind', 'suppliers')->first();
        if (! $record || $record->state !== 'unverified') {
            if ($record && self::isOtherVendor($values)) $values['vendor_choice'] = 'supplier:'.$id;
            $values['vendor_other_id'] = '';
            $values['vendor_other_baseline'] = [];

            return $values;
        }
        $newLink = empty($values['vendor_other_id']);
        $values['vendor_other_id'] = (string) $id;
        if ($refresh || $newLink) {
            foreach (['name'=>'name','website'=>'website','contact'=>'contact_name','email'=>'email','phone'=>'phone'] as $field=>$column) {
                $values['vendor_'.$field] = $record->$column ?? '';
            }
            $values['vendor_other_baseline'] = self::vendorFields($record);
        }

        return $values;
    }

    public static function rules(array $input): array
    {
        $rules = [];
        foreach (self::FIELDS as $field) $rules[$field] = ['nullable', 'string', 'max:'.($field === 'delivery_address' ? 5000 : 255)];
        foreach (['vendor_choice', 'delivery_choice'] as $field) $rules[$field][0] = 'required';
        if (self::isOtherVendor($input)) $rules['vendor_name'][0] = 'required';
        if (($input['delivery_choice'] ?? '') === 'other') {
            $rules['delivery_name'][0] = 'required';
            $rules['delivery_address'][0] = 'required';
        }
        foreach (['vendor', 'delivery'] as $kind) {
            if (($kind === 'vendor' && self::isOtherVendor($input)) || ($input[$kind.'_choice'] ?? '') === 'other') $rules[$kind.'_email'][] = 'email';
        }

        return $rules;
    }

    public static function validateChoices(array $values, array $previous): void
    {
        foreach (['vendor_choice', 'delivery_choice'] as $field) {
            $choice = $values[$field] ?? '';
            if ($choice === '' || $choice === 'other') continue;
            if ($field === 'vendor_choice' && preg_match('/\Asupplier:([1-9][0-9]*)\z/', $choice, $match)) {
                $record = app(CatalogRecords::class)->records('suppliers')->firstWhere('id', (int) $match[1]);
                if ($record && (in_array($record->state, ['active', 'unverified'], true) || $choice === ($previous[$field] ?? ''))) continue;
            }
            if ($field === 'delivery_choice' && preg_match('/\Alocation:([1-9][0-9]*)\z/', $choice, $match)) {
                $record = app(CatalogRecords::class)->records('storage_locations')->firstWhere('id', (int) $match[1]);
                if ($record && ($record->state === 'active' || $choice === ($previous[$field] ?? ''))) continue;
            }
            if ($field === 'delivery_choice' && preg_match('/\Auser:([1-9][0-9]*)\z/', $choice, $match)) {
                $user = User::find((int) $match[1]);
                if ($user && ($user->enabled || $choice === ($previous[$field] ?? ''))) continue;
            }
            throw ValidationException::withMessages([$field => 'Choose a current entry or Other.']);
        }
    }

    public static function vendorName(array $values): string
    {
        if (self::isOtherVendor($values)) return $values['vendor_name'] ?? '';
        if (preg_match('/\Asupplier:([1-9][0-9]*)\z/', $values['vendor_choice'] ?? '', $match)) {
            return app(CatalogRecords::class)->records('suppliers')->firstWhere('id', (int) $match[1])?->name ?? '';
        }

        return '';
    }

    public static function labels(array $values): array
    {
        $vendor = self::vendorName($values);
        if (self::isOtherVendor($values)) $vendor = implode("\n", array_filter(array_map(fn ($key) => $values['vendor_'.$key] ?? '', ['name','website','contact','email','phone'])));
        $choice = $values['delivery_choice'] ?? '';
        $delivery = match (true) {
            str_starts_with($choice, 'location:') => app(CatalogRecords::class)->records('storage_locations')->firstWhere('id', (int) substr($choice,9))?->name ?? '',
            str_starts_with($choice, 'user:') => User::find((int) substr($choice,5))?->displayName() ?? '',
            default => implode("\n", array_filter(array_map(fn ($key) => $values['delivery_'.$key] ?? '', ['name','address','email','phone']))),
        };
        return compact('vendor','delivery');
    }

    private static function vendorFields(object $record): array
    {
        $fields = [];
        foreach (['name', 'website', 'contact_name', 'email', 'phone'] as $field) $fields[$field] = $record->$field ?? null;
        return $fields;
    }

    /** Update the linked Unverified Other supplier, or materialize it once, inside the request transaction. */
    public static function saveVendor(array $values): array
    {
        if (! self::isOtherVendor($values)) return $values;
        $name = trim($values['vendor_name']);
        if ($name === '') throw ValidationException::withMessages(['vendor_name' => 'Enter the supplier name.']);
        if ($id = (int) ($values['vendor_other_id'] ?? 0)) {
            $record = DB::table('catalog_references')->where('id', $id)->where('kind', 'suppliers')->lockForUpdate()->first();
            if (! $record || $record->state !== 'unverified') throw ValidationException::withMessages(['vendor_choice' => 'This supplier is no longer unverified. Reopen the request to load its current details.']);
            if (DB::table('catalog_references')->where('kind', 'suppliers')->where('name', $name)->where('id', '!=', $id)->exists()) {
                throw ValidationException::withMessages(['vendor_name' => 'Another supplier already has this name. Select that supplier instead.']);
            }
            $fields = [
                'name' => $name, 'website' => $values['vendor_website'] ?: null,
                'contact_name' => $values['vendor_contact'] ?: null, 'email' => $values['vendor_email'] ?: null,
                'phone' => $values['vendor_phone'] ?: null,
            ];
            $current = self::vendorFields($record);
            $baseline = $values['vendor_other_baseline'] ?? [];
            if ($fields === $baseline) {
                // A material-only save must not undo a canonical supplier correction.
                return self::restoreOtherVendor($values, true);
            }
            if ($baseline === [] || $current !== $baseline) {
                throw ValidationException::withMessages(['vendor_name' => 'This supplier changed since you opened it. Your edits are retained; reopen the request to compare the latest supplier details before saving.']);
            }
            if (array_diff_assoc($fields, (array) $record) !== []) {
                DB::table('catalog_references')->where('id', $id)->update($fields + ['updated_at' => now('UTC')]);
            }
            $values['vendor_choice'] = 'supplier:'.$id;
            $values['vendor_other_baseline'] = $fields;

            return $values;
        }
        $query = DB::table('catalog_references')->where('kind', 'suppliers')->where('name', $name);
        $record = (clone $query)->lockForUpdate()->first();
        if (! $record) {
            DB::table('catalog_references')->insertOrIgnore([
                'kind' => 'suppliers', 'name' => $name, 'state' => 'unverified',
                'website' => $values['vendor_website'] ?: null, 'contact_name' => $values['vendor_contact'] ?: null,
                'email' => $values['vendor_email'] ?: null, 'phone' => $values['vendor_phone'] ?: null,
                'created_at' => now('UTC'), 'updated_at' => now('UTC'),
            ]);
            $record = $query->lockForUpdate()->firstOrFail();
        }
        if (! in_array($record->state, ['active', 'unverified'], true)) {
            throw ValidationException::withMessages(['vendor_name' => 'A supplier with this name is inactive or archived. Choose another supplier or ask Procurement to review it.']);
        }
        $values['vendor_choice'] = 'supplier:'.$record->id;
        $values['vendor_other_id'] = $record->state === 'unverified' ? (string) $record->id : '';
        $values['vendor_other_baseline'] = $record->state === 'unverified' ? self::vendorFields($record) : [];
        // Reusing a same-name record loads its canonical details without overwriting it.
        foreach (['name'=>'name','website'=>'website','contact'=>'contact_name','email'=>'email','phone'=>'phone'] as $field=>$column) $values['vendor_'.$field] = $record->$column ?? '';

        return $values;
    }
}
