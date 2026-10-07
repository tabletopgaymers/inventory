<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

class EventValues
{
    public static function destination(mixed $value, string $field, bool $optional = false): ?string
    {
        if ($optional && ($value === null || $value === '')) {
            return null;
        }
        if (! is_string($value) || ! preg_match('/\A(storage|event):([1-9]\d{0,17})\z/D', $value)) {
            throw ValidationException::withMessages([$field => 'Choose an eligible storage location or active event.']);
        }

        return $value;
    }

    public static function allocations(mixed $rows, string $field): array
    {
        if (! is_array($rows) || count($rows) > 1000) {
            throw ValidationException::withMessages([$field => 'Enter a bounded set of destination allocations.']);
        }
        $result = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                throw ValidationException::withMessages([$field => 'Each allocation needs a destination and quantity.']);
            }
            $key = self::destination($row['destination'] ?? null, $field, true);
            $quantity = RequestValues::quantity($row['quantity'] ?? '', $field, true);
            if ($key === null && $quantity === null) {
                continue;
            }
            if ($key === null) {
                throw ValidationException::withMessages([$field => 'Choose a destination for this allocation.']);
            }
            if (array_key_exists($key, $result)) {
                throw ValidationException::withMessages([$field => 'Use each destination once per item.']);
            }
            $result[$key] = $quantity;
        }
        ksort($result);

        return $result;
    }

    public static function counts(mixed $remaining, array $allocations, int $brought, string $field, bool $complete): ?int
    {
        $count = RequestValues::quantity($remaining, $field, true);
        if ($complete) {
            if ($count === null) {
                throw ValidationException::withMessages([$field => 'Count every event item, including explicit zero.']);
            }
            if ($count > $brought) {
                throw ValidationException::withMessages([$field => 'Remaining exceeds brought. Record a missed delivery before finalizing; progress may still be saved.']);
            }
            if (in_array(null, $allocations, true) || array_sum($allocations) !== $count) {
                throw ValidationException::withMessages([$field => 'Complete destination allocations must equal remaining; no remainder is assigned automatically.']);
            }
        }

        return $count;
    }

    /** Prevent user text becoming spreadsheet formula content, including whitespace prefixes. */
    public static function csv(mixed $value): string
    {
        $text = (string) $value;

        return preg_match('/\A\s*[=+@-]/u', $text) ? "'".$text : $text;
    }
}
