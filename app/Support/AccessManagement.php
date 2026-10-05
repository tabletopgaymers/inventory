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
            $previous = $target->roles();
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
        DB::transaction(function () use ($actorId, $targetId) {
            DB::table('authentication_bootstraps')->where('key', 'initial-admin')->lockForUpdate()->firstOrFail();
            $actor = User::findOrFail($actorId);
            abort_unless($actor->enabled && $actor->hasRole('admin'), 403);
            $target = User::findOrFail($targetId);
            if ($target->enabled) {
                $target->enabled = false;
                $target->save();
                $this->audit($actorId, $targetId, 'disablement', ['enabled' => true], ['enabled' => false]);
            }
        }, 3);
    }

    public function saveProfile(int $userId, array $data): void
    {
        DB::transaction(function () use ($userId, $data) {
            $bootstrap = DB::table('authentication_bootstraps')->where('key', 'initial-admin')->lockForUpdate()->firstOrFail();
            $user = User::findOrFail($userId);
            abort_unless($user->enabled, 403);
            $address = $data['contact_email'] ?: null;
            $changed = $address !== $user->contact_email;
            $user->first_name = $data['first_name'];
            $user->last_name = $data['last_name'];
            $user->contact_email = $address;
            $user->contact_attested = ($changed ? false : $user->contact_attested) || $data['contact_attested'];
            if (! $address) {
                $user->contact_attested = false;
            }
            $user->save();
            $tenant = config('microsoft_auth.bootstrap_tenant');
            $object = config('microsoft_auth.bootstrap_object');
            if ($bootstrap->consumed_at === null && $user->validContact()
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
