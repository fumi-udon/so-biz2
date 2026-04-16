<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetGuestLocale
{
    /** Locales supported by the guest-order UI. */
    private const SUPPORTED = ['fr', 'en'];

    /** Default locale when no match is found. */
    private const DEFAULT = 'fr';

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolveLocale($request);
        App::setLocale($locale);

        return $next($request);
    }

    private function resolveLocale(Request $request): string
    {
        $header = $request->header('Accept-Language', '');

        foreach ($this->parseAcceptLanguage($header) as $tag) {
            $primary = strtolower(explode('-', $tag)[0]);
            if (in_array($primary, self::SUPPORTED, true)) {
                return $primary;
            }
        }

        return self::DEFAULT;
    }

    /**
     * Parse Accept-Language header into an ordered list of language tags.
     *
     * @return list<string>
     */
    private function parseAcceptLanguage(string $header): array
    {
        if ($header === '') {
            return [];
        }

        $parts = array_map('trim', explode(',', $header));

        $weighted = [];
        foreach ($parts as $part) {
            if (preg_match('/^([a-zA-Z\-]+)(?:;q=([0-9.]+))?$/', trim($part), $m)) {
                $weighted[] = [
                    'tag' => $m[1],
                    'q'   => isset($m[2]) ? (float) $m[2] : 1.0,
                ];
            }
        }

        usort($weighted, static fn (array $a, array $b): int => $b['q'] <=> $a['q']);

        return array_column($weighted, 'tag');
    }
}
