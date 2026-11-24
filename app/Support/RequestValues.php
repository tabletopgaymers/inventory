<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RequestValues
{
    /**
     * Validates UTF-8, length and allowed control characters, then trims whitespace. Optional empty text normalizes to null. The 255-character case rejects line breaks and tabs.
     *
     * Scope: Domain-specific (Request and Inventory workflows)
     * Task: Validate and normalize plain text
     * @example app/Http/Controllers/PurchaseController.php
     */
    public static function text(mixed $value, string $field, int $max = 5000, bool $required = false): ?string
    {
        if ($value === null && ! $required) {
            return null;
        }
        if (! is_string($value) || ! mb_check_encoding($value, 'UTF-8') || mb_strlen($value) > $max
            || preg_match($max === 255 ? '/[\x00-\x1F\x7F]/u' : '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $value)) {
            throw ValidationException::withMessages([$field => 'Use ordinary plain text up to '.$max.' characters.']);
        }
        $value = trim($value);
        if ($required && $value === '') {
            throw ValidationException::withMessages([$field => 'Enter a nonblank '.$field.'.']);
        }

        return $value === '' ? null : $value;
    }

    /**
     * Accepts grouped thousands up to one billion. Blank mode preserves absent values as null, while bulk mode treats nonempty values with no digits as zero.
     *
     * Scope: Domain-specific (Request and Inventory workflows)
     * Task: Validate whole-unit quantities
     * @example app/Support/EventWorkflow.php
     */
    public static function quantity(mixed $value, string $field, bool $blank = false, bool $bulk = false): ?int
    {
        if ($blank && ($value === null || $value === '')) {
            return null;
        }
        if (! is_string($value) && ! is_int($value)) {
            throw ValidationException::withMessages([$field => 'Enter individual whole units from 0 to 1,000,000,000.']);
        }
        $value = trim((string) $value);
        if ($bulk && $value !== '' && ! preg_match('/[0-9]/', $value)) {
            return 0;
        }
        if (! preg_match('/\A(?:\d{1,10}|\d{1,3}(?:,\d{3}){1,3})\z/D', $value) || (int) str_replace(',', '', $value) > 1000000000) {
            throw ValidationException::withMessages([$field => 'Enter individual whole units from 0 to 1,000,000,000; commas may group thousands.']);
        }

        return (int) str_replace(',', '', $value);
    }

    /**
     * Accepts a positive decimal integer of up to 18 digits. Empty input returns null. Callers separately check existence and eligibility.
     *
     * Scope: Domain-specific (Request and Inventory workflows)
     * Task: Parse an optional record identifier
     * @example app/Http/Controllers/RelocationController.php
     */
    public static function id(mixed $value, string $field): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ((! is_int($value) && ! is_string($value)) || ! preg_match('/\A[1-9]\d{0,17}\z/D', (string) $value)) {
            throw ValidationException::withMessages([$field => 'Choose an existing record.']);
        }

        return (int) $value;
    }

    /**
     * Compares a decimal revision with the current record and rejects the upper revision limit. Run against the currently locked row when protecting concurrent writes.
     *
     * Scope: Domain-specific (Request and Inventory workflows)
     * Task: Check the revision before a write
     * @example app/Support/EventWorkflow.php
     */
    public static function revision(mixed $value, object $record): void
    {
        if ((! is_int($value) && ! is_string($value)) || ! ctype_digit((string) $value) || (int) $value !== (int) $record->revision) {
            throw ValidationException::withMessages(['revision' => 'Another worker saved this request. Your entered values are retained. Open the latest saved request and reload before applying your changes.']);
        }
        abort_if((int) $record->revision >= 4294967295, 409, 'This request needs revision inspection.');
    }

    /**
     * Locks the same bootstrap row used by access management before locking the user and rechecking enabled state. Use inside a transaction and check the action-specific role separately.
     *
     * Scope: Shared
     * Task: Resolve and lock the acting user before a write
     * @example app/Support/DailyInventoryPosting.php
     */
    public static function actor(int $id): User
    {
        // Same global lock as role/profile changes: recheck current access before writes.
        DB::table('authentication_bootstraps')->where('key', 'initial-admin')->lockForUpdate()->firstOrFail();
        $user = User::whereKey($id)->lockForUpdate()->firstOrFail();
        abort_unless($user->enabled, 403);

        return $user;
    }

    /**
     * The predicate checks the supplied user. For writes that require a current role, resolve the actor under the shared access lock in the surrounding transaction.
     *
     * Scope: Shared
     * Task: Check enabled Admin or specified role access
     * @example app/Http/Controllers/RelocationController.php
     */
    public static function manages(User $user, string $role): bool
    {
        return $user->enabled && ($user->hasRole('admin') || $user->hasRole($role));
    }

    /**
     * Requires an enabled user with a Microsoft identity in the configured tenant. Ownership and request status are separate action checks.
     *
     * Scope: Domain-specific (Relocation)
     * Task: Check eligibility to submit one's own request
     * @example app/Http/Controllers/RelocationController.php
     */
    public static function submitsOwn(User $user): bool
    {
        $tenant = config('services.microsoft.tenant');

        return $user->enabled && is_string($tenant) && MicrosoftConfiguration::guid($tenant)
            && DB::table('external_identities')->where('user_id', $user->id)->where('provider', 'microsoft')->where('tenant_id', strtolower($tenant))->exists();
    }

    /**
     * Appends an actor and action snapshot with optional before/after JSON for the specified request kind. Call inside the transaction governing the associated mutation.
     *
     * Scope: Domain-specific (Purchase and Relocation)
     * Task: Record request activity
     * @example app/Http/Controllers/PurchaseController.php
     */
    public static function activity(string $kind, int $id, User $actor, string $action, mixed $before, mixed $after): void
    {
        abort_unless(in_array($kind, ['purchase', 'relocation'], true), 500);
        DB::table($kind.'_request_activity')->insert([
            $kind.'_request_id' => $id, 'actor_id' => $actor->id, 'actor_name' => $actor->displayName(), 'action' => $action,
            'previous' => $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR),
            'current' => $after === null ? null : json_encode($after, JSON_THROW_ON_ERROR), 'occurred_at' => now('UTC'),
        ]);
    }

    /**
     * Allows null and can retain the exact existing inactive record unless active-only is required. Rechecks the record's state after locking the catalog row and any applicable metadata. Run inside the caller's transaction.
     *
     * Scope: Domain-specific (Purchase and Relocation)
     * Task: Validate and lock a catalog association
     * @example app/Http/Controllers/RelocationController.php
     */
    public static function association(string $kind, ?int $id, ?int $existing, string $field, bool $requireActive = false): void
    {
        if ($id === null) {
            return;
        }
        $records = app(CatalogRecords::class);
        $record = $records->records($kind)->firstWhere('id', $id);
        if (! $record || ($record->state !== 'active' && ($requireActive || $id !== $existing))) {
            throw ValidationException::withMessages([$field => 'Choose an active '.$kind.' record. Saved retired associations can be retained in a draft.']);
        }
        $table = $kind === 'suppliers' ? 'catalog_references' : $kind;
        DB::table($table)->where('id', $id)->lockForUpdate()->firstOrFail();
        // Re-read metadata under its row lock after locking the catalog record.
        if ($kind !== 'suppliers') {
            DB::table('catalog_metadata')->where('kind', $kind)->where('record_id', $id)->lockForUpdate()->first();
        }
        $record = $records->records($kind)->firstWhere('id', $id);
        if (! $record || ($record->state !== 'active' && ($requireActive || $id !== $existing))) {
            throw ValidationException::withMessages([$field => 'That selection is no longer active. Reload the catalog choices.']);
        }
    }
}
