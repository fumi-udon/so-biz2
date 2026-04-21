<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Option id (slug): optional empty, otherwise a-z0-9 and internal hyphens.
 *
 * @see MenuItemSlugFormat for why this is a class, not a closure
 */
final class MenuItemOptionIdFormat implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }
        if (! is_string($value) || ! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value)) {
            $fail('ID は英小文字・数字・ハイフンのみです。');
        }
    }
}
