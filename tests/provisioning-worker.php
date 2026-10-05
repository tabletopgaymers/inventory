<?php

use App\Support\BaselineProbe;
use App\Support\MicrosoftConfiguration;
use App\Support\MicrosoftIdentity;
use Illuminate\Contracts\Console\Kernel;
use SocialiteProviders\Manager\OAuth2\User;

// Disposable concurrency fixture: never accepts environment or database targets.
ini_set('display_errors', '0');
ini_set('log_errors', '0');
try {
    require dirname(__DIR__).'/vendor/autoload.php';
    $app = require dirname(__DIR__).'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    if (! $app->environment('testing') || ! app(BaselineProbe::class)->inspect()['ready']) {
        throw new RuntimeException;
    }
    $object = $argv[1] ?? '';
    if (! MicrosoftConfiguration::guid($object)) {
        throw new RuntimeException;
    }
    $tenant = '11111111-1111-1111-1111-111111111111';
    config(['services.microsoft.tenant' => $tenant]);
    $external = (new User)->setRaw(['givenName' => '', 'surname' => ''])
        ->map(['id' => $object, 'email' => null]);
    $user = app(MicrosoftIdentity::class)->resolve($external, (object) ['tid' => $tenant, 'oid' => $object]);
    echo $user->id;
} catch (Throwable) {
    fwrite(STDERR, 'Isolated concurrency fixture failed.');
    exit(1);
}
