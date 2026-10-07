<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\EventValues;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class EventPresentationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $user = new class extends User
        {
            public function roles(): array
            {
                return ['manager'];
            }
        };
        $user->forceFill(['id' => -24, 'first_name' => 'Test', 'last_name' => 'Manager']);
        $this->actingAs($user);
        view()->share('errors', new ViewErrorBag);
    }

    public function test_print_report_escapes_identity_and_contains_only_distribution_columns(): void
    {
        $line = ['category' => '<script>sample</script>', 'collection' => 'Pronoun', 'item' => '=SUM(1,2)', 'sku' => 'EVT-A', 'distributed' => 750];
        $html = view('events.report', ['row' => (object) ['id' => 1, 'name' => 'Approved sample event'], 'data' => ['corrected' => true, 'finalized_at' => '2026-10-07T12:00:00Z'], 'report' => collect([$line]), 'all' => false])->render();
        $this->assertStringContainsString('&lt;script&gt;sample&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>sample</script>', $html);
        $this->assertStringContainsString('Distributed', $html);
        $this->assertStringNotContainsString('<th>Brought', $html);
        $this->assertStringNotContainsString('<th>Remaining', $html);
        $this->assertStringContainsString('event-report.css', $html);
        $this->assertStringContainsString('CDT', $html);
    }

    public function test_formula_text_is_safe_even_with_whitespace_and_ordinary_text_is_preserved(): void
    {
        foreach (['=SUM(1,2)', '+cmd', '-cmd', '@cmd', "\t =cmd", "\n+cmd"] as $value) {
            $this->assertSame("'".$value, EventValues::csv($value));
        }
        $this->assertSame('They/Them', EventValues::csv('They/Them'));
        $this->assertSame('0', EventValues::csv(0));
    }

    public function test_shared_form_has_labeled_blank_count_split_and_keyboard_native_controls(): void
    {
        $html = view('events.form', ['row' => (object) ['id' => 1, 'name' => 'Approved sample event'], 'mode' => 'counts', 'token' => '00000000-0000-4000-8000-000000000024', 'base' => '/events/1/work/counts',
            'draft' => ['values' => ['lines' => [1 => ['brought' => 1000, 'remaining' => '', 'allocations' => [['destination' => 'storage:1', 'quantity' => '']]]]]],
            'items' => collect([(object) ['id' => 1, 'name' => 'They/Them', 'sku' => 'EVT-A']]), 'destinations' => ['storage:1' => 'Boston MA']])->render();
        $this->assertStringContainsString('Counted remaining (blank if not counted)', $html);
        $this->assertStringContainsString('name="lines[1][remaining]" inputmode="numeric" value=""', $html);
        $this->assertStringContainsString('Add destination for They/Them', $html);
        $this->assertStringContainsString('Review irreversible finalization', $html);
        $this->assertStringContainsString('data-request-form', $html);
        $this->assertStringContainsString('/requests.js', $html);
        $this->assertStringContainsString('<label>Leftover destination<select', $html);
    }
}
