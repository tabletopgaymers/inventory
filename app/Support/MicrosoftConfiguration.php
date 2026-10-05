<?php

namespace App\Support;

class MicrosoftConfiguration
{
    public static function guid(mixed $value): bool
    {
        return is_string($value) && preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/iD', $value) === 1;
    }

    public function ready(): bool
    {
        $origin = match (app()->environment()) {
            'local', 'testing' => 'https://tg-inventory-app.test',
            'development' => 'https://dev-inventory.tabletopgaymers.org',
            default => null,
        };

        return $origin !== null && config('app.url') === $origin
            && self::guid(config('services.microsoft.tenant'))
            && self::guid(config('services.microsoft.client_id'))
            && is_string(config('services.microsoft.client_secret')) && config('services.microsoft.client_secret') !== ''
            && config('services.microsoft.redirect') === $origin.'/auth/microsoft/callback'
            && config('microsoft_auth.post_logout_redirect_uri') === $origin.'/signed-out'
            && self::guid(config('microsoft_auth.bootstrap_object'))
            && config('microsoft_auth.bootstrap_tenant') === config('services.microsoft.tenant')
            && config('microsoft_auth.scopes') === ['openid', 'profile', 'email', 'User.Read']
            && config('services.microsoft.include_avatar') === false
            && config('services.microsoft.include_tenant_info') === false;
    }
}
