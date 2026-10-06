<?php

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Validation\ValidationException;

class StockNumbers
{
    public const LIMIT = 1000000000;

    public static function quantity(mixed $value, string $field, bool $blankZero = false): int
    {
        if ($blankZero && ($value === null || $value === '')) {
            return 0;
        }
        if ((! is_string($value) && ! is_int($value)) || ! preg_match('/\A[+-]?\d{1,10}\z/D', (string) $value)
            || abs((int) $value) > self::LIMIT) {
            throw ValidationException::withMessages([$field => 'Enter a whole number from −1,000,000,000 to 1,000,000,000.']);
        }

        return (int) $value;
    }

    public static function finalQuantity(mixed $set, mixed $adjust, string $field): int
    {
        $result = self::quantity($set, $field.'.set') + self::quantity($adjust, $field.'.adjust', true);

        return self::quantity($result, $field);
    }

    public static function cost(string $value): string
    {
        try {
            $decimal = BigDecimal::of($value)->toScale(12, RoundingMode::Unnecessary);
            if ($decimal->isNegative() || $decimal->isGreaterThan('999999999999.999999999999')) {
                throw new \InvalidArgumentException;
            }

            return (string) $decimal;
        } catch (\Throwable) {
            throw ValidationException::withMessages(['unit_cost' => 'Cost requires a nonnegative exact decimal with at most 12 integer and 12 decimal places.']);
        }
    }

    public static function displayCost(string $value): string
    {
        $decimal = BigDecimal::of(self::cost($value));

        return $decimal->isZero() ? 'n/a' : '$'.$decimal->toScale(3, RoundingMode::HalfUp);
    }
}
