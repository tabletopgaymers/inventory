<?php

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Math\BigRational;
use Brick\Math\RoundingMode;
use Illuminate\Validation\ValidationException;

/** Invoice arithmetic stays rational until the explicitly reconciled cent boundary. */
class PurchaseCosting
{
    public static function money(mixed $value, string $field): int
    {
        if ((! is_string($value) && ! is_int($value)) || ! preg_match('/\A\d{1,10}(?:\.\d{1,2})?\z/D', (string) $value)) {
            throw ValidationException::withMessages([$field => 'Enter a nonnegative USD amount with at most two decimal places.']);
        }

        return BigDecimal::of($value)->multipliedBy(100)->toScale(0, RoundingMode::Unnecessary)->toInt();
    }

    public static function dollars(int $cents): string
    {
        return (string) BigDecimal::of($cents)->dividedBy(100, 2, RoundingMode::Unnecessary);
    }

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

    public function calculate(array $data): array
    {
        $input = $data['lines'] ?? [];
        if (! is_array($input) || $input === [] || count($input) > 1000) {
            throw ValidationException::withMessages(['lines' => 'Keep at least one catalog line, up to 1,000. Remove absent items before final receipt; cancel a wholly missing purchase.']);
        }
        $lines = [];
        foreach ($input as $item => $line) {
            $id = RequestValues::id($item, 'lines');
            if (! $id || ! is_array($line)) {
                throw ValidationException::withMessages(['lines' => 'Choose existing catalog items.']);
            }
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
            $lines[$id] = ['item_id' => $id, 'quantity' => $quantity, 'unit' => $unit, 'cost' => $cost, 'fee' => $fee];
        }
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

    public static function average(int $held, string $cost, int $received, int $acquisition): string
    {
        $amount = BigDecimal::of(self::dollars($acquisition));
        if ($held <= 0 || BigDecimal::of($cost)->isZero()) {
            return StockNumbers::cost((string) $amount->dividedBy($received, 12, RoundingMode::HalfUp));
        }

        return StockNumbers::cost((string) BigDecimal::of($cost)->multipliedBy($held)->plus($amount)->dividedBy($held + $received, 12, RoundingMode::HalfUp));
    }
}
