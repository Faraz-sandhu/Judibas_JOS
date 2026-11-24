<?php

namespace App\Modules\Pms\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class NameWordLimit implements ValidationRule
{
    public function __construct(private ?string $existing = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value)) return;
        $normalize = fn ($text) => preg_replace('/\s+/u', ' ', trim((string) $text));
        $name = $normalize($value);
        if ($this->existing !== null && $name === $normalize($this->existing)) return;
        if (count(preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY)) > 12) {
            $fail('The name may contain no more than 12 words. Put additional details in the description.');
        }
    }
}
