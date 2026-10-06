<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\BaselineProbe;
use App\Support\PurchaseRequests;
use App\Support\RelocationRequests;
use App\Support\RequestPreparation;
use App\Support\RequestValues;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RequestWorkflowTest extends TestCase
{
    private User $basic;

    private User $other;

    private User $manager;

    private User $procurement;

    private User $admin;

    private int $item;

    private int $second;

    private int $source;

    private int $destination;

    private int $supplier;

    private array $stock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertTrue(app()->environment('testing'));
        $this->assertSame('tg_inventory_test', DB::connection()->getDatabaseName());
        $this->assertTrue(app(RequestPreparation::class)->prepare(false));
        $this->assertTrue(app(BaselineProbe::class)->schemaState(DB::connection())['requestsReady']);
        DB::beginTransaction();
        foreach (['basic', 'other', 'manager', 'procurement', 'admin'] as $name) {
            $this->{$name} = User::create(['first_name' => 'Test', 'last_name' => ucfirst($name), 'contact_email' => 'request-test@tabletopgaymers.org', 'contact_attested' => true]);
            DB::table('user_roles')->insert(['user_id' => $this->{$name}->id, 'role' => $name === 'other' ? 'basic' : $name]);
        }
        $category = DB::table('categories')->insertGetId(['name' => 'Ribbon']);
        $collection = DB::table('collections')->insertGetId(['name' => 'Pronoun', 'sku_prefix' => 'REQ-', 'category_id' => $category]);
        $this->item = DB::table('items')->insertGetId(['name' => 'They/Them', 'collection_id' => $collection, 'sku_suffix' => 'A', 'sku' => 'REQ-A', 'unit_cost' => '0.123456789012']);
        $this->second = DB::table('items')->insertGetId(['name' => 'They/Them Ribbon', 'collection_id' => $collection, 'sku_suffix' => 'B', 'sku' => 'REQ-B', 'unit_cost' => '0']);
        $this->source = DB::table('storage_locations')->insertGetId(['name' => 'Boston MA', 'is_central' => false]);
        $this->destination = DB::table('storage_locations')->insertGetId(['name' => 'Seattle WA', 'is_central' => false]);
        $this->supplier = DB::table('catalog_references')->insertGetId(['kind' => 'suppliers', 'name' => 'Approved Sample Supplier', 'state' => 'active']);
        DB::table('inventory_balances')->insert([['item_id' => $this->item, 'storage_location_id' => $this->source, 'quantity' => 17], ['item_id' => $this->item, 'storage_location_id' => $this->destination, 'quantity' => -5]]);
        $this->stock = $this->invariants();
        $this->signIn($this->basic);
    }

    protected function tearDown(): void
    {
        if (isset($this->stock)) {
            $this->assertSame($this->stock, $this->invariants(), 'Every intake/preparation action preserves all stock, exact cost, sources and immutable history.');
        }
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        parent::tearDown();
    }

    private function invariants(): array
    {
        $result = [];
        foreach (['inventory_balances', 'inventory_adjustments', 'inventory_adjustment_entries', 'inventory_count_operations', 'inventory_count_items', 'item_cost_entries', 'inventory_sources', 'inventory_source_balances'] as $table) {
            $rows = array_map(fn ($row) => json_encode($row), DB::table($table)->get()->all());
            sort($rows);
            $result[$table] = hash('sha256', implode('|', $rows));
        }
        $result['item_costs'] = DB::table('items')->orderBy('id')->pluck('unit_cost', 'id')->all();

        return $result;
    }

    private function signIn(User $user): void
    {
        $this->actingAs($user)->withSession(['authentication' => ['started' => now()->timestamp, 'activity' => now()->timestamp, 'remember' => false]]);
    }

    private function purchase(User $actor): int
    {
        return app(PurchaseRequests::class)->save($actor->id, null, null, []);
    }

    private function work(): string
    {
        $response = $this->get('/relocations/new')->assertRedirect();

        return basename($response->headers->get('Location'));
    }

    private function relocationData(): array
    {
        return ['title' => 'Approved sample relocation', 'details' => 'Preparation only', 'source_location_id' => $this->source, 'destination_location_id' => $this->destination, 'lines' => [$this->item => '2,000', $this->second => '0']];
    }

    public function test_blank_purchase_permanent_owner_notes_access_and_cancel_paths(): void
    {
        $page = $this->get('/purchases/new')->assertOk();
        $token = $page->viewData('token');
        $response = $this->post('/purchases', ['token' => $token])->assertRedirect();
        $id = (int) basename($response->headers->get('Location'));
        $this->post('/purchases', ['token' => $token])->assertRedirect('/purchases/'.$id);
        $this->assertSame(1, DB::table('purchase_request_activity')->where('purchase_request_id', $id)->count());
        $this->assertSame($this->basic->id, (int) DB::table('purchase_requests')->where('id', $id)->value('owner_id'));
        $this->get('/purchases/'.$id)->assertOk()->assertSee('Untitled request')->assertDontSee('Submit Request');
        $this->post('/purchases/'.$id.'/transition', ['action' => 'submit', 'revision' => 1])->assertForbidden();
        $this->post('/purchases/'.$id, ['owner_id' => $this->other->id, 'revision' => 1])->assertSessionHasErrors('owner_id');
        $this->signIn($this->other);
        $this->get('/purchases/'.$id.'/edit')->assertForbidden();
        $this->post('/purchases/'.$id, ['title' => 'Tampered', 'revision' => 1])->assertForbidden();
        $this->post('/purchases/'.$id.'/notes', ['note' => '<script>alert(1)</script>'])->assertRedirect();
        $this->get('/purchases/'.$id)->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert', false);
        $this->post('/purchases/'.$id.'/notes', ['note' => ' '])->assertSessionHasErrors('note');
        $note = DB::table('purchase_request_notes')->where('purchase_request_id', $id)->first();
        $this->post('/purchases/'.$id.'/notes/'.$note->id, ['note' => 'Rewrite'])->assertNotFound();
        $this->signIn($this->manager);
        $this->post('/purchases/'.$id, ['title' => 'Wrong manager', 'revision' => 1])->assertForbidden();
        $this->signIn($this->procurement);
        $this->post('/purchases/'.$id.'/transition', ['action' => 'submit', 'revision' => 1])->assertRedirect();
        $logs = DB::table('purchase_request_activity')->where('purchase_request_id', $id)->count();
        $this->post('/purchases/'.$id.'/transition', ['action' => 'submit', 'revision' => 1])->assertSessionHasErrors('revision');
        $this->assertSame($logs, DB::table('purchase_request_activity')->where('purchase_request_id', $id)->count());
        $this->signIn($this->basic);
        $this->post('/purchases/'.$id.'/transition', ['action' => 'cancel', 'revision' => 2])->assertForbidden();
        $this->signIn($this->procurement);
        $this->post('/purchases/'.$id.'/transition', ['action' => 'return', 'revision' => 2])->assertRedirect();
        $this->signIn($this->basic);
        $this->post('/purchases/'.$id.'/transition', ['action' => 'cancel', 'revision' => 3])->assertRedirect();
        $this->signIn($this->other);
        $this->post('/purchases/'.$id.'/notes', ['note' => 'Correction is a new permanent note.'])->assertRedirect();
        $this->assertSame(2, DB::table('purchase_request_notes')->where('purchase_request_id', $id)->count());
        $this->assertSame('Cancelled', DB::table('purchase_requests')->where('id', $id)->value('status'));
        $this->assertSame($this->basic->id, (int) DB::table('purchase_requests')->where('id', $id)->value('owner_id'));
    }

    public function test_elevated_own_submission_contact_and_revoked_role_are_rechecked(): void
    {
        foreach ([$this->manager, $this->procurement, $this->admin] as $user) {
            $id = $this->purchase($user);
            $this->signIn($user);
            $this->post('/purchases/'.$id.'/transition', ['action' => 'submit', 'revision' => 1])->assertRedirect();
        }
        $id = $this->purchase($this->manager);
        $this->manager->update(['contact_attested' => false]);
        $this->signIn($this->manager);
        $this->post('/purchases/'.$id.'/transition', ['action' => 'submit', 'revision' => 1])->assertForbidden();
        $this->manager->update(['contact_attested' => true]);
        DB::table('user_roles')->where('user_id', $this->manager->id)->delete();
        $this->post('/purchases/'.$id.'/transition', ['action' => 'submit', 'revision' => 1])->assertForbidden();
        $this->manager->update(['enabled' => false]);
        $this->get('/purchases/'.$id)->assertRedirect('/login');
    }

    public function test_preparation_persists_exact_blank_zero_across_workers_and_conflict_retains_input(): void
    {
        $id = $this->purchase($this->basic);
        $this->signIn($this->procurement);
        $data = ['revision' => 1, 'supplier_id' => $this->supplier, 'receiving_location_id' => $this->source, 'planned_date' => '2026-10-06', 'preparation_notes' => 'Incomplete pre-order work', 'lines' => [$this->item => ['quantity' => '0', 'estimate' => '0.123456789012', 'note' => 'Sample line'], $this->second => ['quantity' => '2,000', 'estimate' => '']]];
        $this->post('/purchases/'.$id.'/preparation', $data)->assertRedirect();
        $this->signIn($this->admin);
        $this->get('/purchases/'.$id.'/preparation')->assertOk()->assertSee('0.123456789012')->assertSee('Incomplete pre-order work');
        $line = DB::table('purchase_request_lines')->where('purchase_request_id', $id)->where('item_id', $this->second)->first();
        $this->assertNull($line->estimate);
        $data['preparation_notes'] = 'My stale changes';
        $this->post('/purchases/'.$id.'/preparation', $data)->assertSessionHasErrors('revision')->assertSessionHasInput('preparation_notes', 'My stale changes');
        $data['revision'] = 2;
        $data['lines'][$this->item]['estimate'] = '0';
        $this->post('/purchases/'.$id.'/preparation', $data)->assertRedirect();
        $this->assertSame('0.000000000000', DB::table('purchase_request_lines')->where('purchase_request_id', $id)->where('item_id', $this->item)->value('estimate'));
        $this->signIn($this->manager);
        $this->post('/purchases/'.$id.'/preparation', $data)->assertForbidden();
        $this->signIn($this->basic);
        $this->get('/purchases/'.$id.'/preparation')->assertOk()->assertSee('disabled', false)->assertDontSee('Save preparation');
    }

    public function test_relocation_review_edit_submit_copy_and_saved_fulfillment_are_no_stock(): void
    {
        $token = $this->work();
        $this->get('/relocations/work/'.$token)->assertOk();
        $this->post('/relocations/work/'.$token, ['action' => 'save'])->assertSessionHasErrors('title');
        $data = $this->relocationData();
        $this->post('/relocations/work/'.$token, $data + ['action' => 'review'])->assertRedirect('/relocations/work/'.$token.'/review');
        $this->get('/relocations/work/'.$token.'/review')->assertOk()->assertSee('Edit draft')->assertDontSee('>Submit request<', false);
        $this->post('/relocations/work/'.$token.'/submit')->assertForbidden();
        $response = $this->post('/relocations/work/'.$token, $data + ['action' => 'save'])->assertRedirect();
        $id = (int) basename($response->headers->get('Location'));
        $this->signIn($this->procurement);
        $this->get('/relocations/'.$id.'/edit')->assertForbidden();
        $this->signIn($this->manager);
        $edit = $this->get('/relocations/'.$id.'/edit')->assertRedirect();
        $token = basename($edit->headers->get('Location'));
        $this->post('/relocations/work/'.$token, $data + ['action' => 'review'])->assertRedirect();
        $this->get('/relocations/work/'.$token)->assertOk()->assertSee('Approved sample relocation')->assertSee('2,000');
        $submit = $this->post('/relocations/work/'.$token.'/submit')->assertRedirect('/relocations/'.$id);
        $logs = DB::table('relocation_request_activity')->where('relocation_request_id', $id)->count();
        $this->post('/relocations/work/'.$token.'/submit')->assertRedirect($submit->headers->get('Location'));
        $this->assertSame($logs, DB::table('relocation_request_activity')->where('relocation_request_id', $id)->count());
        $this->post('/relocations/'.$id.'/fulfillment', ['revision' => 2, 'fulfillment' => [$this->item => '900', $this->second => '']])->assertRedirect();
        $this->signIn($this->admin);
        $this->get('/relocations/'.$id)->assertOk()->assertSee('value="900"', false);
        $this->get('/relocations/'.$id.'/worksheet')->assertOk()->assertSee('Sent')->assertDontSee('900');
        $this->post('/relocations/'.$id.'/transition', ['revision' => 3, 'action' => 'cancel'])->assertStatus(409);
        $this->post('/relocations/'.$id.'/transition', ['revision' => 3, 'action' => 'return'])->assertRedirect();
        $this->signIn($this->basic);
        $this->post('/relocations/'.$id.'/transition', ['revision' => 4, 'action' => 'cancel'])->assertRedirect();
        $this->post('/relocations/'.$id.'/title', ['revision' => 5, 'title' => 'Cancelled title correction', 'details' => 'Must not replace details'])->assertRedirect();
        $this->assertSame('Preparation only', DB::table('relocation_requests')->where('id', $id)->value('details'));
        $this->signIn($this->other);
        $copy = $this->post('/relocations/'.$id.'/copy')->assertRedirect();
        $copyToken = basename($copy->headers->get('Location'));
        $work = session('relocation_work.'.$copyToken);
        $this->assertSame('', $work['title']);
        $this->assertNull($work['source_location_id']);
        $this->assertNull($work['destination_location_id']);
        $this->assertSame('', $work['details']);
        $this->assertNull($work['id']);
        $copyData = ['title' => 'Independent copied Draft', 'lines' => $work['lines']];
        $response = $this->post('/relocations/work/'.$copyToken, $copyData + ['action' => 'save'])->assertRedirect();
        $newId = (int) basename($response->headers->get('Location'));
        $this->assertSame($this->other->id, (int) DB::table('relocation_requests')->where('id', $newId)->value('owner_id'));
        $this->assertNull(DB::table('relocation_request_lines')->where('relocation_request_id', $newId)->value('fulfillment_quantity'));
    }

    public function test_bulk_nonnumeric_zero_search_exclusion_and_numeric_rejection(): void
    {
        $token = $this->work();
        $data = ['title' => 'Bulk sample', 'source_location_id' => $this->source, 'destination_location_id' => $this->destination];
        $this->post('/relocations/work/'.$token, $data + ['action' => 'search', 'search' => 'Pronoun'])->assertRedirect();
        $this->post('/relocations/work/'.$token, $data + ['action' => 'add', 'selection' => [$this->item => 'xyzzy', $this->second => '2,000']])->assertRedirect();
        $draft = session('relocation_work.'.$token);
        $this->assertSame('0', $draft['lines'][$this->item]);
        $this->assertSame('2000', $draft['lines'][$this->second]);
        $this->get('/relocations/work/'.$token)->assertOk()->assertDontSee('name="selection['.$this->item.']"', false);
        foreach (['-1', '1.5', '1e3', '1,00', '1000000001'] as $value) {
            try {
                RequestValues::quantity($value, 'selection', false, true);
                $this->fail('Invalid numeric quantity accepted');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('selection', $e->errors());
            }
        }
        $this->assertSame(0, RequestValues::quantity('0', 'quantity'));
        $this->assertNull(RequestValues::quantity('', 'fulfillment', true));
        $this->assertSame(1000000000, RequestValues::quantity('1,000,000,000', 'quantity'));
    }

    public function test_relocation_validation_other_owner_revocation_and_unavailable_later_actions(): void
    {
        $this->signIn($this->manager);
        $id = app(RelocationRequests::class)->save($this->basic->id, null, null, ['title' => 'Incomplete Draft']);
        $this->signIn($this->other);
        $this->get('/relocations/'.$id.'/edit')->assertForbidden();
        $this->post('/relocations/'.$id.'/title', ['revision' => 1, 'title' => 'Tamper'])->assertForbidden();
        $this->signIn($this->manager);
        $this->post('/relocations/'.$id.'/transition', ['revision' => 1, 'action' => 'shipped'])->assertSessionHasErrors('action');
        $this->post('/relocations/'.$id.'/shipment')->assertNotFound();
        $purchase = $this->purchase($this->basic);
        $this->post('/purchases/'.$purchase.'/transition', ['revision' => 1, 'action' => 'ordered'])->assertSessionHasErrors('action');
        $this->post('/purchases/'.$purchase.'/receipt')->assertNotFound();
        $edit = $this->get('/relocations/'.$id.'/edit')->assertRedirect();
        $token = basename($edit->headers->get('Location'));
        $data = $this->relocationData();
        $data['destination_location_id'] = $this->source;
        $this->post('/relocations/work/'.$token, $data + ['action' => 'review'])->assertSessionHasErrors('review');
        $data['destination_location_id'] = $this->destination;
        $this->post('/relocations/work/'.$token, $data + ['action' => 'review'])->assertRedirect();
        DB::table('user_roles')->where('user_id', $this->manager->id)->delete();
        $this->post('/relocations/work/'.$token.'/submit')->assertForbidden();
    }

    public function test_transaction_failure_rolls_back_header_lines_and_activity_and_controller_is_safe(): void
    {
        $id = $this->purchase($this->basic);
        // Fail the activity insert after the header update in the real transaction.
        $connection = DB::connection();
        $inject = true;
        $connection->beforeExecuting(function ($sql) use (&$inject) {
            if ($inject && str_starts_with($sql, 'insert into `purchase_request_activity`')) {
                throw new QueryException('mariadb', 'synthetic request activity failure', [], new \PDOException('private synthetic diagnostics'));
            }
        });
        try {
            $this->post('/purchases/'.$id, ['revision' => 1, 'title' => 'Rollback title'])->assertRedirect()->assertSessionHas('error')->assertSessionHasInput('title', 'Rollback title');
        } finally {
            $inject = false;
        }
        $this->assertNull($connection->table('purchase_requests')->where('id', $id)->value('title'));
        $this->assertSame(1, (int) $connection->table('purchase_requests')->where('id', $id)->value('revision'));
        $this->assertSame(1, $connection->table('purchase_request_activity')->where('purchase_request_id', $id)->count());
    }

    public function test_retired_associations_noop_saves_and_revision_conflicts_do_not_rewrite_saved_work(): void
    {
        $id = app(RelocationRequests::class)->save($this->manager->id, null, null, $this->relocationData());
        DB::table('catalog_metadata')->insert(['kind' => 'items', 'record_id' => $this->item, 'state' => 'archived']);
        $service = app(RelocationRequests::class);
        $service->save($this->manager->id, $id, 1, $this->relocationData());
        $this->assertSame(1, (int) DB::table('relocation_requests')->where('id', $id)->value('revision'));
        $service->title($this->manager->id, $id, 1, 'Other worker title');
        $this->signIn($this->manager);
        $edit = $this->get('/relocations/'.$id.'/edit')->assertRedirect();
        $token = basename($edit->headers->get('Location'));
        $service->title($this->manager->id, $id, 2, 'Saved latest title');
        $this->post('/relocations/work/'.$token, $this->relocationData() + ['action' => 'save'])->assertSessionHasErrors('revision')->assertSessionHasInput('title', 'Approved sample relocation');
        $this->assertSame('Saved latest title', DB::table('relocation_requests')->where('id', $id)->value('title'));
        $this->assertSame(2, DB::table('relocation_request_lines')->where('relocation_request_id', $id)->count());
    }

    public function test_failed_preparation_restores_lines_and_submit_rechecks_retired_storage(): void
    {
        $id = $this->purchase($this->basic);
        $service = app(PurchaseRequests::class);
        $service->prepare($this->procurement->id, $id, 1, ['lines' => [$this->item => ['quantity' => '0', 'estimate' => '0']]]);
        $header = (array) DB::table('purchase_requests')->where('id', $id)->first();
        $lines = DB::table('purchase_request_lines')->where('purchase_request_id', $id)->get()->toJson();
        $activity = DB::table('purchase_request_activity')->where('purchase_request_id', $id)->count();
        $inject = true;
        DB::connection()->beforeExecuting(function ($sql) use (&$inject) {
            if ($inject && str_starts_with($sql, 'insert into `purchase_request_activity`')) {
                throw new QueryException('mariadb', 'synthetic preparation activity failure', [], new \PDOException('private synthetic diagnostics'));
            }
        });
        $this->signIn($this->procurement);
        try {
            $this->post('/purchases/'.$id.'/preparation', ['revision' => 2, 'preparation_notes' => 'Retain my input', 'lines' => [$this->second => ['quantity' => '4']]])
                ->assertRedirect()->assertSessionHas('error')->assertSessionHasInput('preparation_notes', 'Retain my input');
        } finally {
            $inject = false;
        }
        $this->assertSame($header, (array) DB::table('purchase_requests')->where('id', $id)->first());
        $this->assertSame($lines, DB::table('purchase_request_lines')->where('purchase_request_id', $id)->get()->toJson());
        $this->assertSame($activity, DB::table('purchase_request_activity')->where('purchase_request_id', $id)->count());
        $this->signIn($this->manager);
        $token = $this->work();
        $this->post('/relocations/work/'.$token, $this->relocationData() + ['action' => 'review'])->assertRedirect();
        DB::table('catalog_metadata')->insert(['kind' => 'storage_locations', 'record_id' => $this->destination, 'state' => 'inactive']);
        $before = DB::table('relocation_requests')->count();
        $this->post('/relocations/work/'.$token.'/submit')->assertSessionHasErrors('destination_location_id');
        $this->assertSame($before, DB::table('relocation_requests')->count());
    }
}
