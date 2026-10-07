<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\BaselineProbe;
use App\Support\DailyInventoryPosting;
use App\Support\InventorySearch;
use App\Support\LocationCountSearch;
use App\Support\StockPosting;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class DailyInventoryWorkflowTest extends TestCase
{
    private User $manager;

    private int $item;

    private int $other;

    private int $location;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertTrue(app()->environment('testing'));
        $this->assertSame('tg_inventory_test', DB::connection()->getDatabaseName());
        $this->assertTrue(app(BaselineProbe::class)->schemaState(DB::connection())['dailyInventoryReady']);
        DB::beginTransaction();
        $this->manager = User::create(['first_name' => 'Test', 'last_name' => 'Manager']);
        DB::table('user_roles')->insert(['user_id' => $this->manager->id, 'role' => 'manager']);
        $this->signIn($this->manager);
        $category = DB::table('categories')->insertGetId(['name' => 'Ribbon']);
        $collection = DB::table('collections')->insertGetId(['name' => 'Pronoun', 'sku_prefix' => 'COUNT-', 'category_id' => $category]);
        $this->item = DB::table('items')->insertGetId(['name' => 'They/Them', 'collection_id' => $collection, 'sku_suffix' => 'A', 'sku' => 'COUNT-A', 'unit_cost' => '0.123456789012']);
        $this->other = DB::table('items')->insertGetId(['name' => 'They/Them Ribbon', 'collection_id' => $collection, 'sku_suffix' => 'B', 'sku' => 'COUNT-B', 'unit_cost' => '0']);
        $this->location = DB::table('storage_locations')->insertGetId(['name' => 'Boston MA', 'is_central' => false]);
        DB::table('inventory_balances')->insert([['item_id' => $this->item, 'storage_location_id' => $this->location, 'quantity' => 17], ['item_id' => $this->other, 'storage_location_id' => $this->location, 'quantity' => -5]]);
    }

    protected function tearDown(): void
    {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        parent::tearDown();
    }

    private function signIn(User $user): void
    {
        $this->actingAs($user)->withSession(['authentication' => ['started' => now()->timestamp, 'activity' => now()->timestamp, 'remember' => false]]);
    }

    private function context(): string
    {
        $response = $this->post('/inventory/location-counts', ['action' => 'search', 'location_id' => $this->location, 'search' => 'Ribbon'])->assertRedirect();
        parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);

        return $query['context'];
    }

    public function test_location_choices_nonzero_balances_and_unavailable_saved_selections(): void
    {
        DB::table('catalog_metadata')->insert(['kind' => 'storage_locations', 'record_id' => $this->location, 'state' => 'inactive']);
        DB::table('inventory_balances')->where('item_id', $this->item)->update(['quantity' => 5]);
        $service = app(InventorySearch::class);
        $this->assertArrayHasKey('storage:'.$this->location, $service->columns(), 'Offsetting non-zero stock remains inspectable.');
        $criteria = $service->defaults();
        $criteria['search'] = 'No matches';
        $criteria['columns'] = ['storage:'.$this->location];
        $service->remember($this->manager->id, $criteria);
        DB::table('inventory_balances')->where('storage_location_id', $this->location)->update(['quantity' => 0]);
        $this->assertArrayNotHasKey('storage:'.$this->location, $service->columns());
        $this->assertSame($criteria, $service->remembered($this->manager->id));
        $this->get('/inventory')->assertOk()->assertSee('unavailable — remove this saved selection')->assertSee('No matches');
        $this->post('/inventory/search', $criteria + ['action' => 'search'])->assertSessionHasErrors('columns.0');
        $this->assertSame($criteria, $service->remembered($this->manager->id));
        DB::table('inventory_balances')->where('item_id', $this->other)->where('storage_location_id', $this->location)->update(['quantity' => -1]);
        $this->assertArrayHasKey('storage:'.$this->location, $service->columns());
    }

    public function test_count_zero_stock_defaults_overrides_and_legacy_saved_compatibility(): void
    {
        $service = app(LocationCountSearch::class);
        $central = (int) (DB::table('storage_locations')->where('is_central', true)->value('id') ?? DB::table('storage_locations')->insertGetId(['name' => 'Central', 'is_central' => true]));
        DB::table('inventory_balances')->where('item_id', $this->item)->update(['quantity' => 0]);
        DB::table('catalog_metadata')->insert(['kind' => 'items', 'record_id' => $this->other, 'state' => 'inactive']);
        $fresh = $service->criteria(Request::create('/', 'POST', ['location_id' => $this->location]));
        $this->assertFalse($fresh['include_active_zero']);
        $this->assertNotContains($this->item, array_column($service->rows($fresh), 'id'));
        $this->assertContains($this->other, array_column($service->rows($fresh), 'id'));
        $fresh['include_active_zero'] = true;
        $this->assertContains($this->item, array_column($service->rows($fresh), 'id'));
        DB::table('inventory_balances')->where('item_id', $this->other)->update(['quantity' => 0]);
        $this->assertNotContains($this->other, array_column($service->rows($fresh), 'id'));
        $centralCriteria = $service->criteria(Request::create('/', 'POST', ['location_id' => $central]));
        $this->assertTrue($centralCriteria['include_active_zero']);
        $changed = $service->criteria(Request::create('/', 'POST', ['location_id' => $central, 'zero_stock_location' => $this->location, 'include_active_zero' => '0']));
        $this->assertTrue($changed['include_active_zero'], 'No-JS location changes apply the destination default.');
        $override = $service->criteria(Request::create('/', 'POST', ['location_id' => $central, 'zero_stock_location' => $central, 'include_active_zero' => '0']));
        $this->assertFalse($override['include_active_zero']);
        $service->save($this->manager->id, 'Central override', $override, false);
        $page = $this->get('/inventory/location-counts?saved=central%20override')->assertOk();
        $this->assertFalse($page->viewData('criteria')['include_active_zero']);
        $legacy = ['location_id' => $this->location, 'search' => 'Pronoun', 'collections' => [], 'include_inactive' => false];
        $this->assertTrue($service->normalize($legacy)['include_active_zero']);
        $legacy['include_inactive'] = true;
        $preferences = ['saved_counts' => ['legacy' => ['name' => 'Legacy', 'criteria' => $legacy]]];
        DB::table('inventory_preferences')->where('user_id', $this->manager->id)->update(['criteria' => json_encode($preferences)]);
        $page = $this->get('/inventory/location-counts?saved=legacy')->assertOk()->assertSee('results have not been loaded');
        $this->assertNull($page->viewData('rows'));
        $this->assertSame($legacy, $service->saved($this->manager->id)['legacy']['criteria']);
        $this->assertFalse($service->compatible($legacy));
    }

    public function test_invalid_count_retains_value_and_identifies_the_exact_input(): void
    {
        DB::table('items')->where('id', $this->other)->update(['name' => 'They/Them']);
        $token = $this->context();
        $url = '/inventory/location-counts/'.$token.'/enter';
        $this->get($url)->assertOk();
        $before = DB::table('inventory_adjustments')->count();
        $this->from($url)->post('/inventory/location-counts/'.$token.'/review', ['counts' => [$this->item => '-1', $this->other => '']])
            ->assertRedirect($url)->assertSessionHasErrors('counts.'.$this->item)->assertSessionHasInput('counts.'.$this->item, '-1');
        $bag = session('errors');
        $this->assertNotNull($bag);
        // Explicitly persist flashed state between this harness's HTTP requests.
        session()->reflash();
        session()->save();
        $page = $this->get($url)->assertOk();
        $this->assertSame(1, app('view')->shared('errors')->count(), json_encode($bag->getBags()));
        $page->assertSee('href="#count-'.$this->item.'"', false)->assertSee('They/Them · COUNT-A:')
            ->assertSee('aria-invalid="true"', false)->assertSee('aria-describedby="count-'.$this->item.'-error"', false)->assertSee('value="-1"', false);
        $this->assertSame($before, DB::table('inventory_adjustments')->count());
    }

    public function test_blank_zero_commas_atomic_absolute_current_cost_and_idempotent_save(): void
    {
        $token = $this->context();
        $this->get('/inventory/location-counts/'.$token.'/enter')->assertOk()->assertSee('value=""', false);
        $before = DB::table('inventory_adjustments')->count();
        $this->post('/inventory/location-counts/'.$token.'/review', ['counts' => [$this->item => '1,000', $this->other => '']])->assertRedirect();
        $this->get('/inventory/location-counts/'.$token.'/review')->assertOk()->assertSee('They/Them')->assertDontSee('They/Them Ribbon');
        $this->assertSame($before, DB::table('inventory_adjustments')->count());
        DB::table('inventory_balances')->where('item_id', $this->item)->where('storage_location_id', $this->location)->update(['quantity' => 1500]);
        DB::table('items')->where('id', $this->item)->update(['unit_cost' => '0.999999999999']);
        $response = $this->post('/inventory/location-counts/'.$token.'/save', ['action' => 'save', 'rationales' => [$this->item => 'Actual count']])->assertRedirect();
        $this->assertSame(1000, (int) DB::table('inventory_balances')->where('item_id', $this->item)->where('storage_location_id', $this->location)->value('quantity'));
        $this->assertSame(-5, (int) DB::table('inventory_balances')->where('item_id', $this->other)->value('quantity'));
        $entry = DB::table('inventory_adjustment_entries')->orderByDesc('id')->first();
        $this->assertSame(1500, (int) $entry->before_quantity);
        $this->assertSame(-500, (int) $entry->quantity_change);
        $this->assertSame('0.999999999999', $entry->unit_cost);
        $this->assertSame($before + 1, DB::table('inventory_adjustments')->count());
        $this->post('/inventory/location-counts/'.$token.'/save', ['action' => 'save'])->assertRedirect($response->headers->get('Location'));
        $this->assertSame($before + 1, DB::table('inventory_adjustments')->count());
        $this->get($response->headers->get('Location'))->assertOk()->assertSee('/inventory/adjustments/', false);
        $zero = app(DailyInventoryPosting::class)->counts($this->manager->id, $this->location, (string) Str::uuid(), [$this->other => '0'], []);
        $this->assertSame(0, (int) DB::table('inventory_balances')->where('item_id', $this->other)->value('quantity'));
        $this->assertSame(1, DB::table('inventory_count_items')->where('inventory_count_operation_id', $zero)->count());
        $noop = app(DailyInventoryPosting::class)->counts($this->manager->id, $this->location, (string) Str::uuid(), [$this->other => '0', $this->item => ''], []);
        $this->assertSame(0, DB::table('inventory_count_items')->where('inventory_count_operation_id', $noop)->count());
    }

    public function test_atomic_failure_rolls_back_first_item_operation_and_links_then_retry_succeeds(): void
    {
        $before = DB::table('inventory_adjustments')->count();
        $operations = DB::table('inventory_count_operations')->count();
        $real = new StockPosting;
        $calls = 0;
        $mock = Mockery::mock(StockPosting::class);
        $mock->shouldReceive('post')->andReturnUsing(function (...$arguments) use ($real, &$calls) {
            if (++$calls === 2) {
                throw new QueryException('mariadb', 'synthetic insert failure', [], new \RuntimeException('Synthetic storage failure'));
            }

            return $real->post(...$arguments);
        });
        $this->app->instance(StockPosting::class, $mock);
        $operation = (string) Str::uuid();
        try {
            app(DailyInventoryPosting::class)->counts($this->manager->id, $this->location, $operation, [$this->item => '1', $this->other => '2'], []);
            $this->fail('Expected atomic storage failure.');
        } catch (QueryException) {
            $this->assertSame($before, DB::table('inventory_adjustments')->count());
            $this->assertSame($operations, DB::table('inventory_count_operations')->count());
            $this->assertSame(17, (int) DB::table('inventory_balances')->where('item_id', $this->item)->value('quantity'));
            $this->assertSame(-5, (int) DB::table('inventory_balances')->where('item_id', $this->other)->value('quantity'));
        }
        $this->app->instance(StockPosting::class, $real);
        $id = app(DailyInventoryPosting::class)->counts($this->manager->id, $this->location, $operation, [$this->item => '1', $this->other => '2'], []);
        $this->assertSame(2, DB::table('inventory_count_items')->where('inventory_count_operation_id', $id)->count());
    }

    public function test_personal_criteria_only_owner_unique_overwrite_and_location_specific_inclusion(): void
    {
        $criteria = ['location_id' => $this->location, 'search' => 'Ribbon', 'collections' => [], 'include_inactive' => false, 'quantity' => 999, 'results' => ['bad']];
        $service = app(LocationCountSearch::class);
        $service->save($this->manager->id, 'Monthly', $criteria, false);
        $stored = $service->saved($this->manager->id)['monthly'];
        $this->assertSame(['location_id', 'search', 'collections', 'include_active_zero'], array_keys($stored['criteria']));
        try {
            $service->save($this->manager->id, 'MONTHLY', $criteria, false);
            $this->fail('Duplicate needs explicit overwrite.');
        } catch (ValidationException) {
            $this->assertSame('Monthly', $service->saved($this->manager->id)['monthly']['name']);
        }
        $service->save($this->manager->id, 'Monthly', $criteria + [], true);
        app(InventorySearch::class)->remember($this->manager->id, app(InventorySearch::class)->defaults());
        $this->assertCount(1, $service->saved($this->manager->id));
        DB::table('catalog_metadata')->insert(['kind' => 'items', 'record_id' => $this->other, 'state' => 'inactive']);
        DB::table('inventory_balances')->where('item_id', $this->other)->update(['quantity' => 0]);
        $central = DB::table('storage_locations')->insertGetId(['name' => 'Sample Storage', 'is_central' => false]);
        DB::table('inventory_balances')->insert(['item_id' => $this->other, 'storage_location_id' => $central, 'quantity' => 20]);
        $this->assertNotContains($this->other, array_column($service->rows($stored['criteria']), 'id'));
        $this->get('/inventory/location-counts?saved=monthly')->assertOk()->assertSee('They/Them')->assertDontSee('They/Them Ribbon');
        $viewer = User::create(['first_name' => 'Test', 'last_name' => 'Viewer']);
        DB::table('user_roles')->insert(['user_id' => $viewer->id, 'role' => 'basic']);
        $this->signIn($viewer);
        $this->get('/inventory/location-counts?saved=monthly')->assertNotFound();
        $this->assertSame([], $service->saved($viewer->id));
    }

    public function test_count_invalid_inputs_edit_retention_actor_and_current_permission_enforcement(): void
    {
        $token = $this->context();
        foreach (['-1', '1.5', '1,00', '1000000001', '1e3'] as $value) {
            $this->post('/inventory/location-counts/'.$token.'/review', ['counts' => [$this->item => $value]])->assertSessionHasErrors('counts.'.$this->item);
        }
        $this->post('/inventory/location-counts/'.$token.'/review', ['counts' => [999999 => '1']])->assertUnprocessable();
        $this->post('/inventory/location-counts/'.$token.'/review', ['counts' => [$this->item => '5']])->assertRedirect();
        $this->post('/inventory/location-counts/'.$token.'/save', ['action' => 'edit', 'rationales' => [$this->item => 'Keep this rationale']])->assertRedirect('/inventory/location-counts/'.$token.'/enter');
        $this->get('/inventory/location-counts/'.$token.'/enter')->assertOk()->assertSee('value="5"', false);
        $this->post('/inventory/location-counts/'.$token.'/review', ['counts' => [$this->item => '5']])->assertRedirect();
        $this->get('/inventory/location-counts/'.$token.'/review')->assertOk()->assertSee('Keep this rationale');
        $other = User::create(['first_name' => 'Test', 'last_name' => 'Viewer']);
        DB::table('user_roles')->insert(['user_id' => $other->id, 'role' => 'manager']);
        $this->signIn($other);
        $this->post('/inventory/location-counts/'.$token.'/save', ['action' => 'save'])->assertNotFound();
        $this->signIn($this->manager);
        DB::table('user_roles')->where('user_id', $this->manager->id)->where('role', 'manager')->delete();
        $this->post('/inventory/location-counts/'.$token.'/save', ['action' => 'save'])->assertForbidden();
        $this->assertSame(17, (int) DB::table('inventory_balances')->where('item_id', $this->item)->value('quantity'));
        $this->get('/inventory/location-counts/'.$token.'/worksheet')->assertOk()->assertSee('Findings')->assertSee('17');
    }

    public function test_admin_exact_cost_immutable_history_zero_noop_and_nonadmin_tampering(): void
    {
        $this->get('/inventory/items/'.$this->item.'/cost')->assertForbidden();
        $this->post('/inventory/items/'.$this->item.'/cost', ['operation' => (string) Str::uuid(), 'unit_cost' => '1'])->assertForbidden();
        DB::table('user_roles')->insert(['user_id' => $this->manager->id, 'role' => 'admin']);
        $before = DB::table('inventory_adjustments')->count();
        $id = app(DailyInventoryPosting::class)->cost($this->manager->id, $this->item, (string) Str::uuid(), '0.000000000049', 'Cost only');
        $this->assertSame('0.000000000049', DB::table('items')->where('id', $this->item)->value('unit_cost'));
        $this->assertSame(17, (int) DB::table('inventory_balances')->where('item_id', $this->item)->value('quantity'));
        $this->assertSame($before, DB::table('inventory_adjustments')->count());
        $this->get('/inventory/items/'.$this->item)->assertOk()->assertSee('Adjustment: Unit Cost')->assertSee('$0.000')->assertSee('/inventory/cost-adjustments/'.$id, false);
        DB::table('items')->where('id', $this->item)->update(['name' => 'Changed current metadata']);
        $this->get('/inventory/cost-adjustments/'.$id)->assertOk()->assertSee('They/Them')->assertDontSee('Changed current metadata')->assertSee('Cost only');
        $operation = (string) Str::uuid();
        $zero = app(DailyInventoryPosting::class)->cost($this->manager->id, $this->item, $operation, '0', null);
        $this->assertSame($zero, app(DailyInventoryPosting::class)->cost($this->manager->id, $this->item, $operation, '1', null));
        $this->assertNull(app(DailyInventoryPosting::class)->cost($this->manager->id, $this->item, (string) Str::uuid(), '0', null));
        $this->assertSame('0.000000000000', DB::table('items')->where('id', $this->item)->value('unit_cost'));
        foreach (['', '-1', '1e3', '.1', '1000000000000', '1.1234567890123'] as $value) {
            $response = $this->get('/inventory/items/'.$this->item.'/cost')->assertOk();
            preg_match('/name="operation" value="([^"]+)"/', $response->getContent(), $match);
            $this->post('/inventory/items/'.$this->item.'/cost', ['operation' => $match[1], 'unit_cost' => $value])->assertSessionHasErrors('unit_cost');
        }
        $form = $this->get('/inventory/items/'.$this->item.'/cost')->assertOk();
        preg_match('/name="operation" value="([^"]+)"/', $form->getContent(), $match);
        $this->post('/inventory/items/'.$this->item.'/cost', ['operation' => $match[1], 'unit_cost' => '2.123456789012'])->assertRedirect();
        $this->post('/inventory/items/'.$this->item.'/cost', ['operation' => $match[1], 'unit_cost' => '9'])->assertRedirect();
        $this->assertSame('2.123456789012', DB::table('items')->where('id', $this->item)->value('unit_cost'));
        $viewer = User::create(['first_name' => 'Test', 'last_name' => 'Viewer']);
        DB::table('user_roles')->insert(['user_id' => $viewer->id, 'role' => 'basic']);
        $this->signIn($viewer);
        $this->get('/inventory/cost-adjustments/'.$id)->assertOk()->assertDontSee('Save Cost');
        $this->get('/inventory/items/'.$this->item.'/cost')->assertForbidden();
    }
}
