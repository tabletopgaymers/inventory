<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class MicrosoftIdentity
{
    public function resolve(object $external, object $claims): User
    {
        if (! is_string($claims->tid ?? null) || ! is_string($claims->oid ?? null)
            || ! is_string($external->getId())) {
            throw new \RuntimeException('Microsoft identity rejected.');
        }
        $tenant = strtolower($claims->tid ?? '');
        $object = strtolower($claims->oid ?? '');
        if (! MicrosoftConfiguration::guid($tenant) || ! MicrosoftConfiguration::guid($object)
            || $tenant !== strtolower(config('services.microsoft.tenant') ?? '')
            || $object !== strtolower((string) $external->getId())) {
            throw new \RuntimeException('Microsoft identity rejected.');
        }

        return DB::transaction(function () use ($tenant, $object, $external) {
            // Serialize provisioning, avoiding orphan users on concurrent first login.
            DB::table('authentication_bootstraps')->where('key', 'initial-admin')->lockForUpdate()->firstOrFail();
            $identity = DB::table('external_identities')->where('provider', 'microsoft')
                ->where('tenant_id', $tenant)->where('object_id', $object)->first();
            if ($identity) {
                $user = User::findOrFail($identity->user_id);
            } else {
                $user = new User;
                $user->first_name = $this->seedName($external->user['givenName'] ?? '');
                $user->last_name = $this->seedName($external->user['surname'] ?? '');
                $user->save();
                DB::table('external_identities')->insert([
                    'user_id' => $user->id, 'provider' => 'microsoft', 'tenant_id' => $tenant,
                    'object_id' => $object, 'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('user_roles')->insert(['user_id' => $user->id, 'role' => 'basic']);
            }
            if (! $user->enabled) {
                throw new \RuntimeException('Application access denied.');
            }
            $email = $external->getEmail();
            $user->provider_email = is_string($email) && mb_strlen($email) <= 255
                && ! preg_match('/\p{Cc}/u', $email) ? $email : null;
            $user->save();

            return $user;
        }, 3);
    }

    private function seedName(mixed $name): string
    {
        $name = is_string($name) ? preg_replace('/^\s+|\s+$/u', '', $name) : '';

        return $name !== null && mb_strlen($name) <= 100 && ! preg_match('/\p{Cc}/u', $name) ? $name : '';
    }
}
