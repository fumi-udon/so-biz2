<?php

namespace App\Support;

/**
 * Normalise les montants caisse (FR/TN : virgule décimale, points ou espaces milliers).
 * Retourne une chaîne castable en float avec au plus une décimale, ou null si vide/invalide.
 */
final class CaisseMoneyInputNormalizer
{
    public static function normalizeToMaxOneDecimal(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            if (! is_finite($value)) {
                return null;
            }

            return self::formatMaxOneDecimal(max(0.0, round($value, 1)));
        }

        $s = trim((string) $value);
        if ($s === '') {
            return null;
        }

        $s = preg_replace('/[\h\x{00A0}\x{202F}]+/u', '', $s) ?? '';
        if ($s === '') {
            return null;
        }

        $hasComma = str_contains($s, ',');
        $hasDot = str_contains($s, '.');

        if ($hasComma) {
            $lastComma = strrpos($s, ',');
            $intRaw = substr($s, 0, $lastComma);
            $fracRaw = substr($s, $lastComma + 1);
            $intRaw = str_replace(['.', ','], '', $intRaw);
            $intPart = preg_replace('/\D/', '', $intRaw) ?? '';
            $fracPart = preg_replace('/\D/', '', $fracRaw) ?? '';
        } elseif ($hasDot) {
            $lastDot = strrpos($s, '.');
            $intRaw = substr($s, 0, $lastDot);
            $fracRaw = substr($s, $lastDot + 1);
            $intRaw = str_replace('.', '', $intRaw);
            $intPart = preg_replace('/\D/', '', $intRaw) ?? '';
            $fracPart = preg_replace('/\D/', '', $fracRaw) ?? '';
        } else {
            $intPart = preg_replace('/\D/', '', $s) ?? '';
            $fracPart = '';
        }

        if ($intPart === '' && $fracPart === '') {
            return null;
        }

        $numStr = ($intPart !== '' ? $intPart : '0').'.'.($fracPart !== '' ? $fracPart : '0');
        if (! is_numeric($numStr)) {
            return null;
        }

        $f = (float) $numStr;
        if (! is_finite($f)) {
            return null;
        }
        $f = max(0.0, round($f, 1));

        return self::formatMaxOneDecimal($f);
    }

    private static function formatMaxOneDecimal(float $amount): string
    {
        $r = round($amount, 1);
        $scaled = (int) round($r * 10);
        if ($scaled % 10 === 0) {
            return (string) (int) round($r);
        }

        return number_format($r, 1, '.', '');
    }
}
