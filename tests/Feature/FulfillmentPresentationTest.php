<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class FulfillmentPresentationTest extends TestCase
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
        $user->forceFill(['id' => -22, 'first_name' => 'Test', 'last_name' => 'Manager']);
        $this->actingAs($user);
        view()->share('errors', new ViewErrorBag);
    }

    private function choices(): array
    {
        return ['items' => collect([(object) ['id' => 1, 'name' => 'They/Them', 'sku' => 'FUL-A', 'collection_id' => 1, 'state' => 'active']]),
            'collections' => collect([(object) ['id' => 1, 'name' => 'Pronoun', 'category_name' => 'Ribbon']]),
            'suppliers' => collect([(object) ['id' => 1, 'name' => 'Approved Sample Supplier', 'state' => 'active']]),
            'locations' => collect([(object) ['id' => 1, 'name' => 'Boston MA', 'state' => 'active']])];
    }

    public function test_purchase_filters_include_active_orders_and_final_receipts(): void
    {
        $html = view('requests.index', ['kind' => 'purchase', 'rows' => collect()])->render();
        foreach (['Ordered', 'Shipped', 'Received', 'Cancelled'] as $status) {
            $this->assertStringContainsString('data-status-filter value="'.$status.'"', $html);
        }
        $this->assertMatchesRegularExpression('/value="Ordered"\s+checked/', $html);
        $this->assertMatchesRegularExpression('/value="Shipped"\s+checked/', $html);
        $this->assertDoesNotMatchRegularExpression('/value="Received"\s+checked/', $html);
    }

    public function test_shipment_counts_group_catalog_identity_and_review_formats_calendar_date(): void
    {
        $lines = [1 => ['sent' => '15']];
        $common = $this->choices() + ['kind' => 'relocation', 'mode' => 'shipment', 'row' => (object) ['id' => 1, 'title' => 'Approved sample relocation', 'status' => 'Requested', 'source_location_id' => 1],
            'token' => '00000000-0000-4000-8000-000000000022', 'base' => '/relocations/1'];
        $html = view('requests.fulfillment-edit', $common + ['draft' => ['values' => ['lines' => $lines, 'shipped_date' => '2026-10-07']], 'readOnly' => false, 'relocationLines' => collect($lines), 'requestedQuantities' => [1 => 900]])->render();
        $this->assertStringContainsString('Ribbon: Pronoun', $html);
        $this->assertStringContainsString('aria-label="Sent for They/Them · FUL-A"', $html);
        $this->assertStringContainsString('900', $html);
        $this->assertStringContainsString('Enter actual sent counts.', $html);
        $this->assertStringNotContainsString('Counts are cumulative.', $html);
        $review = view('requests.fulfillment-review', $common + ['review' => ['lines' => $lines, 'date' => '2026-10-07']])->render();
        $this->assertStringContainsString('Shipped date: 07 Oct 2026', $review);
        $this->assertStringNotContainsString('Shipped date: 2026-10-07', $review);
    }
}
