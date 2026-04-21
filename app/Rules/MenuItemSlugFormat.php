<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Slug: optional empty, otherwise lowercase a-z0-9 and hyphens.
 *
 * Implemented as a {@see ValidationRule} (not a closure) because Filament evaluates
 * closure `rules` like form callbacks in some paths, causing
 * "[$attribute] was unresolvable" (BindingResolutionException).
 */
final class MenuItemSlugFormat implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }
        if (! is_string($value) || ! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value)) {
            $fail('スラッグは英小文字・数字・ハイフンのみ使用できます。');
        }
    }
}
