<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Container\Container;

class DisplayDates
{
    public const DEFAULT_ZONE = 'America/Chicago';

    /**
     * Use this list of PHP time-zone identifiers for profile choices and validation.
     *
     * Scope: Shared
     * Task: Dates and time zones
     *
     * @return list<string>
     * @example app/Http/Controllers/UserController.php
     */
    public static function zones(): array
    {
        return \DateTimeZone::listIdentifiers();
    }

    /**
     * Use the signed-in viewer’s valid profile time zone for display or a local today.
     * Use the default zone when there is no authenticated viewer or valid preference.
     *
     * Scope: Shared
     * Task: Dates and time zones
     *
     * @example app/Http/Controllers/RelocationController.php
     */
    public static function effectiveZone(): string
    {
        $zone = Container::getInstance()->bound('auth') ? auth()->user()?->time_zone : null;

        return is_string($zone) && in_array($zone, self::zones(), true) ? $zone : self::DEFAULT_ZONE;
    }

    /**
     * Format a timestamp as a date in the chosen display zone. Input without a zone is interpreted as UTC.
     * Use businessDate for calendar-only input.
     *
     * Scope: Shared
     * Task: Dates and time zones
     *
     * @param mixed $value A timestamp or other date-time value accepted by Carbon.
     * @param string|null $zone Override the viewer’s effective time zone.
     * @example resources/views/saved-lists/show.blade.php
     */
    public static function date(mixed $value, ?string $zone = null): string
    {
        return CarbonImmutable::parse($value, 'UTC')->setTimezone($zone ?? self::effectiveZone())->format('d M Y');
    }

    /**
     * Format a recorded instant with the date, time, and zone abbreviation. Input without a zone is interpreted as UTC.
     *
     * Scope: Shared
     * Task: Dates and time zones
     *
     * @param mixed $value A timestamp or other date-time value accepted by Carbon.
     * @param string|null $zone Override the viewer’s effective time zone.
     * @example resources/views/inventory/adjustment.blade.php
     */
    public static function timestamp(mixed $value, ?string $zone = null): string
    {
        return CarbonImmutable::parse($value, 'UTC')->setTimezone($zone ?? self::effectiveZone())->format('d M Y g:i A T');
    }

    /**
     * Format a calendar date such as a received-on date. The day stays the same in every viewer’s time zone.
     *
     * Scope: Shared
     * Task: Dates and time zones
     *
     * @param string $value A calendar date in Y-m-d format.
     * @example resources/views/purchase/receive.blade.php
     */
    public static function businessDate(string $value): string
    {
        // Calendar dates have no time zone and must never move to a different day.
        return CarbonImmutable::createFromFormat('!Y-m-d', $value)->format('d M Y');
    }
}
