<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\BaselineProbe;
use App\Support\CatalogPreparation;
use App\Support\CatalogRecords;
use App\Support\InventorySearch;
use App\Support\StockPosting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CatalogWorkflowTest extends TestCase
{
    private User $manager;

    private int $category;

    private int $collection;

    private int $item;

    private int $location;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertTrue(app()->environment('testing'));
        $this->assertSame('tg_inventory_test', DB::connection()->getDatabaseName());
        $this->assertTrue(app(BaselineProbe::class)->schemaState(DB::connection())['catalogReady']);
        DB::beginTransaction();
        $this->manager = User::create(['first_name' => 'Test', 'last_name' => 'Manager']);
        DB::table('user_roles')->insert(['user_id' => $this->manager->id, 'role' => 'manager']);
        $this->signIn($this->manager);
        $this->category = $this->create('categories', ['name' => 'Ribbon']);
        $this->collection = $this->create('collections', ['name' => 'Pronoun', 'category_id' => $this->category, 'sku_prefix' => 'PRON-']);
        $this->item = $this->create('items', ['name' => 'They/Them', 'collection_id' => $this->collection, 'sku_suffix' => 'THEY']);
        $this->location = $this->create('storage_locations', ['name' => 'Boston MA', 'notes' => 'Retained location note']);
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

    private function create(string $kind, array $data): int
    {
        $this->post('/catalog/'.$kind.'/create', $data)->assertRedirect();

        return (int) DB::table(in_array($kind, ['purposes', 'programs', 'suppliers']) ? 'catalog_references' : $kind)->orderByDesc('id')->value('id');
    }

    private function search(array $data = []): string
    {
        $response = $this->post('/inventory/search', ['action' => 'search'] + $data)->assertRedirect();
        $token = basename($response->headers->get('Location'));
        $this->get('/inventory/results/'.$token)->assertOk();

        return $token;
    }

    public function test_create_exact_metadata_move_sku_and_preserve_immutable_stock_history(): void
    {
        $purpose = $this->create('purposes', ['name' => 'Outreach']);
        $program = $this->create('programs', ['name' => 'Gayme Night']);
        $this->post('/catalog/items/'.$this->item.'/edit', ['name' => 'They/Them Ribbon', 'collection_id' => $this->collection, 'sku_suffix' => 'THEY', 'purpose_id' => $purpose, 'programs' => [$program], 'irs_fmv' => '0', 'in_person_ask' => '1.123456789012', 'bundle_type' => 'Tray', 'notes' => 'Useful note', 'unit_cost' => '999'])->assertRedirect('/inventory/items/'.$this->item);
        $meta = DB::table('item_metadata')->where('item_id', $this->item)->first();
        $this->assertSame('0.000000000000', $meta->irs_fmv);
        $this->assertSame('1.123456789012', $meta->in_person_ask);
        $this->assertNull($meta->online_ask);
        $this->assertSame('0.000000000000', DB::table('items')->where('id', $this->item)->value('unit_cost'));
        $this->assertSame(0, DB::table('inventory_adjustments')->count());
        $rows = [];
        foreach (DB::table('storage_locations')->get() as $location) {
            $rows[$location->id] = ['set' => $location->id === $this->location ? '7' : '0', 'adjust' => '', 'rationale' => 'Original'];
        }
        $group = app(StockPosting::class)->post($this->manager->id, $this->item, (string) Str::uuid(), $rows);
        $snapshot = DB::table('inventory_adjustments')->where('id', $group)->first();
        $newCollection = $this->create('collections', ['name' => 'Pride Flag', 'category_id' => $this->category, 'sku_prefix' => 'FLAG-']);
        $this->post('/catalog/items/'.$this->item.'/edit', ['name' => 'Renamed Ribbon', 'collection_id' => $newCollection, 'sku_suffix' => 'THEY'])->assertRedirect();
        $this->assertSame('FLAG-THEY', DB::table('items')->where('id', $this->item)->value('sku'));
        $this->assertSame(7, (int) DB::table('inventory_balances')->where('item_id', $this->item)->sum('quantity'));
        $this->assertEquals($snapshot, DB::table('inventory_adjustments')->where('id', $group)->first());
        $this->get('/inventory/items/'.$this->item)->assertOk()->assertSee('Renamed Ribbon')->assertSee('Events and pending inventory');
        $this->get('/catalog/collections/'.$newCollection.'/edit')->assertOk();
    }

    public function test_unique_names_sku_prefix_immutability_and_collision_rollback(): void
    {
        $before = DB::table('categories')->count();
        $this->post('/catalog/categories/create', ['name' => 'ribbon'])->assertSessionHas('error');
        $this->assertSame($before, DB::table('categories')->count());
        $this->post('/catalog/collections/'.$this->collection.'/edit', ['name' => 'Pronoun', 'category_id' => $this->category, 'sku_prefix' => 'CHANGED'])->assertSessionHasErrors('sku_prefix');
        $this->assertSame('PRON-', DB::table('collections')->where('id', $this->collection)->value('sku_prefix'));
        $new = $this->create('collections', ['name' => 'Second', 'category_id' => $this->category, 'sku_prefix' => 'OTHER-']);
        $collision = $this->create('items', ['name' => 'Collision', 'collection_id' => $new, 'sku_suffix' => 'THEY']);
        $this->post('/catalog/items/'.$this->item.'/edit', ['name' => 'Should roll back', 'collection_id' => $new, 'sku_suffix' => 'THEY', 'notes' => 'Should roll back'])->assertSessionHas('error');
        $this->assertSame('They/Them', DB::table('items')->where('id', $this->item)->value('name'));
        $this->assertNull(DB::table('item_metadata')->where('item_id', $this->item)->value('notes'));
        $this->assertSame('OTHER-THEY', DB::table('items')->where('id', $collision)->value('sku'));
    }

    public function test_lifecycle_confirmation_restore_no_cascade_and_retired_association_rules(): void
    {
        $this->post('/catalog/categories/'.$this->category.'/lifecycle', ['action' => 'archive'])->assertSessionHasErrors('confirm');
        $this->post('/catalog/categories/'.$this->category.'/lifecycle', ['action' => 'archive', 'confirm' => '1'])->assertRedirect();
        $this->assertSame('active', app(CatalogRecords::class)->record('collections', $this->collection)->state);
        $this->post('/catalog/categories/'.$this->category.'/lifecycle', ['action' => 'activate'])->assertStatus(422);
        $this->post('/catalog/categories/'.$this->category.'/lifecycle', ['action' => 'restore'])->assertRedirect();
        $this->assertSame('inactive', app(CatalogRecords::class)->record('categories', $this->category)->state);
        $this->post('/catalog/collections/create', ['name' => 'Forbidden new child', 'category_id' => $this->category, 'sku_prefix' => 'NO'])->assertSessionHasErrors('categories');
        $this->post('/catalog/collections/'.$this->collection.'/edit', ['name' => 'Retained association', 'category_id' => $this->category, 'sku_prefix' => 'PRON-'])->assertRedirect();
        $this->post('/catalog/storage_locations/'.$this->location.'/lifecycle', ['action' => 'archive', 'confirm' => '1'])->assertRedirect();
        $this->get('/inventory/locations/'.$this->location)->assertOk()->assertSee('Retained location note')->assertSee('Archived');
    }

    public function test_permissions_tampering_and_invalid_fields_make_no_mutation(): void
    {
        foreach (['basic', 'procurement'] as $role) {
            $user = User::create(['first_name' => 'Test', 'last_name' => 'Viewer']);
            DB::table('user_roles')->insert(['user_id' => $user->id, 'role' => $role]);
            $this->signIn($user);
            $this->get('/catalog')->assertOk()->assertDontSee('>Create<', false);
            $this->get('/catalog/items/create')->assertForbidden();
            $this->post('/catalog/items/create', ['name' => 'Denied'])->assertForbidden();
            $this->post('/catalog/items/'.$this->item.'/lifecycle', ['action' => 'archive', 'confirm' => 1])->assertForbidden();
        }
        $this->signIn($this->manager);
        foreach ([['name' => ['bad']], ['name' => "bad\x01"], ['name' => str_repeat('a', 256)], ['name' => ' ']] as $data) {
            $this->post('/catalog/categories/create', $data)->assertSessionHasErrors('name');
        }
        $this->post('/catalog/items/create', ['name' => 'Bad quantity', 'collection_id' => $this->collection, 'sku_suffix' => 'BAD', 'bundle_quantity' => 2])->assertSessionHasErrors('bundle_type');
        $this->post('/catalog/items/create', ['name' => 'Bad money', 'collection_id' => $this->collection, 'sku_suffix' => 'BAD', 'irs_fmv' => '0.1234567890123'])->assertSessionHasErrors('irs_fmv');
        $this->post('/catalog/suppliers/create', ['name' => 'Bad supplier', 'email' => 'bad', 'website' => 'file:///secret'])->assertSessionHasErrors(['email', 'website']);
        DB::table('user_roles')->where('user_id', $this->manager->id)->delete();
        $this->post('/catalog/categories/create', ['name' => 'Revoked'])->assertForbidden();
        $this->assertSame(1, DB::table('items')->count());
    }

    public function test_browse_inactive_nonzero_individual_sources_available_and_column_selection(): void
    {
        $service = app(InventorySearch::class);
        $this->post('/catalog/items/'.$this->item.'/lifecycle', ['action' => 'inactivate'])->assertRedirect();
        $this->assertSame([], $service->results($service->defaults())['rows']);
        $source = DB::table('inventory_sources')->where('kind', 'ordered')->value('id');
        DB::table('inventory_source_balances')->insert(['item_id' => $this->item, 'source_id' => $source, 'quantity' => 5]);
        $result = $service->results($service->defaults());
        $this->assertSame(0, $result['rows'][0]['available']);
        $this->assertSame(['ordered'], array_keys($result['columns']));
        $other = $this->create('storage_locations', ['name' => 'Ames IA']);
        DB::table('inventory_balances')->insert([['item_id' => $this->item, 'storage_location_id' => $this->location, 'quantity' => 10], ['item_id' => $this->item, 'storage_location_id' => $other, 'quantity' => -10]]);
        $event = DB::table('inventory_sources')->insertGetId(['kind' => 'event', 'name' => 'Gen Con', 'active' => true]);
        DB::table('inventory_source_balances')->insert(['item_id' => $this->item, 'source_id' => $event, 'quantity' => 30]);
        $criteria = $service->defaults();
        $criteria['columns'] = ['storage:'.$this->location];
        $result = $service->results($criteria);
        $this->assertSame(0, $result['rows'][0]['available']);
        $this->assertCount(1, $result['columns']);
        $this->assertSame(30, $result['rows'][0]['values']['event:'.$event]);
        $criteria['search'] = 'nonexistent';
        $this->assertSame([], $service->results($criteria)['rows']);
        $criteria['search'] = 'rIbBoN';
        $this->assertCount(1, $service->results($criteria)['rows']);
        $criteria['columns'] = ['transit'];
        $this->assertSame(['transit'], array_keys($service->results($criteria)['columns']));
    }

    public function test_pending_return_refresh_and_csv_exports_displayed_full_snapshot_with_formula_safety(): void
    {
        DB::table('items')->where('id', $this->item)->update(['name' => '=Danger']);
        DB::table('inventory_balances')->insert(['item_id' => $this->item, 'storage_location_id' => $this->location, 'quantity' => -5]);
        $this->get('/inventory')->assertOk()->assertSee('Search inventory or choose Show All.')->assertDontSee('=Danger');
        $token = $this->search(['search' => 'Ribbon', 'columns' => ['storage:'.$this->location]]);
        $this->post('/inventory/preferences', ['context' => $token, 'search' => 'buttons', 'scroll' => 125, 'destination' => '/inventory/items/'.$this->item])->assertRedirect('/inventory/items/'.$this->item.'?browse='.$token);
        DB::table('inventory_balances')->where('item_id', $this->item)->update(['quantity' => 9]);
        $csv = $this->get('/inventory/results/'.$token.'/csv')->assertOk()->streamedContent();
        $this->assertStringContainsString("'=Danger,-5,-5", $csv);
        $this->assertStringNotContainsString(',9,9', $csv);
        $this->get('/inventory/results/'.$token)->assertOk()->assertSee('value="buttons"', false)->assertSee('=Danger')->assertSee('9');
        $csv = $this->get('/inventory/results/'.$token.'/csv')->streamedContent();
        $this->assertStringContainsString("'=Danger,9,9", $csv);
        $this->get('/inventory')->assertOk()->assertSee('value="buttons"', false)->assertDontSee('=Danger');
        $other = User::create(['first_name' => 'Test', 'last_name' => 'Other']);
        $this->signIn($other);
        $this->get('/inventory/results/'.$token)->assertNotFound();
        $this->get('/inventory/results/'.$token.'/csv')->assertNotFound();
    }

    public function test_show_all_reset_invalid_context_empty_search_and_classification_links(): void
    {
        $token = $this->search(['search' => 'no match']);
        $this->get('/inventory/results/'.$token.'/csv')->assertStatus(410);
        $response = $this->post('/inventory/search', ['action' => 'show_all', 'search' => 'no match', 'columns' => ['ordered']])->assertRedirect();
        $this->get($response->headers->get('Location'))->assertOk()->assertSee('They/Them');
        $this->post('/inventory/preferences', ['context' => (string) Str::uuid(), 'search' => 'tampered'])->assertNotFound();
        $this->assertSame('', app(InventorySearch::class)->remembered($this->manager->id)['search']);
        $this->post('/inventory/preferences', ['destination' => 'https://evil.invalid/'])->assertSessionHasErrors('destination');
        $response = $this->get('/inventory/classification/collection/'.$this->collection)->assertRedirect();
        $this->get($response->headers->get('Location'))->assertOk()->assertSee('They/Them');
        $this->post('/inventory/search', ['action' => 'reset'])->assertRedirect('/inventory');
        $this->get('/inventory')->assertSee('Search inventory or choose Show All.');
        $this->artisan('catalog:check')->assertExitCode(0);
        $this->assertTrue(app(CatalogPreparation::class)->prepare(false));
    }
}
