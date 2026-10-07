<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\BaselineProbe;
use App\Support\PurchaseFulfillment;
use App\Support\PurchaseRequests;
use App\Support\RelocationFulfillment;
use App\Support\RelocationRequests;
use App\Support\RequestStock;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class FulfillmentWorkflowTest extends TestCase
{
    private User $manager;

    private User $procurement;

    private User $owner;

    private User $other;

    private int $item;

    private int $second;

    private int $source;

    private int $destination;

    private int $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertTrue(app()->environment('testing'));
        $this->assertSame('tg_inventory_test', DB::connection()->getDatabaseName());
        $this->assertTrue(app(BaselineProbe::class)->inspect()['ready']);
        $this->assertTrue(app(BaselineProbe::class)->schemaState(DB::connection())['fulfillmentReady']);
        DB::beginTransaction();
        foreach (['manager', 'procurement', 'owner', 'other'] as $role) {
            $this->{$role} = User::create(['first_name' => 'Test', 'last_name' => ucfirst($role), 'contact_email' => 'request-test@tabletopgaymers.org', 'contact_attested' => true]);
            DB::table('user_roles')->insert(['user_id' => $this->{$role}->id, 'role' => in_array($role, ['owner', 'other']) ? 'basic' : $role]);
        }
        $category = DB::table('categories')->insertGetId(['name' => 'Ribbon']);
        $collection = DB::table('collections')->insertGetId(['name' => 'Pronoun', 'sku_prefix' => 'FUL-', 'category_id' => $category]);
        $this->item = DB::table('items')->insertGetId(['name' => 'They/Them', 'collection_id' => $collection, 'sku_suffix' => 'A', 'sku' => 'FUL-A', 'unit_cost' => '0.2']);
        $this->second = DB::table('items')->insertGetId(['name' => 'They/Them Ribbon', 'collection_id' => $collection, 'sku_suffix' => 'B', 'sku' => 'FUL-B', 'unit_cost' => '0']);
        $this->source = DB::table('storage_locations')->insertGetId(['name' => 'Boston MA', 'is_central' => false]);
        $this->destination = DB::table('storage_locations')->insertGetId(['name' => 'Seattle WA', 'is_central' => false]);
        $this->supplier = DB::table('catalog_references')->insertGetId(['kind' => 'suppliers', 'name' => 'Approved Sample Supplier', 'state' => 'active']);
        DB::table('inventory_balances')->insert(['item_id' => $this->item, 'storage_location_id' => $this->source, 'quantity' => 10]);
    }

    protected function tearDown(): void
    {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        parent::tearDown();
    }

    private function revision(string $kind, int $id): int
    {
        return (int) DB::table($kind.'_requests')->where('id', $id)->value('revision');
    }

    private function relocation(): int
    {
        return app(RelocationRequests::class)->save($this->manager->id, null, null, ['title' => 'Approved sample relocation', 'source_location_id' => $this->source, 'destination_location_id' => $this->destination, 'lines' => [$this->item => '20', $this->second => '0']], true);
    }

    private function shipment(int $sent = 15): array
    {
        return ['shipped_date' => '2026-10-07', 'lines' => [$this->item => ['sent' => (string) $sent], $this->second => ['sent' => '0']]];
    }

    private function receipt(int $received): array
    {
        return ['received_date' => '2026-10-08', 'explanation' => '', 'lines' => [$this->item => ['received' => (string) $received], $this->second => ['received' => '0']]];
    }

    private function ship(int $id, int $sent = 15): string
    {
        $token = (string) Str::uuid();
        app(RelocationFulfillment::class)->work($this->manager->id, $id, $this->revision('relocation', $id), $this->shipment($sent), 'shipment', 'confirm', $token);

        return $token;
    }

    private function balance(string $kind, int $item): int
    {
        return (int) DB::table('inventory_source_balances')->join('inventory_sources', 'inventory_sources.id', '=', 'inventory_source_balances.source_id')->where('item_id', $item)->where('kind', $kind)->sum('quantity');
    }

    private function location(int $location): int
    {
        return (int) DB::table('inventory_balances')->where('item_id', $this->item)->where('storage_location_id', $location)->value('quantity');
    }

    private function stock(): array
    {
        $result = [];
        foreach (['items', 'inventory_balances', 'inventory_source_balances', 'request_stock_entries', 'purchase_requests', 'relocation_requests', 'purchase_request_activity', 'relocation_request_activity', 'purchase_fulfillment', 'purchase_fulfillment_lines', 'relocation_fulfillment', 'relocation_fulfillment_lines'] as $table) {
            $result[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }

        return $result;
    }

    public function test_shipment_actuals_can_exceed_stock_and_duplicate_posts_once_then_owner_receives_cumulatively(): void
    {
        $id = $this->relocation();
        $service = app(RelocationFulfillment::class);
        $service->work($this->manager->id, $id, 1, ['lines' => [$this->item => ['sent' => '']]], 'shipment', 'save');
        $this->assertSame(10, $this->location($this->source));
        $this->assertSame(0, $this->balance('transit', $this->item));
        $revision = $this->revision('relocation', $id);
        $service->work($this->manager->id, $id, $revision, $this->shipment(), 'shipment', 'review');
        $this->assertSame(0, DB::table('request_stock_entries')->count());
        $token = $this->ship($id);
        $service->work($this->manager->id, $id, $revision, $this->shipment(), 'shipment', 'confirm', $token);
        $this->assertSame(-5, $this->location($this->source));
        $this->assertSame(15, $this->balance('transit', $this->item));
        $this->assertSame(10, app(RequestStock::class)->held($this->item));
        $this->assertSame(1, DB::table('request_stock_entries')->count());
        // Transfer ownership solely in this rolled-back synthetic fixture.
        DB::table('relocation_requests')->where('id', $id)->update(['owner_id' => $this->owner->id]);
        $service->work($this->owner->id, $id, $this->revision('relocation', $id), $this->receipt(6), 'receipt', 'save');
        $this->assertSame('Receiving', DB::table('relocation_requests')->where('id', $id)->value('status'));
        $this->assertSame(0, $this->location($this->destination));
        $this->assertSame(15, $this->balance('transit', $this->item));
        $this->assertSame(6, (int) $service->data($id)['lines'][$this->item]['received']);
        $token = (string) Str::uuid();
        $revision = $this->revision('relocation', $id);
        $service->work($this->owner->id, $id, $revision, $this->receipt(15), 'receipt', 'confirm', $token);
        $service->work($this->owner->id, $id, $revision, $this->receipt(15), 'receipt', 'confirm', $token);
        $this->assertSame(0, $this->balance('transit', $this->item));
        $this->assertSame(15, $this->location($this->destination));
        $this->assertSame(10, app(RequestStock::class)->held($this->item));
        $this->assertSame('0.200000000000', DB::table('items')->where('id', $this->item)->value('unit_cost'));
        $this->assertSame(2, DB::table('request_stock_entries')->count());
    }

    public function test_manager_shortage_and_overage_clear_only_sent_and_append_explicit_adjustments(): void
    {
        foreach ([12, 18, 0] as $received) {
            $id = $this->relocation();
            $this->ship($id);
            $before = app(RequestStock::class)->held($this->item);
            $clean = app(RelocationFulfillment::class)->work($this->manager->id, $id, $this->revision('relocation', $id), $this->receipt($received), 'receipt', 'review');
            $this->assertTrue($clean['discrepancy']);
            app(RelocationFulfillment::class)->work($this->manager->id, $id, $this->revision('relocation', $id), $this->receipt($received), 'receipt', 'confirm', (string) Str::uuid());
            $this->assertSame(0, $this->balance('transit', $this->item));
            $this->assertSame($before + $received - 15, app(RequestStock::class)->held($this->item));
            $this->assertSame($received - 15, (int) DB::table('request_stock_entries')->where('relocation_request_id', $id)->where('entry_kind', 'discrepancy')->value('quantity_change'));
            $this->assertSame('0.200000000000', DB::table('items')->where('id', $this->item)->value('unit_cost'));
        }
    }

    public function test_owner_discrepancy_other_actor_revocation_and_mutating_shipped_lines_are_denied(): void
    {
        $id = $this->relocation();
        DB::table('relocation_requests')->where('id', $id)->update(['owner_id' => $this->owner->id]);
        $this->ship($id);
        $before = $this->stock();
        foreach ([fn () => app(RelocationFulfillment::class)->work($this->owner->id, $id, 2, $this->receipt(10), 'receipt', 'confirm', (string) Str::uuid()),
            fn () => app(RelocationFulfillment::class)->work($this->other->id, $id, 2, $this->receipt(15), 'receipt', 'save'),
            fn () => app(RelocationRequests::class)->fulfillment($this->manager->id, $id, 2, [$this->item => 99]),
        ] as $action) {
            try {
                $action();
                $this->fail('Denied action succeeded.');
            } catch (HttpException $error) {
                $this->assertSame(403, $error->getStatusCode());
            }
            $this->assertSame($before, $this->stock());
        }
        DB::table('user_roles')->where('user_id', $this->manager->id)->delete();
        try {
            app(RelocationFulfillment::class)->work($this->manager->id, $id, 2, $this->receipt(15), 'receipt', 'confirm', (string) Str::uuid());
            $this->fail('Removed role accepted.');
        } catch (HttpException $error) {
            $this->assertSame(403, $error->getStatusCode());
        }
        $this->assertSame($before, $this->stock());
    }

    public function test_title_tracking_exceptions_preserve_postings_and_reject_unsafe_urls(): void
    {
        $id = $this->relocation();
        $this->ship($id);
        $entries = DB::table('request_stock_entries')->get()->toJson();
        $service = app(RelocationFulfillment::class);
        $tracking = [['carrier' => 'UPS', 'number' => 'Test 123', 'url' => ''], ['carrier' => 'Other', 'number' => 'Test 456', 'url' => 'https://example.org/track']];
        $service->tracking($this->manager->id, $id, 2, $tracking);
        app(RelocationRequests::class)->title($this->manager->id, $id, 3, 'Approved corrected title');
        $service->work($this->manager->id, $id, 4, $this->receipt(15), 'receipt', 'confirm', (string) Str::uuid());
        $service->tracking($this->manager->id, $id, 5, [$tracking[0]]);
        $this->assertSame(1, count($service->data($id)['tracking']));
        $this->assertSame(15, (int) $service->data($id)['lines'][$this->item]['sent']);
        $this->assertSame($entries, DB::table('request_stock_entries')->where('entry_kind', 'shipment')->get()->toJson());
        foreach (['javascript:alert(1)', 'http://example.org/', 'https://user:pass@example.org/'] as $url) {
            try {
                $service->tracking($this->manager->id, $id, 6, [['carrier' => 'Other', 'number' => 'x', 'url' => $url]]);
                $this->fail('Unsafe URL accepted.');
            } catch (ValidationException $error) {
                $this->assertArrayHasKey('tracking', $error->errors());
            }
        }
    }

    private function purchase(): int
    {
        return app(PurchaseRequests::class)->save($this->owner->id, null, null, ['title' => 'Approved sample purchase']);
    }

    private function invoice(int $quantity = 5, string $unit = '0.2'): array
    {
        return ['title' => 'Approved sample purchase', 'supplier_id' => (string) $this->supplier, 'receiving_location_id' => (string) $this->destination, 'ordered_date' => '2026-10-07', 'received_date' => '2026-10-08', 'lines' => [$this->item => ['quantity' => (string) $quantity, 'unit' => $unit, 'cost' => (string) BigDecimal::of($unit)->multipliedBy($quantity)->toScale(2, RoundingMode::HalfUp), 'fee' => '0']], 'shipping_method' => 'line_total'];
    }

    private function order(int $id, array $data): string
    {
        $token = (string) Str::uuid();
        app(PurchaseFulfillment::class)->work($this->procurement->id, $id, $this->revision('purchase', $id), $data, 'order', 'confirm', $token);

        return $token;
    }

    public function test_order_edits_backward_and_cancellation_append_only_own_pending_deltas_and_preserve_dates(): void
    {
        $service = app(PurchaseFulfillment::class);
        $id = $this->purchase();
        $other = $this->purchase();
        $beforeCost = DB::table('items')->where('id', $this->item)->value('unit_cost');
        $this->order($other, $this->invoice(3));
        $token = $this->order($id, $this->invoice(8));
        $service->work($this->procurement->id, $id, 1, $this->invoice(8), 'order', 'confirm', $token);
        $this->order($id, $this->invoice(7));
        $this->order($id, $this->invoice(9));
        $this->assertSame([8, -1, 2], DB::table('request_stock_entries')->where('purchase_request_id', $id)->pluck('quantity_change')->map(fn ($q) => (int) $q)->all());
        $service->transition($this->procurement->id, $id, $this->revision('purchase', $id), 'shipped', '2026-10-08');
        $this->assertSame(12, $this->balance('ordered', $this->item));
        $service->transition($this->procurement->id, $id, $this->revision('purchase', $id), 'backward');
        $this->assertSame('Ordered', DB::table('purchase_requests')->where('id', $id)->value('status'));
        $service->transition($this->procurement->id, $id, $this->revision('purchase', $id), 'backward');
        $this->assertSame(3, $this->balance('ordered', $this->item));
        $this->assertSame('2026-10-08', $service->data($id)['shipped_date']);
        $this->order($id, $service->data($id));
        $service->transition($this->procurement->id, $id, $this->revision('purchase', $id), 'cancel');
        $this->assertSame(3, $this->balance('ordered', $this->item));
        $this->assertSame(10, app(RequestStock::class)->held($this->item));
        $this->assertSame($beforeCost, DB::table('items')->where('id', $this->item)->value('unit_cost'));
        $this->assertSame(9, (int) DB::table('purchase_fulfillment_lines')->where('purchase_request_id', $id)->value('quantity'));
    }

    public function test_receipt_uses_current_held_events_transit_not_ordered_and_final_amounts_once(): void
    {
        $id = $this->purchase();
        $this->order($id, $this->invoice(5));
        $event = DB::table('inventory_sources')->insertGetId(['kind' => 'event', 'name' => 'Approved sample event', 'active' => true]);
        DB::table('inventory_source_balances')->insert(['item_id' => $this->item, 'source_id' => $event, 'quantity' => 10]);
        app(RequestStock::class)->source($this->item, 'transit', 5);
        $service = app(PurchaseFulfillment::class);
        $final = $this->invoice(5, '0.4');
        $preview = $service->work($this->procurement->id, $id, 2, $final, 'receipt', 'review');
        $this->assertSame(25, $preview['projection'][$this->item]['held']);
        DB::table('items')->where('id', $this->item)->update(['unit_cost' => '0.1']);
        $token = (string) Str::uuid();
        $service->work($this->procurement->id, $id, 2, $final, 'receipt', 'confirm', $token);
        $service->work($this->procurement->id, $id, 2, $final, 'receipt', 'confirm', $token);
        $this->assertSame('0.150000000000', DB::table('items')->where('id', $this->item)->value('unit_cost'));
        $this->assertSame(0, $this->balance('ordered', $this->item));
        $this->assertSame(5, $this->location($this->destination));
        $this->assertSame(30, app(RequestStock::class)->held($this->item));
        $this->assertSame(1, DB::table('request_stock_entries')->where('entry_kind', 'purchase_receipt')->count());
        $this->assertSame('Received', DB::table('purchase_requests')->where('id', $id)->value('status'));
        $before = $this->stock();
        foreach (['cancel', 'backward', 'shipped'] as $action) {
            try {
                $service->transition($this->procurement->id, $id, 3, $action, '2026-10-09');
                $this->fail('Received changed.');
            } catch (HttpException $error) {
                $this->assertSame(409, $error->getStatusCode());
            }
        }
        $this->assertSame($before, $this->stock());
        app(PurchaseRequests::class)->note($this->owner->id, $id, 'Approved correction note');
        $this->assertSame(1, DB::table('purchase_request_notes')->where('purchase_request_id', $id)->count());
    }

    public function test_final_receipt_can_remove_absent_lines_and_unknown_cost_uses_received_quantity(): void
    {
        $id = $this->purchase();
        $invoice = $this->invoice();
        $invoice['lines'][$this->second] = ['quantity' => '5', 'unit' => '1', 'cost' => '5', 'fee' => '0'];
        $this->order($id, $invoice);
        $invoice['lines'] = [$this->second => $invoice['lines'][$this->second]];
        DB::table('inventory_balances')->insert(['item_id' => $this->second, 'storage_location_id' => $this->source, 'quantity' => 500]);
        app(PurchaseFulfillment::class)->work($this->procurement->id, $id, 2, $invoice, 'receipt', 'confirm', (string) Str::uuid());
        $this->assertSame(0, $this->balance('ordered', $this->item));
        $this->assertSame(0, $this->balance('ordered', $this->second));
        $this->assertSame('1.000000000000', DB::table('items')->where('id', $this->second)->value('unit_cost'));
        $this->assertSame(0, $this->location($this->destination));
        $this->assertSame(1, DB::table('purchase_fulfillment_lines')->where('purchase_request_id', $id)->count());
    }

    public function test_injected_failure_after_posting_rolls_back_stock_cost_lines_and_activity(): void
    {
        $id = $this->purchase();
        $this->order($id, $this->invoice());
        $before = $this->stock();
        DB::listen(function (QueryExecuted $query) {
            if (str_starts_with($query->sql, 'insert into `request_stock_entries`')) {
                throw new \PDOException('Controlled posting failure');
            }
        });
        try {
            app(PurchaseFulfillment::class)->work($this->procurement->id, $id, 2, $this->invoice(5, '0.4'), 'receipt', 'confirm', (string) Str::uuid());
            $this->fail('Injected failure was ignored.');
        } catch (\PDOException $error) {
            $this->assertSame('Controlled posting failure', $error->getMessage());
        }
        $this->assertSame($before, $this->stock());
    }

    public function test_purchase_management_permissions_stale_revision_and_invalid_invoice_preserve_stock(): void
    {
        $id = $this->purchase();
        $before = $this->stock();
        try {
            app(PurchaseFulfillment::class)->work($this->owner->id, $id, 1, $this->invoice(), 'order', 'confirm', (string) Str::uuid());
            $this->fail('Owner can order.');
        } catch (HttpException $error) {
            $this->assertSame(403, $error->getStatusCode());
        }
        foreach ([0, 1] as $revision) {
            $invoice = $this->invoice();
            if ($revision === 1) {
                $invoice['lines'][$this->item]['cost'] = '999.00';
            }
            try {
                app(PurchaseFulfillment::class)->work($this->procurement->id, $id, $revision, $invoice, 'order', 'confirm', (string) Str::uuid());
                $this->fail('Invalid invoice/revision accepted.');
            } catch (ValidationException $error) {
                $this->assertNotEmpty($error->errors());
            }
            $this->assertSame($before, $this->stock());
        }
        $this->procurement->update(['enabled' => false]);
        try {
            $this->order($id, $this->invoice());
            $this->fail('Disabled Procurement accepted.');
        } catch (HttpException $error) {
            $this->assertSame(403, $error->getStatusCode());
        }
    }

    public function test_http_review_back_confirm_and_history_links_with_independent_viewer(): void
    {
        $id = $this->purchase();
        $this->actingAs($this->procurement)->withSession(['authentication' => ['started' => now()->timestamp, 'activity' => now()->timestamp, 'remember' => false]]);
        $response = $this->get('/purchases/'.$id.'/fulfillment/order')->assertOk()->assertSee('By line total');
        preg_match('/name="work" value="([^"]+)"/', $response->getContent(), $match);
        $token = $match[1];
        $this->post('/purchases/'.$id.'/fulfillment/order', $this->invoice() + ['work' => $token, 'action' => 'review'])->assertRedirect('/purchases/'.$id.'/fulfillment/order/review/'.$token);
        $this->get('/purchases/'.$id.'/fulfillment/order/review/'.$token)->assertOk()->assertSee('Confirm order');
        $this->assertSame(0, $this->balance('ordered', $this->item));
        $this->get('/purchases/'.$id.'/fulfillment/order?work='.$token)->assertOk()->assertSee('Approved sample purchase');
        $this->post('/purchases/'.$id.'/fulfillment/order/confirm/'.$token)->assertRedirect('/purchases/'.$id);
        $this->post('/purchases/'.$id.'/fulfillment/order/confirm/'.$token)->assertRedirect('/purchases/'.$id);
        $this->get('/inventory/items/'.$this->item)->assertOk()->assertSee('Purchase expectation:')->assertSee('/purchases/'.$id, false)->assertSee('[+5]');
        $this->actingAs($this->other);
        $this->get('/purchases/'.$id.'/fulfillment/order?view=1')->assertOk();
        $this->get('/purchases/'.$id.'/fulfillment/receipt')->assertForbidden();
        $this->get('/purchases/'.$id.'/fulfillment/order/review/'.$token)->assertNotFound();
    }

    public function test_relocation_http_shipment_receiving_discrepancy_and_post_completion_views(): void
    {
        $id = $this->relocation();
        $this->actingAs($this->manager)->withSession(['authentication' => ['started' => now()->timestamp, 'activity' => now()->timestamp, 'remember' => false]]);
        $response = $this->get('/relocations/'.$id.'/fulfillment/shipment')->assertOk();
        preg_match('/name="work" value="([^"]+)"/', $response->getContent(), $match);
        $token = $match[1];
        $this->post('/relocations/'.$id.'/fulfillment/shipment', $this->shipment() + ['work' => $token, 'action' => 'review'])->assertRedirect();
        $this->get('/relocations/'.$id.'/fulfillment/shipment/review/'.$token)->assertOk()->assertSee('Confirm shipment');
        $this->post('/relocations/'.$id.'/fulfillment/shipment/confirm/'.$token)->assertRedirect('/relocations/'.$id);
        $this->get('/relocations/'.$id)->assertOk()->assertSee('Receive shipment');
        $response = $this->get('/relocations/'.$id.'/fulfillment/receipt')->assertOk();
        preg_match('/name="work" value="([^"]+)"/', $response->getContent(), $match);
        $token = $match[1];
        $this->post('/relocations/'.$id.'/fulfillment/receipt', $this->receipt(12) + ['work' => $token, 'action' => 'review'])->assertRedirect();
        $this->get('/relocations/'.$id.'/fulfillment/receipt/review/'.$token)->assertOk()->assertSee('3 missing:')->assertSee('Permanent discrepancy');
        $this->post('/relocations/'.$id.'/fulfillment/receipt/confirm/'.$token)->assertRedirect('/relocations/'.$id);
        $this->get('/relocations/'.$id)->assertOk()->assertSee('Complete')->assertSee('View received quantities');
        $this->get('/relocations/'.$id.'/fulfillment/receipt?view=1')->assertOk()->assertSee('value="12"', false);
        $this->get('/inventory/items/'.$this->item)->assertOk()->assertSee('Adjustment: In Transit shortage')->assertSee('/relocations/'.$id, false)->assertSee('±15');
        $before = $this->stock();
        try {
            app(RelocationFulfillment::class)->work($this->manager->id, $id, 2, $this->receipt(12), 'receipt', 'confirm', (string) Str::uuid());
            $this->fail('Competing completion posted again.');
        } catch (HttpException $error) {
            $this->assertSame(409, $error->getStatusCode());
        }
        $this->assertSame($before, $this->stock());
    }

    public function test_returned_purchase_consumes_later_saved_edits_without_discarding_invoice_precision(): void
    {
        $id = $this->purchase();
        $requests = app(PurchaseRequests::class);
        $service = app(PurchaseFulfillment::class);
        $preparation = ['supplier_id' => (string) $this->supplier, 'receiving_location_id' => (string) $this->destination,
            'lines' => [$this->item => ['quantity' => '20', 'estimate' => '1.00']]];
        $requests->prepare($this->procurement->id, $id, $this->revision('purchase', $id), $preparation);
        $invoice = $this->invoice(5, '0.123456789012') + ['other_fees' => '0.07', 'discount' => '0.01', 'tax' => '0.02', 'shipping' => '0.03'];
        $invoice['lines'][$this->item]['fee'] = '0.04';
        $this->order($id, $invoice);
        $service->transition($this->procurement->id, $id, $this->revision('purchase', $id), 'backward');
        $retained = $service->data($id);
        $this->assertSame(5, (int) $retained['lines'][$this->item]['quantity']); // Actual is intentionally distinct from requested20.
        $this->assertSame('0.123456789012', $retained['lines'][$this->item]['unit']);
        $requests->save($this->procurement->id, $id, $this->revision('purchase', $id), ['title' => 'Revised request']);
        $newSupplier = DB::table('catalog_references')->insertGetId(['kind' => 'suppliers', 'name' => 'Approved Sample Supplier Two', 'state' => 'active']);
        $preparation['supplier_id'] = (string) $newSupplier;
        $preparation['receiving_location_id'] = (string) $this->source;
        $preparation['lines'][$this->item]['quantity'] = '9';
        $requests->prepare($this->procurement->id, $id, $this->revision('purchase', $id), $preparation);
        $edited = $service->data($id);
        $this->assertSame('Revised request', $edited['title']);
        $this->assertSame($newSupplier, (int) $edited['supplier_id']);
        $this->assertSame($this->source, (int) $edited['receiving_location_id']);
        $this->assertSame(9, (int) $edited['lines'][$this->item]['quantity']);
        $this->assertSame('0.123456789012', $edited['lines'][$this->item]['unit']);
        $this->assertSame('1.11', $edited['lines'][$this->item]['cost']);
        foreach (['other_fees', 'discount', 'tax', 'shipping', 'ordered_date', 'received_date'] as $field) {
            $this->assertSame($retained[$field], $edited[$field]);
        }
        $this->assertSame('0.04', $edited['lines'][$this->item]['fee']);
        $service->work($this->procurement->id, $id, $this->revision('purchase', $id), $edited, 'order', 'review');
        $this->assertSame(0, $this->balance('ordered', $this->item));
        $this->order($id, $edited);
        $this->assertSame(9, $this->balance('ordered', $this->item));
        $this->assertSame([5, -5, 9], DB::table('request_stock_entries')->where('purchase_request_id', $id)->pluck('quantity_change')->map(fn ($q) => (int) $q)->all());
        $this->assertSame('Revised request', DB::table('purchase_requests')->where('id', $id)->value('title'));
    }

    public function test_both_relocation_preparation_paths_share_last_saved_sent_counts_before_final_posting(): void
    {
        $id = $this->relocation();
        $requests = app(RelocationRequests::class);
        $service = app(RelocationFulfillment::class);
        $requests->fulfillment($this->manager->id, $id, $this->revision('relocation', $id), [$this->item => '15']);
        $service->work($this->manager->id, $id, $this->revision('relocation', $id), $this->shipment(10), 'shipment', 'save');
        $this->assertSame(10, (int) DB::table('relocation_request_lines')->where('relocation_request_id', $id)->where('item_id', $this->item)->value('fulfillment_quantity'));
        $this->assertNull(DB::table('relocation_fulfillment_lines')->where('relocation_request_id', $id)->where('item_id', $this->item)->value('sent'));
        $requests->fulfillment($this->manager->id, $id, $this->revision('relocation', $id), [$this->item => '18']);
        $this->assertSame(18, (int) $service->data($id)['lines'][$this->item]['sent']);
        $service->work($this->manager->id, $id, $this->revision('relocation', $id), $this->shipment(12), 'shipment', 'save');
        $this->assertSame(12, (int) $service->data($id)['lines'][$this->item]['sent']);
        $requests->fulfillment($this->manager->id, $id, $this->revision('relocation', $id), [$this->item => '18']);
        $values = $service->data($id);
        $review = $service->work($this->manager->id, $id, $this->revision('relocation', $id), $values, 'shipment', 'review');
        $this->assertSame(18, (int) $review['lines'][$this->item]['sent']);
        $this->assertSame(0, $this->balance('transit', $this->item));
        $service->work($this->manager->id, $id, $this->revision('relocation', $id), $values, 'shipment', 'confirm', (string) Str::uuid());
        $this->assertSame(18, $this->balance('transit', $this->item));
        $this->assertSame(-8, $this->location($this->source));
        $this->assertSame(20, (int) DB::table('relocation_request_lines')->where('relocation_request_id', $id)->where('item_id', $this->item)->value('quantity'));
        $this->assertSame(18, (int) $service->data($id)['lines'][$this->item]['sent']);
        $this->assertSame(18, (int) DB::table('relocation_fulfillment_lines')->where('relocation_request_id', $id)->where('item_id', $this->item)->value('sent'));
    }

    public function test_relocation_mid_shipment_failure_preserves_all_counts_stock_and_activity(): void
    {
        $id = $this->relocation();
        $before = $this->stock();
        DB::listen(function (QueryExecuted $query) {
            if (str_starts_with($query->sql, 'insert into `request_stock_entries`')) {
                throw new \PDOException('Controlled relocation failure');
            }
        });
        try {
            $this->ship($id);
            $this->fail('Injected failure was ignored.');
        } catch (\PDOException $error) {
            $this->assertSame('Controlled relocation failure', $error->getMessage());
        }
        $this->assertSame($before, $this->stock());
    }
}
