<?php

namespace Tests\Feature;

use App\Support\EventWorkflow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\EventFixture;
use Tests\TestCase;

class EventTransferHistoryTest extends TestCase
{
    use EventFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->eventFixture();
        $this->signInEvent($this->manager);
    }

    protected function tearDown(): void
    {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        parent::tearDown();
    }

    public function test_one_direct_event_movement_preserves_stock_contribution_actor_cost_and_both_context_links(): void
    {
        $receiver = $this->activeEvent(10);
        $sender = $this->activeEvent();
        $counts = $this->eventCounts();
        $counts['lines'][$this->item]['allocations'] = [['destination' => 'event:'.$receiver, 'quantity' => 250]];
        $token = (string) Str::uuid();
        app(EventWorkflow::class)->work($this->manager->id, $sender, 2, 'finalize', $counts, 'confirm', $token);
        $operation = DB::table('event_operations')->where('operation_id', $token)->first();
        $movement = DB::table('event_stock_entries')->where('operation_id', $operation->id)->where('item_id', $this->item)->where('leg', '<>', 'distribution')->get();
        $this->assertCount(1, $movement, 'One physical movement has one common history row');
        $this->assertSame($receiver, (int) $movement->first()->event_id);
        $this->assertSame(250, (int) $movement->first()->quantity_change);
        $this->assertSame('0.200000000000', $movement->first()->unit_cost);
        $this->assertStringContainsString('(event finalization)', $movement->first()->description);
        $this->assertSame($sender, (int) $operation->event_id);
        $this->assertSame($this->manager->id, (int) $operation->actor_id);
        $this->assertSame(250, json_decode($operation->data, true)['lines'][$this->item]['allocations']['event:'.$receiver]);
        $this->assertSame(260, app(EventWorkflow::class)->data($receiver)['lines'][$this->item]['brought']);
        $this->get('/events/'.$sender)->assertOk()->assertSee('href="/events/'.$receiver.'"', false);
        $this->get('/events/'.$receiver)->assertOk()->assertSee('href="/events/'.$sender.'"', false)->assertSee('(event finalization)');
        $page = $this->get('/inventory/items/'.$this->item)->assertOk()->getContent();
        $this->assertSame(1, substr_count($page, '(event finalization)'));
        $before = $this->eventInventory();
        app(EventWorkflow::class)->work($this->manager->id, $sender, 2, 'finalize', $counts, 'confirm', $token);
        $this->assertSame($before, $this->eventInventory());
    }

    public function test_storage_leftover_retains_its_one_movement_plus_distinct_distribution_history(): void
    {
        $id = $this->activeEvent();
        $token = (string) Str::uuid();
        app(EventWorkflow::class)->work($this->manager->id, $id, 2, 'finalize', $this->eventCounts(), 'confirm', $token);
        $operation = DB::table('event_operations')->where('operation_id', $token)->value('id');
        $entries = DB::table('event_stock_entries')->where('operation_id', $operation)->get()->keyBy('leg');
        $this->assertCount(2, $entries);
        $this->assertSame(-750, (int) $entries['distribution']->quantity_change);
        $this->assertSame(250, (int) $entries['storage:'.$this->source]->quantity_change);
        $this->assertSame($id, (int) $entries['storage:'.$this->source]->event_id);
        $this->assertSame(1250, (int) DB::table('inventory_balances')->where('storage_location_id', $this->source)->value('quantity'));
    }
}
