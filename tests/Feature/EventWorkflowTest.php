<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\BaselineProbe;
use App\Support\EventPreparation;
use App\Support\EventWorkflow;
use App\Support\PurchaseFulfillment;
use App\Support\PurchaseRequests;
use App\Support\RequestStock;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class EventWorkflowTest extends TestCase
{
    private static bool $prepared = false;

    private User $manager;

    private User $other;

    private User $basic;

    private int $item;

    private int $second;

    private int $source;

    private int $destination;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertTrue(app()->environment('testing'));
        $this->assertSame('tg_inventory_test', DB::connection()->getDatabaseName());
        $this->assertSame('tg_inventory_test', DB::connection()->getConfig('username'));
        if (! self::$prepared) {
            self::$prepared = app(EventPreparation::class)->prepare();
        }
        $this->assertTrue(self::$prepared);
        $this->assertTrue(app(BaselineProbe::class)->schemaState(DB::connection())['eventsReady']);
        DB::beginTransaction();
        foreach (['manager', 'other', 'basic'] as $name) {
            $this->{$name} = User::create(['first_name' => 'Test', 'last_name' => ucfirst($name), 'contact_email' => 'request-test@tabletopgaymers.org', 'contact_attested' => true]);
            DB::table('user_roles')->insert(['user_id' => $this->{$name}->id, 'role' => $name === 'basic' ? 'basic' : 'manager']);
        }
        $category = DB::table('categories')->insertGetId(['name' => 'Ribbon']);
        $collection = DB::table('collections')->insertGetId(['category_id' => $category, 'name' => 'Pronoun', 'sku_prefix' => 'EVT-']);
        $this->item = DB::table('items')->insertGetId(['name' => 'They/Them', 'collection_id' => $collection, 'sku_suffix' => 'A', 'sku' => 'EVT-A', 'unit_cost' => '0.200000000000']);
        $this->second = DB::table('items')->insertGetId(['name' => 'They/Them Ribbon', 'collection_id' => $collection, 'sku_suffix' => 'B', 'sku' => 'EVT-B', 'unit_cost' => '0']);
        $this->source = DB::table('storage_locations')->insertGetId(['name' => 'Boston MA', 'is_central' => false]);
        $this->destination = DB::table('storage_locations')->insertGetId(['name' => 'Seattle WA', 'is_central' => false]);
        DB::table('inventory_balances')->insert(['item_id' => $this->item, 'storage_location_id' => $this->source, 'quantity' => 2000]);
    }

    protected function tearDown(): void
    {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        parent::tearDown();
    }

    private function token(): string
    {
        return (string) Str::uuid();
    }

    private function revision(int $id): int
    {
        return (int) DB::table('event_workflows')->where('id', $id)->value('revision');
    }

    private function plan(int $quantity = 1000, ?string $default = null, ?string $token = null): int
    {
        return app(EventWorkflow::class)->plan($this->manager->id, null, null, ['name' => 'Approved sample event', 'initial_source_id' => $this->source, 'default_destination' => $default ?? 'storage:'.$this->source,
            'supplies' => [['item_id' => $this->item, 'source' => 'storage:'.$this->source, 'quantity' => $quantity]]], $token ?? $this->token());
    }

    private function active(int $quantity = 1000, ?string $default = null): int
    {
        $id = $this->plan($quantity, $default);
        app(EventWorkflow::class)->work($this->manager->id, $id, $this->revision($id), 'activate', [], 'confirm', $this->token());

        return $id;
    }

    private function counts(mixed $remaining = '250', array $allocations = []): array
    {
        return ['lines' => [$this->item => ['remaining' => $remaining, 'allocations' => $allocations ?: [['destination' => 'storage:'.$this->source, 'quantity' => $remaining]]]]];
    }

    private function inventory(): array
    {
        $result = [];
        foreach (['items', 'inventory_balances', 'inventory_sources', 'inventory_source_balances', 'event_stock_entries', 'request_stock_entries', 'inventory_adjustments', 'inventory_adjustment_entries', 'item_cost_entries'] as $table) {
            $result[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }

        return $result;
    }

    private function eventQuantity(int $id): int
    {
        $source = DB::table('event_workflows')->where('id', $id)->value('source_id');

        return (int) DB::table('inventory_source_balances')->where('source_id', $source)->where('item_id', $this->item)->value('quantity');
    }

    private function fails(callable $callback, string $class = ValidationException::class): void
    {
        try {
            $callback();
            $this->fail('Expected rejection');
        } catch (\Throwable $error) {
            $this->assertInstanceOf($class, $error);
        }
    }

    public function test_planning_incomplete_defaults_duplicate_save_and_review_move_no_stock(): void
    {
        $before = $this->inventory();
        $token = $this->token();
        $id = app(EventWorkflow::class)->plan($this->manager->id, null, null, ['name' => 'Approved sample event', 'supplies' => []], $token);
        $this->assertSame($id, app(EventWorkflow::class)->plan($this->manager->id, null, null, ['name' => 'Ignored duplicate'], $token));
        $this->assertSame($before, $this->inventory());
        $this->fails(fn () => app(EventWorkflow::class)->work($this->manager->id, $id, 1, 'activate', [], 'review'));
        $id = $this->plan();
        app(EventWorkflow::class)->work($this->manager->id, $id, 1, 'activate', ['actual_date' => '2020-02-29'], 'review');
        $this->assertSame($before, $this->inventory());
        $this->assertNull(DB::table('event_workflows')->where('id', $id)->value('source_id'));
    }

    public function test_activation_posts_once_actual_date_does_not_backdate_history_or_adopt_legacy_stock(): void
    {
        $legacy = DB::table('inventory_sources')->insertGetId(['kind' => 'event', 'name' => 'Approved sample event', 'active' => true]);
        DB::table('inventory_source_balances')->insert(['source_id' => $legacy, 'item_id' => $this->item, 'quantity' => 99]);
        $id = $this->plan();
        $token = $this->token();
        app(EventWorkflow::class)->work($this->manager->id, $id, 1, 'activate', ['actual_date' => '2020-02-29'], 'confirm', $token);
        $after = $this->inventory();
        $this->assertTrue(app(EventWorkflow::class)->work($this->manager->id, $id, 1, 'activate', [], 'confirm', $token)['duplicate']);
        $this->assertSame($after, $this->inventory());
        $this->assertSame(1000, $this->eventQuantity($id));
        $this->assertSame(99, (int) DB::table('inventory_source_balances')->where('source_id', $legacy)->value('quantity'));
        $operation = DB::table('event_operations')->where('operation_id', $token)->first();
        $this->assertSame('2020-02-29', json_decode($operation->data, true)['actual_date']);
        $this->assertSame(now('UTC')->format('Y-m-d'), substr($operation->posted_at, 0, 10));
        $this->fails(fn () => app(EventWorkflow::class)->work($this->manager->id, $id, $this->revision($id), 'activate', [], 'confirm', $this->token()), HttpException::class);
    }

    public function test_blank_zero_and_partial_split_progress_are_shared_without_movement(): void
    {
        $id = $this->active();
        $before = $this->inventory();
        app(EventWorkflow::class)->work($this->other->id, $id, 2, 'counts', $this->counts('', [['destination' => 'storage:'.$this->source, 'quantity' => '']]), 'save', $this->token());
        $this->assertSame('', app(EventWorkflow::class)->data($id)['lines'][$this->item]['remaining']);
        $this->assertSame($before, $this->inventory());
        app(EventWorkflow::class)->work($this->manager->id, $id, 3, 'counts', $this->counts('0', []), 'save', $this->token());
        $this->assertSame('0', app(EventWorkflow::class)->data($id)['lines'][$this->item]['remaining']);
        $this->assertSame($before, $this->inventory());
        app(EventWorkflow::class)->work($this->other->id, $id, 4, 'counts', $this->counts('250', [['destination' => 'storage:'.$this->source, 'quantity' => '100'], ['destination' => 'storage:'.$this->destination, 'quantity' => '']]), 'save', $this->token());
        $this->assertSame('', app(EventWorkflow::class)->data($id)['lines'][$this->item]['allocations'][1]['quantity']);
        $this->assertSame($before, $this->inventory());
    }

    public function test_stale_shared_progress_and_current_permissions_fail_before_changes(): void
    {
        $id = $this->active();
        app(EventWorkflow::class)->work($this->other->id, $id, 2, 'counts', $this->counts(), 'save', $this->token());
        $before = $this->inventory();
        $this->fails(fn () => app(EventWorkflow::class)->work($this->manager->id, $id, 2, 'counts', $this->counts('0'), 'save', $this->token()));
        foreach (['disabled', 'role', 'contact'] as $reason) {
            if ($reason === 'disabled') {
                DB::table('users')->where('id', $this->manager->id)->update(['enabled' => false]);
            }
            if ($reason === 'role') {
                DB::table('users')->where('id', $this->manager->id)->update(['enabled' => true]);
                DB::table('user_roles')->where('user_id', $this->manager->id)->delete();
            }
            if ($reason === 'contact') {
                DB::table('user_roles')->insert(['user_id' => $this->manager->id, 'role' => 'manager']);
                DB::table('users')->where('id', $this->manager->id)->update(['contact_attested' => false]);
            }
            $this->fails(fn () => app(EventWorkflow::class)->work($this->manager->id, $id, 3, 'counts', $this->counts(), 'save', $this->token()), HttpException::class);
            $this->assertSame($before, $this->inventory());
        }
    }

    public function test_over_brought_can_save_but_refuses_finalization_until_missed_delivery(): void
    {
        $id = $this->active();
        app(EventWorkflow::class)->work($this->manager->id, $id, 2, 'counts', $this->counts('1100'), 'save', $this->token());
        $this->fails(fn () => app(EventWorkflow::class)->work($this->other->id, $id, 3, 'finalize', $this->counts('1100'), 'review'));
        app(EventWorkflow::class)->work($this->other->id, $id, 3, 'delivery', ['supplies' => [['item_id' => $this->item, 'source' => 'storage:'.$this->source, 'quantity' => 100]]], 'confirm', $this->token());
        $this->assertSame('1100', app(EventWorkflow::class)->data($id)['lines'][$this->item]['remaining']);
        app(EventWorkflow::class)->work($this->manager->id, $id, 4, 'finalize', $this->counts('1100'), 'confirm', $this->token());
        $this->assertSame(0, $this->eventQuantity($id));
        $this->assertSame(0, app(EventWorkflow::class)->data($id)['report'][$this->item]['distributed']);
    }

    public function test_known_external_and_grouped_deliveries_post_once_preserve_cost_and_default(): void
    {
        $id = $this->active();
        $token = $this->token();
        $data = ['supplies' => [['item_id' => $this->item, 'source' => 'external', 'quantity' => 20], ['item_id' => $this->item, 'source' => 'external', 'quantity' => 30], ['item_id' => $this->item, 'source' => 'storage:'.$this->destination, 'quantity' => 10]]];
        $before = $this->inventory();
        app(EventWorkflow::class)->work($this->manager->id, $id, 2, 'delivery', $data, 'review');
        $this->assertSame($before, $this->inventory());
        app(EventWorkflow::class)->work($this->manager->id, $id, 2, 'delivery', $data, 'confirm', $token);
        $this->assertSame(1060, $this->eventQuantity($id));
        $this->assertSame('-10', (string) DB::table('inventory_balances')->where('storage_location_id', $this->destination)->value('quantity'));
        $this->assertSame('0.200000000000', DB::table('items')->where('id', $this->item)->value('unit_cost'));
        $this->assertSame('storage:'.$this->source, app(EventWorkflow::class)->data($id)['default_destination']);
        $this->assertSame(2, DB::table('event_stock_entries')->where('operation_id', DB::table('event_operations')->where('operation_id', $token)->value('id'))->count());
        $after = $this->inventory();
        app(EventWorkflow::class)->work($this->manager->id, $id, 2, 'delivery', $data, 'confirm', $token);
        $this->assertSame($after, $this->inventory());
    }

    public function test_delivery_retains_an_obsolete_default_without_transfer_and_finalization_requires_override(): void
    {
        $recipient = $this->active(10);
        $id = $this->active(1000, 'event:'.$recipient);
        app(EventWorkflow::class)->work($this->manager->id, $recipient, 2, 'finalize', $this->counts('0'), 'confirm', $this->token());
        app(EventWorkflow::class)->work($this->manager->id, $id, 2, 'delivery', ['supplies' => [['item_id' => $this->item, 'source' => 'external', 'quantity' => 100]]], 'confirm', $this->token());
        $this->assertSame('event:'.$recipient, app(EventWorkflow::class)->data($id)['default_destination']);
        $this->assertSame(1100, $this->eventQuantity($id));
        $this->assertSame(0, $this->eventQuantity($recipient));
        $this->fails(fn () => app(EventWorkflow::class)->work($this->manager->id, $id, 3, 'finalize', $this->counts('250', [['destination' => 'event:'.$recipient, 'quantity' => 250]]), 'review'), HttpException::class);
        app(EventWorkflow::class)->work($this->manager->id, $id, 3, 'finalize', $this->counts(), 'confirm', $this->token());
        $this->assertSame(850, app(EventWorkflow::class)->data($id)['report'][$this->item]['distributed']);
    }

    public function test_exact_1000_250_750_split_and_duplicate_competing_finalization(): void
    {
        $id = $this->active();
        $token = $this->token();
        $values = $this->counts('250', [['destination' => 'storage:'.$this->source, 'quantity' => '100'], ['destination' => 'storage:'.$this->destination, 'quantity' => '150']]);
        $before = $this->inventory();
        app(EventWorkflow::class)->work($this->other->id, $id, 2, 'finalize', $values, 'review');
        $this->assertSame($before, $this->inventory());
        app(EventWorkflow::class)->work($this->other->id, $id, 2, 'finalize', $values, 'confirm', $token);
        $this->assertSame(0, $this->eventQuantity($id));
        $this->assertSame(1100, (int) DB::table('inventory_balances')->where('storage_location_id', $this->source)->value('quantity'));
        $this->assertSame(150, (int) DB::table('inventory_balances')->where('storage_location_id', $this->destination)->value('quantity'));
        $this->assertSame(750, app(EventWorkflow::class)->data($id)['report'][$this->item]['distributed']);
        $after = $this->inventory();
        app(EventWorkflow::class)->work($this->other->id, $id, 2, 'finalize', $values, 'confirm', $token);
        $this->assertSame($after, $this->inventory());
        $this->fails(fn () => app(EventWorkflow::class)->work($this->manager->id, $id, 3, 'finalize', $values, 'confirm', $this->token()), HttpException::class);
        $this->assertSame($after, $this->inventory());
    }

    public function test_blank_missing_items_incomplete_splits_and_unavailable_destination_block_finalization(): void
    {
        $id = $this->active();
        $before = $this->inventory();
        $this->fails(fn () => app(EventWorkflow::class)->work($this->manager->id, $id, 2, 'finalize', $this->counts(''), 'review'));
        $this->fails(fn () => app(EventWorkflow::class)->work($this->manager->id, $id, 2, 'finalize', ['lines' => []], 'review'), HttpException::class);
        $this->fails(fn () => app(EventWorkflow::class)->work($this->manager->id, $id, 2, 'finalize', $this->counts('250', [['destination' => 'storage:'.$this->source, 'quantity' => '249']]), 'review'));
        DB::table('catalog_metadata')->insert(['kind' => 'storage_locations', 'record_id' => $this->source, 'state' => 'inactive']);
        $this->fails(fn () => app(EventWorkflow::class)->work($this->manager->id, $id, 2, 'finalize', $this->counts(), 'review'));
        $this->assertSame($before, $this->inventory());
    }

    public function test_direct_active_event_leftovers_update_contribution_stock_and_revision_atomically(): void
    {
        $recipient = $this->active(10);
        $id = $this->active(1000, 'event:'.$recipient);
        $values = $this->counts('250', [['destination' => 'event:'.$recipient, 'quantity' => 250]]);
        app(EventWorkflow::class)->work($this->manager->id, $id, 2, 'finalize', $values, 'confirm', $this->token());
        $this->assertSame(260, $this->eventQuantity($recipient));
        $this->assertSame(260, app(EventWorkflow::class)->data($recipient)['lines'][$this->item]['brought']);
        $this->assertSame(3, $this->revision($recipient));
        $this->assertSame(1, DB::table('event_stock_entries')->where('event_id', $recipient)->where('leg', 'incoming:'.$recipient)->count());
        $this->fails(fn () => app(EventWorkflow::class)->work($this->other->id, $recipient, 2, 'counts', $this->counts('0'), 'save', $this->token()));
    }

    public function test_self_planning_and_finalized_event_destinations_refuse(): void
    {
        $planning = $this->plan();
        $id = $this->active();
        $finalized = $this->active(10);
        app(EventWorkflow::class)->work($this->manager->id, $finalized, 2, 'finalize', $this->counts('0'), 'confirm', $this->token());
        foreach ([$planning, $id, $finalized] as $destination) {
            $this->fails(fn () => app(EventWorkflow::class)->work($this->manager->id, $id, 2, 'finalize', $this->counts('250', [['destination' => 'event:'.$destination, 'quantity' => 250]]), 'review'), HttpException::class);
        }
    }

    public function test_supply_failure_rolls_back_event_source_balances_operation_and_contribution(): void
    {
        $id = $this->plan();
        $before = $this->inventory();
        $operations = DB::table('event_operations')->count();
        DB::listen(function (QueryExecuted $query) {
            if (str_starts_with($query->sql, 'insert into `event_stock_entries`')) {
                throw new \RuntimeException('Owned failure injection');
            }
        });
        $this->fails(fn () => app(EventWorkflow::class)->work($this->manager->id, $id, 1, 'activate', [], 'confirm', $this->token()), \RuntimeException::class);
        $this->assertSame($before, $this->inventory());
        $this->assertSame($operations, DB::table('event_operations')->count());
        $this->assertSame(0, DB::table('event_lines')->where('event_id', $id)->count());
        $this->assertNull(DB::table('event_workflows')->where('id', $id)->value('source_id'));
    }

    public function test_finalization_failure_rolls_back_recipient_contribution_and_sender_posting(): void
    {
        $recipient = $this->active(10);
        $id = $this->active();
        $before = $this->inventory();
        $events = DB::table('event_workflows')->get()->toJson();
        $lines = DB::table('event_lines')->get()->toJson();
        DB::listen(function (QueryExecuted $query) {
            if (str_starts_with($query->sql, 'update `event_workflows`') && ($query->bindings[2] ?? null) === 'Finalized') {
                throw new \RuntimeException('Owned failure injection');
            }
        });
        $this->fails(fn () => app(EventWorkflow::class)->work($this->manager->id, $id, 2, 'finalize', $this->counts('250', [['destination' => 'event:'.$recipient, 'quantity' => 250]]), 'confirm', $this->token()), \RuntimeException::class);
        $this->assertSame($before, $this->inventory());
        $this->assertSame($events, DB::table('event_workflows')->get()->toJson());
        $this->assertSame($lines, DB::table('event_lines')->get()->toJson());
    }

    public function test_report_corrections_are_shared_flagged_immutable_and_never_replay_stock(): void
    {
        $id = $this->active();
        app(EventWorkflow::class)->work($this->manager->id, $id, 2, 'finalize', $this->counts(), 'confirm', $this->token());
        $before = $this->inventory();
        $lines = DB::table('event_lines')->get()->toJson();
        $original = DB::table('event_operations')->where('action', 'finalize')->get()->toJson();
        $data = ['lines' => [$this->item => ['brought' => '1100', 'remaining' => '300']], 'explanation' => 'Approved sample explanation'];
        app(EventWorkflow::class)->work($this->other->id, $id, 3, 'correction', $data, 'review');
        $this->assertSame($before, $this->inventory());
        $token = $this->token();
        app(EventWorkflow::class)->work($this->other->id, $id, 3, 'correction', $data, 'confirm', $token);
        $this->assertSame($before, $this->inventory());
        $this->assertSame($lines, DB::table('event_lines')->get()->toJson());
        $this->assertSame($original, DB::table('event_operations')->where('action', 'finalize')->get()->toJson());
        $report = app(EventWorkflow::class)->data($id);
        $this->assertTrue($report['corrected']);
        $this->assertSame(800, $report['report'][$this->item]['distributed']);
        $log = DB::table('event_operations')->where('operation_id', $token)->first();
        $snapshot = json_decode($log->data, true);
        $this->assertSame($this->other->id, (int) $log->actor_id);
        $this->assertSame(750, $snapshot['before'][$this->item]['distributed']);
        $this->assertSame(800, $snapshot['after'][$this->item]['distributed']);
        app(EventWorkflow::class)->work($this->other->id, $id, 3, 'correction', $data, 'confirm', $token);
        $this->assertSame(1, DB::table('event_operations')->where('operation_id', $token)->count());
    }

    public function test_receipt_averaging_uses_real_event_held_stock_and_preserves_external_cost(): void
    {
        $id = $this->active();
        app(EventWorkflow::class)->work($this->manager->id, $id, 2, 'delivery', ['supplies' => [['item_id' => $this->item, 'source' => 'external', 'quantity' => 100]]], 'confirm', $this->token());
        $this->assertSame(2100, app(RequestStock::class)->held($this->item));
        $this->assertSame('0.200000000000', DB::table('items')->where('id', $this->item)->value('unit_cost'));
        DB::table('user_roles')->insert(['user_id' => $this->manager->id, 'role' => 'procurement']);
        $supplier = DB::table('catalog_references')->insertGetId(['kind' => 'suppliers', 'name' => 'Approved Sample Supplier', 'state' => 'active']);
        $purchase = app(PurchaseRequests::class)->save($this->manager->id, null, null, ['title' => 'Approved sample purchase', 'supplier_id' => $supplier, 'receiving_location_id' => $this->source, 'lines' => [$this->item => ['quantity' => '100', 'estimate' => '', 'note' => '']]], 'draft');
        $invoice = ['title' => 'Approved sample purchase', 'supplier_id' => $supplier, 'receiving_location_id' => $this->source, 'ordered_date' => '2026-10-07', 'received_date' => '2026-10-07', 'lines' => [$this->item => ['quantity' => '100', 'unit' => '1', 'cost' => '100.00', 'fee' => '0.00', 'basis' => 'unit']], 'other_fees' => '0.00', 'discount' => '0.00', 'shipping' => '0.00', 'tax' => '0.00', 'shipping_method' => 'line_total'];
        $revision = fn () => (int) DB::table('purchase_requests')->where('id', $purchase)->value('revision');
        app(PurchaseFulfillment::class)->work($this->manager->id, $purchase, $revision(), $invoice, 'order', 'confirm', $this->token());
        app(PurchaseFulfillment::class)->work($this->manager->id, $purchase, $revision(), $invoice, 'receipt', 'confirm', $this->token());
        $this->assertSame('0.236363636364', DB::table('items')->where('id', $this->item)->value('unit_cost'));
        $this->assertSame(2200, app(RequestStock::class)->held($this->item));
    }
}
