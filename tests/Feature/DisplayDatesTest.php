<?php

namespace Tests\Feature;

use App\Support\DisplayDates;
use PHPUnit\Framework\TestCase;

class DisplayDatesTest extends TestCase
{
    public function test_shared_formats_preserve_existing_zones_and_calendar_dates(): void
    {
        $this->assertSame('07 Oct 2026', DisplayDates::date('2026-10-07 04:55:00', 'UTC'));
        $this->assertSame('07 Oct 2026 4:55 AM UTC', DisplayDates::timestamp('2026-10-07 04:55:00', 'UTC'));
        $this->assertSame('06 Oct 2026 11:55 PM CDT', DisplayDates::timestamp('2026-10-07 04:55:00', 'America/Chicago'));
        $this->assertSame('06 Oct 2026', DisplayDates::date('2026-10-07 04:55:00', 'America/Chicago'));
        $this->assertSame('07 Oct 2026', DisplayDates::businessDate('2026-10-07'));
        $this->assertSame('06 Oct 2026', DisplayDates::date('2026-10-07 04:55:00'));
        $this->assertSame('01 Nov 2026 1:30 AM CDT', DisplayDates::timestamp('2026-11-01 06:30:00'));
        $this->assertSame('01 Nov 2026 1:30 AM CST', DisplayDates::timestamp('2026-11-01 07:30:00'));
    }
}
