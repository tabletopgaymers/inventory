<?php

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Math\BigRational;
use Brick\Math\RoundingMode;
use Illuminate\Validation\ValidationException;

/** Invoice arithmetic stays rational until the explicitly reconciled cent boundary. */
class PurchaseCosting
{
    /**
     * Parse a nonnegative USD amount as integer cents with up to two decimal places. A leading decimal point is accepted.
     *
     * Scope: Domain-specific (Purchase)
     * Task: Calculate purchase costs
     * @example app/Http/Controllers/PurchaseController.php
     */
    public static function money(mixed $value, string $field): int
    {
        if (is_string($value) && str_starts_with($value,'.')) $value='0'.$value;
        if ((! is_string($value) && ! is_int($value)) || ! preg_match('/\A\d{1,10}(?:\.\d{1,2})?\z/D', (string) $value)) {
            throw ValidationException::withMessages([$field => 'Enter a nonnegative USD amount with at most two decimal places.']);
        }

        return BigDecimal::of($value)->multipliedBy(100)->toScale(0, RoundingMode::Unnecessary)->toInt();
    }

    /**
     * Convert an exact cent amount to a two-decimal dollar string for display or further decimal arithmetic.
     *
     * Scope: Domain-specific (Purchase)
     * Task: Calculate purchase costs
     * @example app/Http/Controllers/PurchaseController.php
     */
    public static function dollars(int $cents): string
    {
        return (string) BigDecimal::of($cents)->dividedBy(100, 2, RoundingMode::Unnecessary);
    }

    /**
     * Split a charge across weighted invoice lines. Distribute residual cents by largest remainder and preserve input order for ties.
     *
     * @internal
     * @example app/Support/PurchaseCosting.php
     */
    public static function allocate(int $cents, array $weights, string $field): array
    {
        $total = BigRational::of(0);
        foreach ($weights as $weight) {
            $total = $total->plus($weight);
        }
        $shares = array_fill_keys(array_keys($weights), 0);
        if ($cents === 0) {
            return $shares;
        }
        if ($total->isZero()) {
            throw ValidationException::withMessages([$field => 'Positive charges need a positive allocation base. For shipping choose By quantity or correct the merchandise amounts.']);
        }
        $remainders = [];
        foreach ($weights as $key => $weight) {
            $exact = BigRational::of($weight)->multipliedBy($cents)->dividedBy($total);
            $shares[$key] = $exact->toScale(0, RoundingMode::Down)->toInt();
            $remainders[$key] = $exact->minus($shares[$key]);
        }
        // Stable sorting preserves input line order when fractional remainders tie.
        uasort($remainders, fn ($a, $b) => $b->compareTo($a));
        $remaining = $cents - array_sum($shares);
        foreach (array_keys($remainders) as $key) {
            if ($remaining-- <= 0) {
                break;
            }
            $shares[$key]++;
        }

        return $shares;
    }

    /**
     * Validate and price a purchase invoice with at least one line. Returns per-line charge allocations, acquisition amounts, and totals without posting inventory.
     *
     * Scope: Domain-specific (Purchase)
     * Task: Calculate purchase costs
     * @example app/Http/Controllers/PurchaseController.php
     */
    public function calculate(array $data): array
    {
        $input = $data['lines'] ?? [];
        if (! is_array($input) || $input === [] || count($input) > 1000) {
            throw ValidationException::withMessages(['lines' => 'Keep at least one material line, up to 1,000. Cancel a wholly missing purchase.']);
        }
        $lines = [];
        foreach ($input as $item => $line) {
            if (!is_array($line)) throw ValidationException::withMessages(['lines' => 'Enter invoice rows.']);
            $identity = PurchaseInvoiceLines::identity($item, $line);
            $id = $identity['line_key'];
            if ($identity['description'] === '') throw ValidationException::withMessages(['lines.'.$id.'.description' => 'Enter an item description.']);
            $quantity = RequestValues::quantity($line['quantity'] ?? null, 'lines.'.$item.'.quantity');
            if ($quantity === 0) {
                throw ValidationException::withMessages(['lines.'.$item.'.quantity' => 'Retained lines need positive individual units. Remove absent lines.']);
            }
            $cost = self::money($line['cost'] ?? '0', 'lines.'.$item.'.cost');
            $fee = self::money($line['fee'] ?? '0', 'lines.'.$item.'.fee');
            $unit = StockNumbers::cost((string) ($line['unit'] ?? '0'));
            $expected = BigDecimal::of($unit)->multipliedBy($quantity)->toScale(2, RoundingMode::HalfUp);
            if ((string) $expected !== self::dollars($cost)) {
                throw ValidationException::withMessages(['lines.'.$item.'.cost' => 'Cost does not equal quantity × the retained exact Unit, rounded to cents. Correct or explicitly recalculate this row.']);
            }
            $lines[$id] = $identity + ['quantity' => $quantity, 'unit' => $unit, 'cost' => $cost, 'fee' => $fee];
        }
        PurchaseInvoiceLines::assertUnique($lines);
        $charges = [];
        foreach (['other_fees', 'discount', 'tax', 'shipping'] as $field) {
            $charges[$field] = self::money($data[$field] ?? '0', $field);
        }
        $method = $data['shipping_method'] ?? 'line_total';
        if (! in_array($method, ['line_total', 'quantity'], true)) {
            throw ValidationException::withMessages(['shipping_method' => 'Choose By line total or By quantity.']);
        }
        $other = self::allocate($charges['other_fees'], array_fill_keys(array_keys($lines), 1), 'other_fees');
        $base = [];
        foreach ($lines as $id => $line) {
            $base[$id] = $line['cost'] + $line['fee'] + $other[$id];
        }
        if ($charges['discount'] > array_sum($base)) {
            throw ValidationException::withMessages(['discount' => 'Discount cannot exceed merchandise and fees.']);
        }
        $discount = self::allocate($charges['discount'], $base, 'discount');
        $discounted = [];
        $weights = [];
        foreach ($lines as $id => $line) {
            $discounted[$id] = $base[$id] - $discount[$id];
            $weights[$id] = $method === 'quantity' ? BigRational::of($line['quantity']) : ($base[$id] === 0 ? BigRational::of(0) : BigRational::of($line['cost'])->multipliedBy($discounted[$id])->dividedBy($base[$id]));
        }
        $tax = self::allocate($charges['tax'], $discounted, 'tax');
        $shipping = self::allocate($charges['shipping'], $weights, 'shipping');
        foreach ($lines as $id => &$line) {
            $line += ['other' => $other[$id], 'discount' => $discount[$id], 'tax' => $tax[$id], 'shipping' => $shipping[$id], 'acquisition' => $discounted[$id] + $tax[$id] + $shipping[$id]];
            $line['acquisition_unit'] = (string) BigDecimal::of(self::dollars($line['acquisition']))->dividedBy($line['quantity'], 12, RoundingMode::HalfUp);
        }
        unset($line);

        return ['lines' => $lines, 'charges' => $charges, 'shipping_method' => $method, 'total' => array_sum(array_column($lines, 'acquisition'))];
    }

    /**
     * Compute weighted unit cost from held stock and received acquisition value. The receipt-cost rule chooses when to use this arithmetic.
     *
     * @internal
     * @example app/Support/PurchaseCosting.php
     */
    public static function average(int $held, string $cost, int $received, int $acquisition): string
    {
        $amount = BigDecimal::of(self::dollars($acquisition));
        if ($held <= 0 || BigDecimal::of($cost)->isZero()) {
            return StockNumbers::cost((string) $amount->dividedBy($received, 12, RoundingMode::HalfUp));
        }

        return StockNumbers::cost((string) BigDecimal::of($cost)->multipliedBy($held)->plus($amount)->dividedBy($held + $received, 12, RoundingMode::HalfUp));
    }

    /**
     * Determine the unit cost at receipt from acquisition cents and positive received units. Use weighted averaging only for lower-cost storage receipts with existing positive stock and known cost.
     *
     * Scope: Domain-specific (Purchase)
     * Task: Calculate purchase costs
     * @example app/Support/PurchaseFulfillment.php
     */
    public static function receiptCost(int $held, string $cost, int $received, int $acquisition, bool $storage): string
    {
        $purchase=StockNumbers::cost((string)BigDecimal::of(self::dollars($acquisition))->dividedBy($received,12,RoundingMode::HalfUp));
        if (!$storage || $held<=0 || BigDecimal::of($cost)->isZero() || BigDecimal::of($purchase)->isGreaterThanOrEqualTo($cost)) return $purchase;
        return self::average($held,$cost,$received,$acquisition);
    }
}
