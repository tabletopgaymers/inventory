<?php

namespace Tests\Feature;

use App\Support\EventWorkflow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\EventFixture;
use Tests\TestCase;

class EventConcurrencyTest extends TestCase
{
    use EventFixture;

    private array $events = [];

    private array $children = [];

    private function snapshot(): array
    {
        $result = [];
        foreach (array_diff(DB::getSchemaBuilder()->getTableListing(null, false), ['sessions']) as $table) {
            $rows = array_map(fn ($row) => json_encode($row, JSON_THROW_ON_ERROR), DB::table($table)->get()->all());
            sort($rows);
            $result[$table] = hash('sha256', implode("\n", $rows));
        }
        ksort($result);

        return $result;
    }

    private function active(int $quantity = 1000): int
    {
        $id = $this->eventPlan($quantity);
        $this->events[] = $id;
        app(EventWorkflow::class)->work($this->manager->id, $id, 1, 'activate', [], 'confirm', (string) Str::uuid());

        return $id;
    }

    private function race(array $a, array $b, bool $conflict): void
    {
        $directory = base_path('_private/event-concurrency');
        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
        $gate = (string) Str::uuid();
        $first = new Process([PHP_BINARY, 'tests/event-worker.php', ...array_map('strval', $a), 'hold', $gate], base_path());
        $second = new Process([PHP_BINARY, 'tests/event-worker.php', ...array_map('strval', $b), 'peer', $gate], base_path());
        $this->children = [$first, $second];
        foreach ($this->children as $child) {
            $child->setTimeout(60);
        }
        try {
            $first->start();
            $this->assertTrue($first->waitUntil(fn ($type, $text) => str_contains($text, 'READY')));
            $second->start();
            $first->wait();
            $second->wait();
            $this->assertTrue($first->isSuccessful(), $first->getErrorOutput());
            $this->assertTrue($second->isSuccessful(), $second->getErrorOutput());
            $this->assertStringContainsString('POSTED', $first->getOutput());
            $this->assertStringContainsString($conflict ? 'CONFLICT' : 'POSTED', $second->getOutput());
        } finally {
            foreach ($this->children as $child) {
                if ($child->isRunning()) {
                    $child->stop(1);
                }
            }
            if (is_file($directory.'/gate-'.$gate)) {
                unlink($directory.'/gate-'.$gate);
            }
        }
    }

    public function test_real_concurrent_counts_finalization_and_receiving_event_state_are_serialized(): void
    {
        $this->assertTrue(app()->environment('testing'));
        $this->assertSame('tg_inventory_test', DB::connection()->getDatabaseName());
        $this->assertSame('tg_inventory_test', DB::connection()->getConfig('username'));
        $before = $this->snapshot();
        try {
            $this->eventFixture(false);
            $id = $this->active();
            $data = json_encode($this->eventCounts(250), JSON_THROW_ON_ERROR);
            $this->race([$this->manager->id, $id, 2, 'counts', Str::uuid(), $data], [$this->other->id, $id, 2, 'counts', Str::uuid(), json_encode($this->eventCounts(0))], true);
            $this->assertSame(250, (int) DB::table('event_lines')->where('event_id', $id)->value('remaining'));
            $token = (string) Str::uuid();
            $this->race([$this->manager->id, $id, 3, 'finalize', $token, $data], [$this->manager->id, $id, 3, 'finalize', $token, $data], false);
            $this->assertSame(1, DB::table('event_operations')->where('event_id', $id)->where('action', 'finalize')->count());
            $id = $this->active();
            $this->race([$this->manager->id, $id, 2, 'finalize', Str::uuid(), $data], [$this->other->id, $id, 2, 'finalize', Str::uuid(), $data], true);
            $this->assertSame(1, DB::table('event_operations')->where('event_id', $id)->where('action', 'finalize')->count());
            $receiver = $this->active(10);
            $sender = $this->active();
            $toEvent = json_encode(['lines' => [$this->item => ['remaining' => '250', 'allocations' => [['destination' => 'event:'.$receiver, 'quantity' => '250']]]]], JSON_THROW_ON_ERROR);
            $this->race([$this->manager->id, $receiver, 2, 'finalize', Str::uuid(), json_encode($this->eventCounts(0))], [$this->other->id, $sender, 2, 'finalize', Str::uuid(), $toEvent], true);
            $this->assertSame('Active', DB::table('event_workflows')->where('id', $sender)->value('status'));
            $this->assertSame(0, DB::table('event_operations')->where('event_id', $sender)->where('action', 'finalize')->count());
            $receiver = $this->active(10);
            $sender = $this->active();
            $toEvent = json_encode(['lines' => [$this->item => ['remaining' => '250', 'allocations' => [['destination' => 'event:'.$receiver, 'quantity' => '250']]]]], JSON_THROW_ON_ERROR);
            $this->race([$this->manager->id, $sender, 2, 'finalize', Str::uuid(), $toEvent], [$this->other->id, $receiver, 2, 'counts', Str::uuid(), json_encode($this->eventCounts(0))], true);
            $this->assertSame(260, (int) DB::table('event_lines')->where('event_id', $receiver)->value('brought'));
            $this->assertSame(260, (int) DB::table('inventory_source_balances')->where('source_id', DB::table('event_workflows')->where('id', $receiver)->value('source_id'))->value('quantity'));
        } finally {
            foreach ($this->children as $child) {
                if ($child->isRunning()) {
                    $child->stop(1);
                }
            }
            $sources = DB::table('event_workflows')->whereIn('id', $this->events)->pluck('source_id')->filter()->all();
            foreach (['event_stock_entries', 'event_lines', 'event_operations'] as $table) {
                DB::table($table)->whereIn('event_id', $this->events)->delete();
            }
            DB::table('event_workflows')->whereIn('id', $this->events)->delete();
            DB::table('inventory_source_balances')->whereIn('source_id', $sources)->delete();
            DB::table('inventory_sources')->whereIn('id', $sources)->delete();
            foreach (['item', 'second'] as $property) {
                if (isset($this->{$property})) {
                    DB::table('inventory_balances')->where('item_id', $this->{$property})->delete();
                    DB::table('items')->where('id', $this->{$property})->delete();
                }
            }
            if (isset($this->eventCollection)) {
                DB::table('collections')->where('id', $this->eventCollection)->delete();
            }
            if (isset($this->eventCategory)) {
                DB::table('categories')->where('id', $this->eventCategory)->delete();
            }
            foreach (['source', 'destination'] as $property) {
                if (isset($this->{$property})) {
                    DB::table('storage_locations')->where('id', $this->{$property})->delete();
                }
            }
            foreach (['manager', 'other', 'basic'] as $property) {
                if (isset($this->{$property})) {
                    DB::table('user_roles')->where('user_id', $this->{$property}->id)->delete();
                    DB::table('users')->where('id', $this->{$property}->id)->delete();
                }
            }
            $this->assertSame($before, $this->snapshot(), 'Every preexisting disposable row retained after real-process fixture cleanup');
        }
    }
}
