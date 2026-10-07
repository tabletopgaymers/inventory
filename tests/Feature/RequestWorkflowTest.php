<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\BaselineProbe;
use App\Support\PurchaseRequests;
use App\Support\RelocationRequests;
use App\Support\RequestCatalog;
use App\Support\RequestPreparation;
use App\Support\RequestValues;
use Dom\HTMLDocument;
use Dom\XPath;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
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

    public function test_admitted_own_submission_without_contact_or_elevated_role_preserves_management_boundaries(): void
    {
        config(['services.microsoft.tenant' => '11111111-1111-1111-1111-111111111111']);
        foreach ([$this->basic, $this->other, $this->manager] as $user) {
            $user->update(['contact_email' => null, 'contact_attested' => false]);
            DB::table('external_identities')->insert(['user_id' => $user->id, 'provider' => 'microsoft', 'tenant_id' => config('services.microsoft.tenant'), 'object_id' => (string) Str::uuid()]);
            $this->signIn($user);
            $id = $this->purchase($user);
            $this->get('/purchases/'.$id)->assertOk()->assertSee('Submit Request');
            $this->post('/purchases/'.$id.'/transition', ['action' => 'submit', 'revision' => 1])->assertRedirect('/purchases/'.$id);
            $this->assertSame('Request', DB::table('purchase_requests')->where('id', $id)->value('status'));
            $this->post('/purchases/'.$id.'/transition', ['action' => 'return', 'revision' => 2])->assertForbidden();
            $this->post('/purchases/'.$id.'/preparation', ['revision' => 2])->assertForbidden();
            $token = $this->work();
            $this->post('/relocations/work/'.$token, $this->relocationData() + ['action' => 'review'])->assertRedirect();
            $this->get('/relocations/work/'.$token.'/review')->assertOk()->assertSee('>Submit request<', false);
            $this->post('/relocations/work/'.$token.'/submit')->assertRedirect();
            $relocation = (int) session('relocation_work.'.$token.'.result');
            $this->assertSame('Requested', DB::table('relocation_requests')->where('id', $relocation)->value('status'));
            $this->post('/relocations/'.$relocation.'/fulfillment', ['revision' => 1])->assertForbidden();
        }
        $id = $this->purchase($this->basic);
        $this->signIn($this->other);
        $this->get('/purchases/'.$id)->assertOk()->assertDontSee('Submit Request');
        $this->post('/purchases/'.$id.'/transition', ['action' => 'submit', 'revision' => 1])->assertForbidden();
        $this->signIn($this->basic);
        DB::table('external_identities')->where('user_id', $this->basic->id)->update(['tenant_id' => '22222222-2222-2222-2222-222222222222']);
        $this->post('/purchases/'.$id.'/transition', ['action' => 'submit', 'revision' => 1])->assertForbidden();
        $this->basic->update(['enabled' => false]);
        $this->post('/purchases/'.$id.'/transition', ['action' => 'submit', 'revision' => 1])->assertRedirect('/login');
        try {
            app(PurchaseRequests::class)->transition($this->basic->id, $id, 1, 'submit');
            $this->fail('Disabled direct service submission accepted.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $this->assertSame('Draft', DB::table('purchase_requests')->where('id', $id)->value('status'));
    }

    public function test_relocation_required_draft_locations_default_central_and_legacy_recovery(): void
    {
        $central = DB::table('storage_locations')->where('is_central', true)->value('id') ?? DB::table('storage_locations')->insertGetId(['name' => 'Central', 'is_central' => true]);
        $token = $this->work();
        $this->assertSame((int) $central, (int) session('relocation_work.'.$token.'.source_location_id'));
        $this->post('/relocations/work/'.$token, ['action' => 'search', 'search' => 'Pronoun'])->assertRedirect();
        foreach (['save', 'review'] as $action) {
            $this->post('/relocations/work/'.$token, ['action' => $action, 'title' => 'Retained title', 'source_location_id' => $this->source])->assertSessionHasErrors('destination_location_id')->assertSessionHasInput('title', 'Retained title');
        }
        $this->assertSame(0, DB::table('relocation_requests')->where('owner_id', $this->basic->id)->count());
        try {
            app(RelocationRequests::class)->save($this->basic->id, null, null, ['title' => 'Missing Source', 'destination_location_id' => $this->destination]);
            $this->fail('Missing Source saved.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('source_location_id', $e->errors());
        }
        DB::table('catalog_metadata')->updateOrInsert(['kind' => 'storage_locations', 'record_id' => $central], ['state' => 'inactive']);
        $token = $this->work();
        $this->assertNull(session('relocation_work.'.$token.'.source_location_id'));
        $this->get('/relocations/work/'.$token)->assertOk()->assertSee('Central is missing or ineligible')->assertDontSee('Not selected');
        $legacy = DB::table('relocation_requests')->insertGetId(['owner_id' => $this->basic->id, 'title' => 'Legacy incomplete', 'status' => 'Draft', 'revision' => 1]);
        $edit = $this->get('/relocations/'.$legacy.'/edit')->assertRedirect();
        $token = basename($edit->headers->get('Location'));
        $this->assertNull(session('relocation_work.'.$token.'.source_location_id'));
        $this->post('/relocations/work/'.$token, ['action' => 'save', 'title' => 'Legacy incomplete'])->assertSessionHasErrors('source_location_id');
        $this->assertNull(DB::table('relocation_requests')->where('id', $legacy)->value('source_location_id'));
        $this->post('/relocations/work/'.$token, $this->relocationData() + ['action' => 'save'])->assertRedirect('/relocations/'.$legacy);
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

    public function test_relocation_unknown_locations_are_independent_of_known_zero(): void
    {
        $catalog = app(RequestCatalog::class);
        foreach ([[null, null, null, null], [$this->source, null, 17, null], [null, $this->destination, null, -5], [$this->source, $this->destination, 17, -5]] as [$source, $destination, $expectedSource, $expectedDestination]) {
            $rows = $catalog->rows([$this->item, $this->second], $source, $destination);
            $item = collect($rows)->firstWhere('id', $this->item);
            $this->assertSame($expectedSource, $item['source']);
            $this->assertSame($expectedDestination, $item['destination']);
            $zero = collect($rows)->firstWhere('id', $this->second);
            $this->assertSame($source === null ? null : 0, $zero['source']);
            $this->assertSame($destination === null ? null : 0, $zero['destination']);
            $html = view('requests.relocation-rows', ['rows' => [$item], 'quantities' => [$this->item => 2]])->render();
            $this->assertStringContainsString('data-source="'.($expectedSource ?? '').'"', $html);
            $this->assertStringContainsString('data-destination="'.($expectedDestination ?? '').'"', $html);
            $this->assertStringContainsString('<span data-source-after>'.($expectedSource === null ? 'Unknown' : $expectedSource - 2).'</span>', $html);
            $this->assertStringContainsString('<span data-destination-after>'.($expectedDestination === null ? 'Unknown' : $expectedDestination + 2).'</span>', $html);
        }
    }

    public function test_saved_preparation_keeps_duplicate_item_names_distinct(): void
    {
        DB::table('items')->where('id', $this->second)->update(['name' => 'They/Them']);
        $id = $this->purchase($this->procurement);
        app(PurchaseRequests::class)->prepare($this->procurement->id, $id, 1, ['lines' => [
            $this->item => ['quantity' => '2', 'estimate' => '0.123456789012', 'note' => ''],
            $this->second => ['quantity' => '0', 'estimate' => '', 'note' => ''],
        ]]);
        $this->signIn($this->procurement);
        $page = $this->get('/purchases/'.$id.'/preparation')->assertOk();
        foreach (['REQ-A', 'REQ-B'] as $sku) {
            $page->assertSee('Quantity for They/Them · '.$sku)->assertSee('Remove They/Them · '.$sku);
        }
        $page->assertSee('0.123456789012')->assertSee('value="0"', false);
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
        $worksheet = $this->get('/relocations/'.$id.'/worksheet')->assertOk()->assertSee('Sent');
        $document = HTMLDocument::createFromString($worksheet->getContent());
        $xpath = new XPath($document);
        $xpath->registerNamespace('html', 'http://www.w3.org/1999/xhtml');
        $this->assertCount(2, $xpath->query('//html:tbody/html:tr[@data-item-id]'));
        foreach ([$this->item, $this->second] as $itemId) {
            $itemRows = $xpath->query('//html:tbody/html:tr[@data-item-id="'.$itemId.'"]');
            $this->assertCount(1, $itemRows);
            $this->assertCount(4, $xpath->query('html:td', $itemRows->item(0)));
            $sentCells = $xpath->query('html:td[last()]', $itemRows->item(0));
            $this->assertCount(1, $sentCells);
            $this->assertSame('packing-sent', $sentCells->item(0)->getAttribute('class'));
            $this->assertSame(0, $sentCells->item(0)->childElementCount);
            $this->assertSame('', trim(str_replace("\u{00A0}", ' ', $sentCells->item(0)->textContent)));
        }
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
        $copyData = ['title' => 'Independent copied Draft', 'lines' => $work['lines'], 'source_location_id' => $this->source, 'destination_location_id' => $this->destination];
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
        // Legacy incomplete records remain readable; new saves require locations.
        $id = DB::table('relocation_requests')->insertGetId(['owner_id' => $this->basic->id, 'title' => 'Incomplete Draft', 'status' => 'Draft', 'revision' => 1, 'created_at' => now('UTC'), 'updated_at' => now('UTC')]);
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
