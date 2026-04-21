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
        /**
         * 画面・XML に載せる「このレシートを生成した日時」（複写ではコピー生成時刻）。
         */
        public string $printedAt,
        /**
         * 精算が確定した日時（Settlement.settled_at）。複写・精算済み FACTURE で表示。
         */
        public ?string $originalSettledAt = null,
    ) {}
}
