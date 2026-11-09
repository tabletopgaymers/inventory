<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\AccessManagement;
use App\Support\DisplayDates;
use App\Support\MicrosoftConfiguration;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use LogicException;

class OfficerSeeder extends Seeder
{
    private const PROFILE_HEADERS = [
        'displayName', 'userPrincipalName', 'givenName', 'surname', 'email', 'personalEmail',
        'mobileNumber', 'address', 'city', 'state', 'zipCode', 'timeZone',
    ];

    private const REFERENCE_HEADERS = ['username', 'basic', 'manager', 'procurement', 'admin'];

    public function run(?string $file = null, ?int $actorId = null): array
    {
        if (! in_array(DB::connection()->getConfig('driver'), ['mysql', 'mariadb'], true)) {
            throw new LogicException('Officer seeding requires the configured MySQL/MariaDB connection.');
        }
        $tenant = config('services.microsoft.tenant');
        if (! MicrosoftConfiguration::guid($tenant)) {
            throw new LogicException('Configure MICROSOFT_TENANT_ID before importing officers.');
        }
        $tenant = strtolower($tenant);
        $rows = $this->readRows($file ?? database_path('fixtures/users.csv'));

        $summary = DB::transaction(function () use ($rows, $tenant, $actorId) {
            $bootstrap = DB::table('authentication_bootstraps')->where('key', 'initial-admin')->lockForUpdate()->firstOrFail();
            $actor = User::find($actorId ?? $bootstrap->user_id);
            if (! $actor || ! $actor->enabled || ! $actor->hasRole('admin')) {
                throw new LogicException('Supply an enabled Admin with --actor, or complete initial administrator setup first.');
            }

            $access = app(AccessManagement::class);
            $summary = ['rows' => count($rows), 'created' => 0, 'updated' => 0, 'unchanged' => 0, 'roles_granted' => 0];
            $seen = [];
            foreach ($rows as $row) {
                $key = ['provider' => 'microsoft', 'tenant_id' => $tenant, 'object_id' => $row['object']];
                $identity = DB::table('external_identities')->where($key)->first();
                $user = $identity ? User::findOrFail($identity->user_id) : new User;
                if (! $user->enabled) {
                    throw new LogicException("Officer CSV row {$row['line']} matches a disabled account. Enable it separately before importing.");
                }
                if ($identity && isset($seen[$user->id])) {
                    throw new LogicException("Officer CSV row {$row['line']} matches an account already included in the file.");
                }

                foreach ($row['profile'] as $field => $value) {
                    if ($value !== '' && blank($user->{$field})) {
                        $user->{$field} = $value;
                    }
                }
                $profileChanged = $user->isDirty();
                if (! $identity || $profileChanged) {
                    $user->save();
                }
                if (! $identity) {
                    $now = now('UTC');
                    DB::table('external_identities')->insert($key + [
                        'user_id' => $user->id, 'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
                $seen[$user->id] = true;

                $newRoles = array_diff($row['roles'], $user->roles());
                foreach ($newRoles as $role) {
                    $access->changeRole($actor->id, $user->id, $role, true);
                    $summary['roles_granted']++;
                }

                if (! $identity) {
                    $summary['created']++;
                } elseif ($profileChanged || $newRoles !== []) {
                    $summary['updated']++;
                } else {
                    $summary['unchanged']++;
                }
            }

            return $summary;
        }, 3);

        $this->command?->info('SUCCESS: Officer seed completed. '.json_encode($summary, JSON_THROW_ON_ERROR));

        return $summary;
    }

    private function readRows(string $file): array
    {
        $contents = is_file($file) ? file_get_contents($file) : false;
        if ($contents === false || ! mb_check_encoding($contents, 'UTF-8')) {
            throw new LogicException('Supply a readable UTF-8 CSV in database/fixtures/users.csv, or select one with --file.');
        }
        $handle = fopen('php://temp', 'w+');
        fwrite($handle, preg_replace('/\A\xEF\xBB\xBF/', '', $contents));
        rewind($handle);

        try {
            $header = fgetcsv($handle, 0, ',', '"', '');
            $required = ['id', 'roles'];
            $allowed = array_merge($required, self::PROFILE_HEADERS, self::REFERENCE_HEADERS);
            if (! is_array($header) || count($header) !== count(array_unique($header))
                || array_diff($required, $header) || array_diff($header, $allowed)) {
                throw new LogicException('Officer CSV requires unique id and roles headers and accepts the documented optional columns.');
            }

            $rows = $objects = [];
            $line = 1;
            $email = ['nullable', 'string', 'max:255', 'email:rfc'];
            $defaults = array_fill_keys(self::PROFILE_HEADERS, '');
            while (($values = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                $line++;
                if ($values === [null]) {
                    continue;
                }
                if (count($values) !== count($header)) {
                    throw new LogicException("Officer CSV row {$line} has the wrong number of columns.");
                }

                $row = array_map('trim', array_combine($header, $values)) + $defaults;
                $object = strtolower($row['id']);
                if (! MicrosoftConfiguration::guid($object) || isset($objects[$object])) {
                    throw new LogicException("Officer CSV row {$line} has an invalid or repeated Microsoft Object ID.");
                }
                $objects[$object] = true;
                $roles = array_values(array_unique(array_map('trim', explode(',', $row['roles']))));
                if (array_diff($roles, AccessManagement::ROLES)) {
                    throw new LogicException("Officer CSV row {$line} needs one or more valid roles.");
                }

                $place = implode(' ', array_filter([$row['city'], $row['state'], $row['zipCode']], fn ($part) => $part !== ''));
                $address = $row['address'] === '' && $place === '' ? '' : implode("\n", array_filter(
                    [$row['displayName'], $row['address'], $place], fn ($part) => $part !== ''
                ));
                $profile = [
                    'first_name' => $row['givenName'], 'last_name' => $row['surname'],
                    'provider_email' => $row['email'] !== '' ? $row['email'] : $row['userPrincipalName'],
                    'personal_email' => $row['personalEmail'], 'mobile_number' => $row['mobileNumber'],
                    'mailing_address' => $address, 'time_zone' => $row['timeZone'],
                ];
                $validation = Validator::make($profile, [
                    'first_name' => ['nullable', 'string', 'max:100', 'not_regex:/\p{Cc}/u'],
                    'last_name' => ['nullable', 'string', 'max:100', 'not_regex:/\p{Cc}/u'],
                    'provider_email' => $email,
                    'personal_email' => $email,
                    'mobile_number' => ['nullable', 'string', 'max:100', 'not_regex:/\p{Cc}/u'],
                    'mailing_address' => ['nullable', 'string', 'max:4000', 'not_regex:/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u'],
                    'time_zone' => ['nullable', Rule::in(DisplayDates::zones())],
                ]);
                if ($validation->fails()) {
                    throw new LogicException("Officer CSV row {$line} has invalid profile fields: ".implode(', ', $validation->errors()->keys()).'.');
                }

                $rows[] = ['line' => $line, 'object' => $object, 'roles' => $roles, 'profile' => $profile];
            }
            if ($rows === []) {
                throw new LogicException('The officer CSV contains no officer rows.');
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }
}
