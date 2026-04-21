<?php

namespace App\Support\Pos\Print;

/**
 * @phpstan-type ReceiptLine array{qty:int,name:string,amount_minor:int}
 */
final readonly class ReceiptPreviewData
{
    /**
     * @param  list<array{qty:int,name:string,amount_minor:int}>  $lines
     */
    public function __construct(
        public string $intent,
        public string $shopName,
        public string $tableLabel,
        public array $lines,
        public int $subtotalMinor,
        public int $totalMinor,
        public string $printedAt,
    ) {}
}
