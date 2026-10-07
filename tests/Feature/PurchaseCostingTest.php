<?php

namespace Tests\Feature;

use App\Support\PurchaseCosting;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PurchaseCostingTest extends TestCase
{
    private function invoice(string $method = 'line_total'): array
    {
        return ['lines' => [1 => ['quantity' => '5200', 'unit' => '0.173076923077', 'cost' => '900', 'fee' => '0'], 2 => ['quantity' => '3100', 'unit' => '0.193548387097', 'cost' => '600', 'fee' => '0']],
            'discount' => '236.25', 'tax' => '88.46', 'shipping' => '124.16', 'shipping_method' => $method];
    }

    public function test_both_approved_shipping_examples_and_exact_total(): void
    {
        foreach (['line_total' => [88583, 59054], 'quantity' => [88912, 58725]] as $method => $expected) {
            $result = app(PurchaseCosting::class)->calculate($this->invoice($method));
            $this->assertSame($expected, array_column($result['lines'], 'acquisition'));
            $this->assertSame(147637, $result['total']);
        }
        $invoice = $this->invoice();
        $invoice['shipping'] = '0';
        $this->assertSame([81133, 54088], array_column(app(PurchaseCosting::class)->calculate($invoice)['lines'], 'acquisition'));
    }

    public function test_cent_leftovers_and_discounted_merchandise_exclude_fees_from_shipping(): void
    {
        $this->assertSame([1 => 501, 2 => 500], PurchaseCosting::allocate(1001, [1 => 1, 2 => 1], 'fees'));
        $result = app(PurchaseCosting::class)->calculate(['lines' => [2 => ['quantity' => '1', 'unit' => '1', 'cost' => '1', 'fee' => '99'], 1 => ['quantity' => '2', 'unit' => '0.5', 'cost' => '1', 'fee' => '0']], 'shipping' => '1.01']);
        $this->assertSame([51, 50], array_column($result['lines'], 'shipping'));
        $this->assertSame(10201, $result['total']);
    }

    public function test_zero_weight_shipping_requires_explicit_quantity_and_free_units_remain_valid(): void
    {
        $free = ['lines' => [1 => ['quantity' => '5', 'unit' => '0', 'cost' => '0', 'fee' => '0']], 'shipping' => '2.00'];
        try {
            app(PurchaseCosting::class)->calculate($free);
            $this->fail('Zero merchandise cannot silently select quantity shipping.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('shipping', $error->errors());
        }
        $this->assertSame(200, app(PurchaseCosting::class)->calculate($free + ['shipping_method' => 'quantity'])['total']);
        $free['shipping'] = '0';
        $this->assertSame(0, app(PurchaseCosting::class)->calculate($free)['total']);
    }

    public function test_inconsistent_unit_cost_zero_tax_base_discount_and_absent_lines_are_rejected(): void
    {
        foreach ([['lines' => [1 => ['quantity' => '5200', 'unit' => '0.2', 'cost' => '900']]],
            ['lines' => [1 => ['quantity' => '1', 'unit' => '0', 'cost' => '0']], 'tax' => '1'],
            ['lines' => [1 => ['quantity' => '1', 'unit' => '1', 'cost' => '1']], 'discount' => '1.01'],
            ['lines' => [1 => ['quantity' => '0', 'unit' => '0', 'cost' => '0']]], ['lines' => []],
        ] as $data) {
            try {
                app(PurchaseCosting::class)->calculate($data);
                $this->fail('Invalid invoice accepted.');
            } catch (ValidationException $error) {
                $this->assertNotEmpty($error->errors());
            }
        }
    }

    public function test_current_average_unknown_nonpositive_and_precision_branches(): void
    {
        $this->assertSame('0.175664516129', PurchaseCosting::average(1000, '0.2', 5200, 88912));
        $this->assertSame('0.189435483871', PurchaseCosting::average(500, '0', 3100, 58725));
        $this->assertSame('0.189435483871', PurchaseCosting::average(-500, '0.2', 3100, 58725));
        $this->assertSame('0.000000000000', PurchaseCosting::average(0, '0', 10, 0));
        $this->assertSame('0.133333333334', PurchaseCosting::average(2, '0.100000000001', 1, 20));
    }
}
