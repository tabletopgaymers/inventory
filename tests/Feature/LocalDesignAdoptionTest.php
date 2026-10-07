<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\BaselineProbe;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LocalDesignAdoptionTest extends TestCase
{
    private User $manager;

    private int $category;

    private int $otherCategory;

    private int $collection;

    private int $otherCollection;

    private int $item;

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
        $this->category = DB::table('categories')->insertGetId(['name' => 'Ribbon']);
        $this->otherCategory = DB::table('categories')->insertGetId(['name' => 'Button']);
        $this->collection = DB::table('collections')->insertGetId(['name' => 'Pronoun', 'category_id' => $this->category, 'sku_prefix' => 'PRON-']);
        $this->otherCollection = DB::table('collections')->insertGetId(['name' => 'Pride Flag', 'category_id' => $this->otherCategory, 'sku_prefix' => 'FLAG-']);
        $this->item = DB::table('items')->insertGetId(['name' => 'They/Them', 'collection_id' => $this->collection, 'sku_suffix' => 'THEY', 'sku' => 'PRON-THEY', 'unit_cost' => '0.123456789012']);
        DB::table('items')->insert(['name' => 'He/Him', 'collection_id' => $this->otherCollection, 'sku_suffix' => 'HE', 'sku' => 'FLAG-HE', 'unit_cost' => '0']);
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

    public function test_category_links_use_the_same_filter_and_clear_returns_all_collections(): void
    {
        $this->get('/catalog/categories')->assertOk()->assertSee('/catalog/collections?category='.$this->category, false)->assertDontSee('/lifecycle', false);
        $response = $this->get('/catalog/collections?category='.$this->category)->assertOk()
            ->assertSee('Collections in Ribbon')->assertSee('Only collections in this category are shown.')
            ->assertSee('All categories')->assertSee('Apply')->assertSee('/catalog/items?collection='.$this->collection, false);
        $this->assertSame([$this->collection], $response->viewData('records')->pluck('id')->map(fn ($id) => (int) $id)->all());
        $this->assertSame($this->category, (int) $response->viewData('category')->id);
        $response = $this->get('/catalog/collections?category=')->assertOk()->assertDontSee('Only collections in this category are shown.');
        $this->assertCount(2, $response->viewData('records'));
        $this->assertNull($response->viewData('category'));
        $this->get('/catalog/collections?category[]=1')->assertSessionHasErrors('category');
        $this->get('/catalog/collections?category=999999999')->assertSessionHasErrors('category');
    }

    public function test_items_are_scoped_to_collection_with_both_parent_names_and_clear_link(): void
    {
        $response = $this->get('/catalog/items?collection='.$this->collection)->assertOk()
            ->assertSee('Items in Pronoun')->assertSee('Category:')->assertSee('Ribbon')->assertSee('Collection:')
            ->assertSee('Only items in this collection are shown.')->assertSee('Show all items')->assertDontSee('He/Him');
        $this->assertSame([$this->item], $response->viewData('records')->pluck('id')->map(fn ($id) => (int) $id)->all());
        $this->get('/catalog/items')->assertOk()->assertSee('They/Them')->assertSee('He/Him');
        $this->get('/catalog/items?collection=999999999')->assertSessionHasErrors('collection');
        $this->get('/catalog/items?collection[]=1')->assertSessionHasErrors('collection');
    }

    public function test_archive_confirmation_is_authorized_named_read_only_and_lifecycle_stays_on_edit(): void
    {
        $bufferLevel = ob_get_level();
        $before = DB::table('catalog_metadata')->get()->all();
        $items = DB::table('items')->get()->all();
        $this->get('/catalog/categories/'.$this->category.'/edit')->assertOk()
            ->assertSee('Record status')->assertSee('Set Inactive')->assertSee('/catalog/categories/'.$this->category.'/archive', false);
        $this->assertSame($bufferLevel, ob_get_level(), 'Edit rendering must close its output buffers.');
        $this->get('/catalog/categories/'.$this->category.'/archive')->assertOk()->assertSee('Archive Ribbon')
            ->assertSee('associated records, inventory and earlier history will be retained')
            ->assertSee('name="confirm" value="1"', false)->assertSee('/catalog/categories/'.$this->category.'/lifecycle', false)->assertSee('Cancel');
        $this->assertSame($bufferLevel, ob_get_level(), 'Archive rendering must close its output buffers.');
        $this->assertEquals($before, DB::table('catalog_metadata')->get()->all());
        $this->assertEquals($items, DB::table('items')->get()->all());
        $this->get('/catalog/categories/999999999/archive')->assertNotFound();
        $this->get('/catalog/unknown/'.$this->category.'/archive')->assertNotFound();
        $viewer = User::create(['first_name' => 'Test', 'last_name' => 'Viewer']);
        $this->signIn($viewer);
        $this->get('/catalog/categories/'.$this->category.'/archive')->assertForbidden();
        $this->get('/catalog/categories')->assertOk()->assertDontSee('>Edit', false)->assertDontSee('/lifecycle', false);
    }

    public function test_shell_escapes_record_title_once_has_unique_ids_and_print_has_no_app_styles(): void
    {
        DB::table('items')->where('id', $this->item)->update(['name' => 'They/Them & <em>Ribbon</em>']);
        $html = $this->get('/inventory/items/'.$this->item)->assertOk()->getContent();
        $this->assertStringContainsString('<h1>They/Them &amp; &lt;em&gt;Ribbon&lt;/em&gt;</h1>', $html);
        $this->assertStringNotContainsString('&amp;amp;', $html);
        $this->assertStringNotContainsString('<em>Ribbon</em>', $html);
        preg_match_all('/\sid="([^"]+)"/', $html, $matches);
        $this->assertCount(count(array_unique($matches[1])), $matches[1]);
        $this->assertStringContainsString('action="/logout"', $html);
        $this->assertStringContainsString('Test Manager', $html);
        $this->assertStringContainsString('/inventory/location-counts', $html);
        $paper = view('inventory.count-worksheet', ['location' => (object) ['name' => 'Central'], 'rows' => []])->render();
        $this->assertStringContainsString('href="/worksheet-base.css"', $paper);
        $this->assertStringContainsString('href="/inventory-print.css"', $paper);
        $this->assertStringNotContainsString('href="/app.css"', $paper);
        $this->assertStringNotContainsString('app-shell.js', $paper);
        $this->assertStringContainsString('Findings', $paper);
    }

    public function test_compact_lists_retain_escaped_metadata_for_viewers_without_edit_access(): void
    {
        DB::table('catalog_metadata')->insert([
            ['kind' => 'collections', 'record_id' => $this->collection, 'state' => 'inactive', 'description' => 'Retained collection description', 'notes' => 'Retained collection note'],
            ['kind' => 'categories', 'record_id' => $this->category, 'state' => 'archived', 'description' => null, 'notes' => 'Retained category note'],
        ]);
        $location = DB::table('storage_locations')->insertGetId(['name' => 'Boston MA', 'is_central' => false]);
        DB::table('catalog_metadata')->insert(['kind' => 'storage_locations', 'record_id' => $location, 'state' => 'active', 'notes' => 'Retained location note']);
        DB::table('catalog_references')->insert(['kind' => 'suppliers', 'name' => 'Approved Sample Supplier', 'state' => 'active', 'phone' => '555-0100', 'website' => 'https://example.com', 'address' => 'Sample address', 'notes' => '<em>Retained supplier note</em>']);
        $this->signIn(User::create(['first_name' => 'Test', 'last_name' => 'Viewer']));
        $this->get('/catalog/categories')->assertOk()->assertSee('Retained category note')->assertSee('Archived')->assertDontSee('>Edit', false);
        $this->get('/catalog/collections')->assertOk()->assertSee('Retained collection description')->assertSee('Retained collection note')->assertSee('Inactive')->assertDontSee('>Edit', false);
        $this->get('/catalog/storage_locations')->assertOk()->assertSee('Retained location note')->assertDontSee('>Edit', false);
        $this->get('/catalog/suppliers')->assertOk()->assertSee('555-0100')->assertSee('https://example.com')->assertSee('Sample address')
            ->assertSee('&lt;em&gt;Retained supplier note&lt;/em&gt;', false)->assertDontSee('<em>Retained supplier note</em>', false)->assertDontSee('>Edit', false);
    }
}
