<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\BaselineProbe;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NavigationTest extends TestCase
{
    private const DESTINATIONS = ['/catalog', '/inventory', '/purchases', '/relocations', '/events', '/inventory/item-history', '/inventory/location-counts'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertTrue(app(BaselineProbe::class)->inspect()['ready']);
        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        parent::tearDown();
    }

    public function test_direct_planned_destinations_require_a_current_application_session(): void
    {
        foreach (self::DESTINATIONS as $path) {
            $this->get($path)->assertRedirect('/login');
        }
        $user = User::create(['first_name' => 'Test', 'last_name' => 'Viewer']);
        $this->actingAs($user)->withSession(['authentication' => [
            'started' => now()->timestamp, 'activity' => now()->timestamp, 'remember' => false,
        ]])->get('/inventory')->assertOk();
        $user->update(['enabled' => false]);
        $this->get('/inventory/item-history')->assertRedirect('/login');
    }

    public function test_basic_viewer_can_find_tasks_without_transaction_controls_or_user_mutation(): void
    {
        $user = User::create(['first_name' => 'Test', 'last_name' => 'Viewer']);
        DB::table('user_roles')->insert(['user_id' => $user->id, 'role' => 'basic']);
        $before = $user->fresh()->getAttributes();
        $this->actingAs($user)->withSession(['authentication' => [
            'started' => now()->timestamp, 'activity' => now()->timestamp, 'remember' => false,
        ]]);
        $this->get('/')->assertOk()->assertSee('request supplies from storage')
            ->assertSee('request supplies to purchase')->assertSee('Location Counts');
        foreach (self::DESTINATIONS as $path) {
            if ($path === '/inventory/location-counts') {
                $this->get($path)->assertOk()->assertSee('Personal search name')->assertDontSee('Reconcile Inventory');
                $this->post($path)->assertSessionHasErrors('action');

                continue;
            }
            if ($path === '/inventory/item-history') {
                $this->get($path)->assertOk()->assertSee('Item &amp; History', false)->assertDontSee('Workflow preview')->assertDontSee('type="number"', false);
                $this->post($path)->assertStatus(405);

                continue;
            }
            if (in_array($path, ['/catalog', '/inventory'], true)) {
                $this->get($path)->assertOk()->assertDontSee('Workflow preview')->assertDontSee('>Planned<', false);
                $this->post($path)->assertStatus(405);

                continue;
            }
            $this->get($path)->assertOk()->assertSee('Planned')->assertSee('Intended actions')
                ->assertSee('Related destinations')->assertSee('separate demonstration with sample data')
                ->assertSee('target="_blank"', false)->assertSee('rel="noopener noreferrer"', false)
                ->assertDontSee('action="'.$path.'"', false)->assertDontSee('type="number"', false);
            $this->post($path)->assertStatus(405);
        }
        $this->get('/inventory')->assertSee('aria-current="page">Inventory', false);
        $this->get('/users')->assertOk()->assertDontSee('Workflow preview');
        $this->get('/profile')->assertOk()->assertSee('Organizational contact email')->assertDontSee('Workflow preview');
        $this->assertEquals($before, $user->fresh()->getAttributes());
        $this->assertSame(['basic'], $user->fresh()->roles());
    }
}
