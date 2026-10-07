<?php

namespace Tests\Feature;

use App\Support\EventWorkflow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\EventFixture;
use Tests\TestCase;

class EventHttpTest extends TestCase
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

    public function test_planning_saves_partial_supply_and_retains_validation_draft_without_posting(): void
    {
        $before = $this->eventInventory();
        $token = $this->get('/events/new')->assertOk()->viewData('token');
        $this->post('/events/new', ['work' => $token, 'action' => 'save', 'name' => 'Approved sample event', 'supplies' => [['item_id' => $this->item, 'source' => '', 'quantity' => '']]])->assertRedirect();
        $this->assertSame($before, $this->eventInventory());
        $id = (int) DB::table('event_workflows')->value('id');
        $this->assertNull(app(EventWorkflow::class)->data($id)['supplies'][0]['quantity']);
        $page = $this->get('/events/'.$id.'/work/plan')->assertOk();
        $token = $page->viewData('token');
        $this->post('/events/'.$id.'/work/plan', ['work' => $token, 'action' => 'save', 'name' => '', 'notes' => 'Retained sample notes'])->assertSessionHasErrors('name')->assertRedirect('/events/'.$id.'/work/plan?work='.$token);
        $this->get('/events/'.$id.'/work/plan?work='.$token)->assertOk()->assertSee('Retained sample notes');
        $this->assertSame($before, $this->eventInventory());
    }

    public function test_activation_http_review_cancel_confirm_and_repeat_are_one_time(): void
    {
        $id = $this->eventPlan();
        $before = $this->eventInventory();
        $base = '/events/'.$id.'/work/activate';
        $token = $this->get($base)->assertOk()->viewData('token');
        $this->post($base, ['work' => $token, 'action' => 'review', 'actual_date' => '2020-02-29'])->assertRedirect($base.'/review/'.$token);
        $this->get($base.'/review/'.$token)->assertOk()->assertSee('Confirm posts these actual supplies once.');
        $this->get('/events/'.$id)->assertOk();
        $this->assertSame($before, $this->eventInventory());
        $this->post($base.'/confirm/'.$token, ['_deliberate' => '1'])->assertRedirect('/events/'.$id);
        $after = $this->eventInventory();
        $this->post($base.'/confirm/'.$token, ['_deliberate' => '1'])->assertRedirect('/events/'.$id);
        $this->assertSame($after, $this->eventInventory());
        $this->assertSame(1, DB::table('event_operations')->where('action', 'activate')->count());
    }

    public function test_shared_counts_split_controls_and_stale_revision_keep_user_values(): void
    {
        $id = $this->activeEvent();
        $base = '/events/'.$id.'/work/counts';
        $token = $this->get($base)->assertOk()->viewData('token');
        $data = $this->eventCounts(250) + ['work' => $token, 'action' => 'split', 'split_item' => $this->item];
        $this->post($base, $data)->assertRedirect($base.'?work='.$token);
        $page = $this->get($base.'?work='.$token)->assertOk();
        $this->assertCount(2, $page->viewData('draft')['values']['lines'][$this->item]['allocations']);
        $before = $this->eventInventory();
        app(EventWorkflow::class)->work($this->other->id, $id, 2, 'counts', $this->eventCounts(0), 'save', (string) Str::uuid());
        $this->post($base, $this->eventCounts(250) + ['work' => $token, 'action' => 'save'])->assertSessionHasErrors('revision');
        $this->get($base.'?work='.$token)->assertOk()->assertSee('value="250"', false);
        $this->assertSame($before, $this->eventInventory());
        $this->assertSame('0', app(EventWorkflow::class)->data($id)['lines'][$this->item]['remaining']);
    }

    public function test_http_over_brought_review_retains_progress_and_finalization_confirmation_is_irreversible(): void
    {
        $id = $this->activeEvent();
        $base = '/events/'.$id.'/work/counts';
        $token = $this->get($base)->assertOk()->viewData('token');
        $this->post($base, $this->eventCounts(1100) + ['work' => $token, 'action' => 'review'])->assertSessionHasErrors('lines.'.$this->item.'.remaining');
        $this->get($base.'?work='.$token)->assertOk()->assertSee('value="1100"', false);
        $this->post($base, $this->eventCounts(250) + ['work' => $token, 'action' => 'review'])->assertRedirect($base.'/review/'.$token);
        $this->get($base.'/review/'.$token)->assertOk()->assertSee('Finalization is irreversible.')->assertSee('750');
        $this->post($base.'/confirm/'.$token)->assertRedirect('/events/'.$id);
        $this->get('/events/'.$id)->assertOk()->assertSee('Original posted counts');
        $this->get($base)->assertStatus(409);
        $this->get('/inventory/items/'.$this->item)->assertOk()->assertSee('/events/'.$id, false)->assertSee('750');
    }

    public function test_viewers_can_read_but_cannot_edit_and_work_tokens_are_actor_bound(): void
    {
        $id = $this->activeEvent();
        $base = '/events/'.$id.'/work/counts';
        $token = $this->get($base)->assertOk()->viewData('token');
        $this->signInEvent($this->other);
        $this->get($base.'?work='.$token)->assertNotFound();
        DB::table('users')->where('id', $this->basic->id)->update(['contact_attested' => false]);
        $this->signInEvent($this->basic);
        $this->get('/events')->assertOk()->assertDontSee('Create event');
        $this->get('/events/'.$id)->assertOk()->assertDontSee('Enter counts / finalize');
        $this->get($base)->assertForbidden();
        $this->post($base, $this->eventCounts() + ['work' => $token, 'action' => 'save'])->assertForbidden();
        $this->signInEvent($this->manager);
        $this->post($base, $this->eventCounts() + ['work' => $token, 'action' => 'save', 'owner_id' => $this->basic->id])->assertSessionHasErrors('owner_id');
        DB::table('users')->where('id', $this->manager->id)->update(['enabled' => false]);
        $this->get('/events')->assertRedirect('/login');
    }

    public function test_provisional_report_refusal_and_corrected_print_csv_escape_and_leave_stock_unchanged(): void
    {
        $id = $this->activeEvent();
        $this->get('/events/'.$id.'/report')->assertStatus(409);
        app(EventWorkflow::class)->work($this->manager->id, $id, 2, 'finalize', $this->eventCounts(), 'confirm', (string) Str::uuid());
        $before = $this->eventInventory();
        $base = '/events/'.$id.'/work/correction';
        $token = $this->get($base)->assertOk()->viewData('token');
        $data = ['work' => $token, 'action' => 'review', 'lines' => [$this->item => ['brought' => '1100', 'remaining' => '300']], 'explanation' => '<script>sample</script>'];
        $this->post($base, $data)->assertRedirect($base.'/review/'.$token);
        $this->get($base.'/review/'.$token)->assertOk()->assertSee('800')->assertDontSee('<script>sample</script>', false);
        $this->post($base.'/confirm/'.$token)->assertRedirect('/events/'.$id);
        $this->get('/events/'.$id.'/report')->assertOk()->assertSee('Corrected report')->assertSee('800')->assertSee('Download CSV')->assertSee('Print report')->assertDontSee('Brought');
        $csv = $this->get('/events/'.$id.'/report?format=csv')->assertOk()->streamedContent();
        $this->assertStringContainsString('Corrected report', $csv);
        $this->assertStringContainsString('800', $csv);
        $this->assertStringNotContainsString('Brought', $csv);
        $this->get('/events/'.$id)->assertOk()->assertSee('&lt;script&gt;sample&lt;/script&gt;', false);
        $this->assertSame($before, $this->eventInventory());
    }

    public function test_csv_formula_identity_zero_filter_and_all_items_match_print_report_without_stock_changes(): void
    {
        DB::table('items')->where('id', $this->item)->update(['name' => '=SUM(1,2)']);
        $id = $this->activeEvent();
        app(EventWorkflow::class)->work($this->manager->id, $id, 2, 'delivery', ['supplies' => [['item_id' => $this->second, 'source' => 'external', 'quantity' => 10]]], 'confirm', (string) Str::uuid());
        $counts = $this->eventCounts();
        $counts['lines'][$this->second] = ['remaining' => '10', 'allocations' => [['destination' => 'storage:'.$this->source, 'quantity' => '10']]];
        app(EventWorkflow::class)->work($this->manager->id, $id, 3, 'finalize', $counts, 'confirm', (string) Str::uuid());
        $before = $this->eventInventory();
        $this->get('/events/'.$id.'/report')->assertOk()->assertSee('=SUM(1,2)')->assertDontSee('EHTTP-B');
        $this->get('/events/'.$id.'/report?all=1')->assertOk()->assertSee('EHTTP-B');
        $csv = $this->get('/events/'.$id.'/report?format=csv')->assertOk()->streamedContent();
        $this->assertStringContainsString("'=SUM(1,2)", $csv);
        $this->assertStringNotContainsString('EHTTP-B', $csv);
        $all = $this->get('/events/'.$id.'/report?format=csv&all=1')->assertOk()->streamedContent();
        $this->assertStringContainsString('EHTTP-B,0', $all);
        $this->assertSame($before, $this->eventInventory());
    }
}
