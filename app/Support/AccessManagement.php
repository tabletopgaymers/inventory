<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccessManagement
{
    public const ROLES = ['basic', 'admin', 'manager', 'procurement'];

    public function changeRole(int $actorId, int $targetId, string $role, bool $grant): void
    {
        abort_unless(in_array($role, self::ROLES, true), 422);
        DB::transaction(function () use ($actorId, $targetId, $role, $grant) {
            // Global lock orders all access changes, including self edits and bootstrap.
            DB::table('authentication_bootstraps')->where('key', 'initial-admin')->lockForUpdate()->firstOrFail();
            $actor = User::findOrFail($actorId);
            $target = User::findOrFail($targetId);
            abort_unless($actor->enabled && ($actor->hasRole('admin')
                || (in_array($role, ['manager', 'procurement'], true) && $actor->hasRole($role))), 403);
            abort_if($actorId === $targetId && $role === 'admin' && ! $grant, 403, 'Another admin must remove your admin role.');
            $previous = $target->roles();
            if ($grant && ! $target->enabled) {
                throw ValidationException::withMessages(['role' => 'Enable this account before granting roles.']);
            }
            if (in_array($role, $previous, true) === $grant) {
                return;
            }
            if ($grant && $role !== 'basic' && ! $target->validContact()) {
                throw ValidationException::withMessages(['role' => 'this user has not verified their email address']);
            }
            if ($grant) {
                DB::table('user_roles')->insert(['user_id' => $targetId, 'role' => $role]);
            } else {
                DB::table('user_roles')->where('user_id', $targetId)->where('role', $role)->delete();
            }
            $this->audit($actorId, $targetId, 'roles', $previous, $target->roles());
        }, 3);
    }

    public function disable(int $actorId, int $targetId): void
    {
        $this->setEnabled($actorId, $targetId, false);
    }

    public function enable(int $actorId, int $targetId): void
    {
        $this->setEnabled($actorId, $targetId, true);
    }

    private function setEnabled(int $actorId, int $targetId, bool $enabled): void
    {
        DB::transaction(function () use ($actorId, $targetId, $enabled) {
            DB::table('authentication_bootstraps')->where('key', 'initial-admin')->lockForUpdate()->firstOrFail();
            $actor = User::findOrFail($actorId);
            abort_unless($actor->enabled && $actor->hasRole('admin'), 403);
            abort_if(! $enabled && $actorId === $targetId, 403, 'Another admin must disable your account.');
            $target = User::findOrFail($targetId);
            if ($enabled && $target->enabled) {
                return;
            }
            // Re-enabling legacy disabled accounts must not reactivate retained roles.
            $previousRoles = $target->roles();
            if ($previousRoles !== []) {
                DB::table('user_roles')->where('user_id', $targetId)->delete();
                $this->audit($actorId, $targetId, 'roles', $previousRoles, []);
            }
            if ($target->enabled !== $enabled) {
                $previousEnabled = $target->enabled;
                $target->enabled = $enabled;
                $target->save();
                $this->audit($actorId, $targetId, $enabled ? 'enablement' : 'disablement', ['enabled' => $previousEnabled], ['enabled' => $enabled]);
            }
        }, 3);
    }

    public function saveProfile(int $userId, array $data, ?int $actorId = null): void
    {
        $actorId ??= $userId;
        DB::transaction(function () use ($userId, $data, $actorId) {
            $bootstrap = DB::table('authentication_bootstraps')->where('key', 'initial-admin')->lockForUpdate()->firstOrFail();
            $actor = User::findOrFail($actorId);
            $user = User::findOrFail($userId);
            $ownProfile = (int) $actor->id === (int) $user->id;
            abort_unless($actor->enabled && ($ownProfile || $actor->hasRole('admin')), 403);
            abort_unless(app(ProfilePreferences::class)->state(DB::connection())['ready'], 503, 'Profile preferences need guarded preparation. Your profile was not changed.');
            $address = $data['contact_email'] ?: null;
            $changed = $address !== $user->contact_email;
            $user->first_name = $data['first_name'];
            $user->last_name = $data['last_name'];
            if (array_key_exists('time_zone', $data)) {
                if (! is_string($data['time_zone']) || ! in_array($data['time_zone'], DisplayDates::zones(), true)) {
                    throw ValidationException::withMessages(['time_zone' => 'Choose a named time zone.']);
                }
                $user->time_zone = $data['time_zone'];
            }
            $user->contact_email = $address;
            foreach (['personal_email', 'mobile_number', 'mailing_address', 'display_density'] as $field) {
                if (array_key_exists($field, $data)) {
                    $user->{$field} = $data[$field];
                }
            }
            $user->contact_attested = ($changed ? false : $user->contact_attested) || ($ownProfile && $data['contact_attested']);
            if (! $address) {
                $user->contact_attested = false;
            }
            $user->save();
            $tenant = config('microsoft_auth.bootstrap_tenant');
            $object = config('microsoft_auth.bootstrap_object');
            if ($ownProfile && $bootstrap->consumed_at === null && $user->validContact()
                && is_string($tenant) && is_string($object) && $tenant !== '' && $object !== ''
                && DB::table('external_identities')->where('user_id', $userId)->where('provider', 'microsoft')
                    ->where('tenant_id', $tenant)->where('object_id', $object)->exists()) {
                $previous = $user->roles();
                DB::table('user_roles')->insertOrIgnore(['user_id' => $userId, 'role' => 'admin']);
                DB::table('authentication_bootstraps')->where('key', 'initial-admin')->update(['user_id' => $userId, 'consumed_at' => now()]);
                $this->audit($userId, $userId, 'initial-admin', $previous, $user->roles());
            }
        }, 3);
    }

    private function audit(int $actor, int $target, string $action, array $previous, array $current): void
    {
        DB::table('access_audits')->insert([
            'actor_id' => $actor, 'target_id' => $target, 'action' => $action,
            'previous' => json_encode($previous, JSON_THROW_ON_ERROR),
            'current' => json_encode($current, JSON_THROW_ON_ERROR), 'occurred_at' => now('UTC'),
        ]);
    }
}
