<?php

namespace App\Support\Pos\Print;

trait AsciiPrintNormalizer
{
    protected function toAsciiUpper(string $text): string
    {
        $value = trim($text);

        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $ascii = $converted !== false ? $converted : $value;

        $ascii = preg_replace('/[^\x20-\x7E]/', '', $ascii) ?? '';
        $ascii = preg_replace('/\s+/', ' ', $ascii) ?? '';

        return strtoupper(trim($ascii));
    }
}
