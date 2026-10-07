<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\BaselineProbe;
use App\Support\DisplayDates;
use App\Support\InventoryPreparation;
use App\Support\StockNumbers;
use App\Support\StockPosting;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class InventoryWorkflowTest extends TestCase
{
    private int $item;

    private array $locations;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertTrue(app()->environment('testing'));
        $this->assertSame('tg_inventory_test', DB::connection()->getDatabaseName());
        $this->assertTrue(app(BaselineProbe::class)->schemaState(DB::connection())['inventoryReady']);
        DB::beginTransaction();
        $this->manager = User::create(['first_name' => 'Test', 'last_name' => 'Manager']);
        DB::table('user_roles')->insert(['user_id' => $this->manager->id, 'role' => 'manager']);
        app(InventoryPreparation::class)->demo(false);
        $this->item = (int) DB::table('items')->where('sku', 'SAMPLE001')->value('id');
        $this->locations = DB::table('storage_locations')->orderBy('id')->pluck('id')->all();
        DB::table('items')->where('id', $this->item)->update(['unit_cost' => '0.142857142857']);
    }

    protected function tearDown(): void
    {
        Event::forget(QueryExecuted::class);
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        parent::tearDown();
    }

    private function rows(string $set = '0', string $adjust = ''): array
    {
        return array_fill_keys($this->locations, ['set' => $set, 'adjust' => $adjust, 'rationale' => '']);
    }

    private function signIn(User $user): void
    {
        $this->actingAs($user)->withSession(['authentication' => ['started' => now()->timestamp, 'activity' => now()->timestamp, 'remember' => false]]);
    }

    public function test_preview_edit_save_retry_and_second_viewer_preserve_exact_immutable_history(): void
    {
        $this->signIn($this->manager);
        $this->get('/inventory/items/'.$this->item.'/edit')->assertOk()->assertSee('Total Available');
        $drafts = session('stock_drafts');
        $token = array_key_last($drafts);
        $rows = $this->rows();
        $rows[$this->locations[0]] = ['set' => '10', 'adjust' => '-15', 'rationale' => '0'];
        $rows[$this->locations[1]] = ['set' => '20', 'adjust' => '', 'rationale' => '<script>'];
        $this->post('/inventory/items/'.$this->item.'/preview', ['operation' => $token, 'rows' => $rows])->assertRedirect('/inventory/items/'.$this->item.'/review/'.$token);
        $this->assertSame(0, DB::table('inventory_balances')->count());
        $this->assertSame(0, DB::table('inventory_adjustments')->count());
        $this->get('/inventory/items/'.$this->item.'/review/'.$token)->assertOk()->assertSee('On Save')->assertSee('$0.143')->assertSee('&lt;script&gt;', false);
        $this->get('/inventory/items/'.$this->item.'/edit?operation='.$token)->assertOk()->assertSee('value="-15"', false)->assertSee('value="0"', false);
        $save = '/inventory/items/'.$this->item.'/review/'.$token;
        $response = $this->post($save, ['rows' => $this->rows('999')]);
        $group = DB::table('inventory_adjustments')->first();
        $response->assertRedirect('/inventory/adjustments/'.$group->id.'/result');
        $this->post($save)->assertRedirect('/inventory/adjustments/'.$group->id.'/result');
        $this->assertSame(1, DB::table('inventory_adjustments')->count());
        $this->assertSame(2, DB::table('inventory_adjustment_entries')->count());
        $this->assertSame(-5, (int) DB::table('inventory_balances')->where('storage_location_id', $this->locations[0])->value('quantity'));
        $this->assertSame('0.142857142857', DB::table('items')->where('id', $this->item)->value('unit_cost'));
        $entries = DB::table('inventory_adjustment_entries')->get();
        $this->assertSame('0', $entries[0]->rationale);
        $this->assertSame('0.142857142857', $entries[0]->unit_cost);
        $this->get('/inventory/adjustments/'.$group->id.'/result')->assertOk()->assertSee('OK');
        DB::table('items')->where('id', $this->item)->update(['name' => 'Sample Collection', 'unit_cost' => '2']);
        DB::table('storage_locations')->where('id', $this->locations[0])->update(['name' => 'Sample Storage']);
        $this->manager->update(['first_name' => 'Test', 'last_name' => 'Viewer']);
        $viewer = User::create(['first_name' => 'Test', 'last_name' => 'Viewer']);
        DB::table('user_roles')->insert(['user_id' => $viewer->id, 'role' => 'basic']);
        $this->signIn($viewer);
        $this->get('/inventory/items/'.$this->item)->assertOk()->assertSee('-5')->assertDontSee('Edit Inventory');
        $this->get('/inventory/adjustments/'.$group->id)->assertOk()->assertSee('Sample Item')->assertSee('Test Manager')->assertSee('Central')->assertSee('$0.143')->assertSee(DisplayDates::timestamp($group->posted_at))->assertDontSee('>Save<', false);
        $this->post('/inventory/adjustments/'.$group->id)->assertStatus(405);
        $this->get('/inventory/items/'.$this->item.'?sort=description')->assertOk();
        $this->assertEquals($entries->toArray(), DB::table('inventory_adjustment_entries')->get()->toArray());
    }

    public function test_viewer_tampered_target_unreviewed_token_and_revoked_role_cannot_post(): void
    {
        $viewer = User::create(['first_name' => 'Test', 'last_name' => 'Viewer']);
        $this->signIn($viewer);
        $this->get('/inventory/items/'.$this->item.'/edit')->assertForbidden();
        $this->post('/inventory/items/'.$this->item.'/preview', ['operation' => (string) Str::uuid(), 'rows' => $this->rows('4')])->assertForbidden();
        $this->signIn($this->manager);
        $this->get('/inventory/items/'.$this->item.'/edit')->assertOk();
        $token = array_key_last(session('stock_drafts'));
        $this->post('/inventory/items/'.$this->item.'/review/'.$token)->assertStatus(422);
        $this->post('/inventory/items/'.$this->item.'/preview', ['operation' => $token, 'rows' => [9999999 => ['set' => '1']]])->assertStatus(422);
        $this->post('/inventory/items/9999999/preview', ['operation' => $token, 'rows' => $this->rows('1')])->assertNotFound();
        $this->post('/inventory/items/'.$this->item.'/preview', ['operation' => $token, 'rows' => $this->rows('1')])->assertRedirect();
        DB::table('user_roles')->where('user_id', $this->manager->id)->delete();
        $this->post('/inventory/items/'.$this->item.'/review/'.$token)->assertForbidden();
        $this->assertSame(0, DB::table('inventory_adjustments')->count());
        $this->assertSame(0, DB::table('inventory_balances')->count());
    }

    public function test_no_op_zero_and_blank_adjust_create_no_history_and_retries_are_idempotent(): void
    {
        $token = (string) Str::uuid();
        $service = app(StockPosting::class);
        $id = $service->post($this->manager->id, $this->item, $token, $this->rows());
        $this->assertSame($id, $service->post($this->manager->id, $this->item, $token, $this->rows('9')));
        $this->assertSame(0, DB::table('inventory_adjustment_entries')->count());
        $this->assertSame(0, DB::table('inventory_balances')->count());
        $service->post($this->manager->id, $this->item, (string) Str::uuid(), $this->rows('-1000000000'));
        $service->post($this->manager->id, $this->item, (string) Str::uuid(), $this->rows('1000000000'));
        $this->assertSame(2000000000, (int) DB::table('inventory_adjustment_entries')->orderByDesc('id')->value('quantity_change'));
        $service->post($this->manager->id, $this->item, (string) Str::uuid(), $this->rows('0'));
        $this->assertSame(0, (int) DB::table('inventory_balances')->sum('quantity'));
    }

    public function test_admin_posts_and_disablement_is_rechecked_inside_the_transaction(): void
    {
        DB::table('user_roles')->where('user_id', $this->manager->id)->delete();
        DB::table('user_roles')->insert(['user_id' => $this->manager->id, 'role' => 'admin']);
        app(StockPosting::class)->post($this->manager->id, $this->item, (string) Str::uuid(), $this->rows('4'));
        $this->manager->update(['enabled' => false]);
        try {
            app(StockPosting::class)->post($this->manager->id, $this->item, (string) Str::uuid(), $this->rows('9'));
            $this->fail('Disabled actor posted.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
            $this->assertSame(1, DB::table('inventory_adjustments')->count());
            $this->assertSame(8, (int) DB::table('inventory_balances')->sum('quantity'));
        }
    }

    public function test_fraction_blank_set_unsafe_sum_and_excess_precision_are_rejected(): void
    {
        foreach ([['', '0'], ['1.5', '0'], ['1e2', '0'], ['1000000000', '1'], ['1000000001', '0'], ['0', 'not a number']] as [$set, $adjust]) {
            try {
                app(StockPosting::class)->post($this->manager->id, $this->item, (string) Str::uuid(), $this->rows($set, $adjust));
                $this->fail('Invalid quantity accepted.');
            } catch (ValidationException) {
                $this->assertSame(0, DB::table('inventory_adjustments')->count());
            }
        }
        $this->assertSame('$0.001', StockNumbers::displayCost('0.0005'));
        $this->assertSame('$0.000', StockNumbers::displayCost('0.000000000001'));
        $this->assertSame('n/a', StockNumbers::displayCost('0'));
        $this->expectException(ValidationException::class);
        StockNumbers::cost('0.0000000000001');
    }

    public function test_forced_failure_after_balance_before_history_rolls_back_everything(): void
    {
        DB::listen(function (QueryExecuted $event) {
            if (str_starts_with($event->sql, 'insert into `inventory_adjustment_entries`')) {
                throw new \RuntimeException('Isolated injected failure.');
            }
        });
        try {
            app(StockPosting::class)->post($this->manager->id, $this->item, (string) Str::uuid(), $this->rows('7'));
            $this->fail('Failure injection did not run.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Isolated injected failure.', $exception->getMessage());
            $this->assertSame(0, DB::table('inventory_balances')->count());
            $this->assertSame(0, DB::table('inventory_adjustments')->count());
            $this->assertSame(0, DB::table('inventory_adjustment_entries')->count());
        }
    }

    public function test_migration_matches_unchanged_retained_contract_and_preparation_is_idempotent(): void
    {
        $this->artisan('inventory:check')->assertExitCode(0);
        $before = DB::table('items')->count();
        $this->artisan('inventory:prepare')->assertExitCode(0);
        app(InventoryPreparation::class)->demo(false);
        $this->assertSame($before, DB::table('items')->count());
        $this->assertTrue(app(BaselineProbe::class)->schemaState(DB::connection())['inventoryReady']);
    }
}
