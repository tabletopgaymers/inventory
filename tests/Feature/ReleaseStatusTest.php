<?php

namespace Tests\Feature;

use App\Support\ReleaseStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ReleaseStatusTest extends TestCase
{
    #[DataProvider('statusCases')]
    public function test_status_allows_only_external_runtime_link_substitution(string $status, bool $linked, bool $accepted): void
    {
        $this->assertSame($accepted, ReleaseStatus::acceptable($status, $linked));
    }

    public static function statusCases(): array
    {
        return [
            ['', false, true],
            [" D storage/app/.gitignore\0?? storage\0", true, true],
            [" D storage/app/.gitignore\0?? storage\0", false, false],
            [" M app/Providers/AppServiceProvider.php\0", true, false],
            ["D  storage/app/.gitignore\0", true, false],
            [" D storage/app/source.php\0", true, false],
            ["?? storage-other\0", true, false],
            ["?? unexpected file.php\0", true, false],
            [" R app/old.php\0app/new.php\0", true, false],
        ];
    }

    public function test_native_linux_shared_storage_fixture_and_dirty_controls(): void
    {
        $fixture = getenv('RELEASE_STATUS_FIXTURE');
        if (! is_string($fixture) || $fixture === '') {
            $this->markTestSkipped('Run the native Linux fixture and supply RELEASE_STATUS_FIXTURE.');
        }
        foreach (['clean' => true, 'shared' => true, 'source-dirty' => false, 'untracked' => false, 'staged' => false] as $name => $accepted) {
            $path = $fixture.'/'.$name.'.bin';
            $this->assertFileExists($path);
            $status = file_get_contents($path);
            $this->assertSame($accepted, ReleaseStatus::acceptable($status, $name !== 'clean'), $name);
            if ($name === 'shared') {
                $this->assertStringContainsString("?? storage\0", $status);
                $this->assertSame(10, substr_count($status, ' D storage/'));
                $this->assertFalse(ReleaseStatus::acceptable($status, false));
            }
        }
    }
}
