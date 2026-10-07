<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Container\Container;

class DisplayDates
{
    public const DEFAULT_ZONE = 'America/Chicago';

    public static function zones(): array
    {
        return \DateTimeZone::listIdentifiers();
    }

    public static function effectiveZone(): string
    {
        $zone = Container::getInstance()->bound('auth') ? auth()->user()?->time_zone : null;

        return is_string($zone) && in_array($zone, self::zones(), true) ? $zone : self::DEFAULT_ZONE;
    }

    public static function date(mixed $value, ?string $zone = null): string
    {
        return CarbonImmutable::parse($value, 'UTC')->setTimezone($zone ?? self::effectiveZone())->format('d M Y');
    }

    public static function timestamp(mixed $value, ?string $zone = null): string
    {
        return CarbonImmutable::parse($value, 'UTC')->setTimezone($zone ?? self::effectiveZone())->format('d M Y g:i A T');
    }

    public static function businessDate(string $value): string
    {
        // Calendar dates have no time zone and must never move to a different day.
        return CarbonImmutable::createFromFormat('!Y-m-d', $value)->format('d M Y');
    }
}
