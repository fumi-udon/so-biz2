<?php

namespace App\Data\Pos;

use App\Domains\Pos\Tables\TableCategory;
use App\Domains\Pos\Tables\TableUiStatus;
use Carbon\CarbonImmutable;

/**
 * V4: docs/technical_contract_v4.md §2.1
 *
 * Phase 2: category / uiStatus をドメインロジックで確定させた結果を保持する。
 * UI 層はこの DTO（または toArray() 版）を「読むだけ」のステートレスとする。
 */
final readonly class TableTileAggregate
{
    public function __construct(
        public int $restaurantTableId,
        public string $restaurantTableName,
        /** Active session’s staff_name (賄い 100–104); null if unset or N/A. */
        public ?string $activeSessionStaffName,
        public ?int $activeTableSessionId,
        public int $unackedPlacedPosOrderCount,
        public bool $unackedPlacedLineExists,
        public ?CarbonImmutable $oldestRelevantPlacedAt,
        public bool $additionOrCheckoutSignalActive,
        public int $relevantPosOrderCount,
        public int $sessionTotalMinor,
        public ?TableCategory $category,
        public TableUiStatus $uiStatus,
        public ?CarbonImmutable $lastAdditionPrintedAt,
        public bool $hasOrderAfterAdditionPrinted,
    ) {}

    /**
     * Plain array for Livewire state (DTOs are not supported as public properties).
     *
     * @return array{
     *   restaurantTableId: int,
     *   restaurantTableName: string,
     *   activeSessionStaffName: string|null,
     *   activeTableSessionId: int|null,
     *   unackedPlacedPosOrderCount: int,
     *   unackedPlacedLineExists: bool,
     *   oldestRelevantPlacedAt: string|null,
     *   additionOrCheckoutSignalActive: bool,
     *   relevantPosOrderCount: int,
     *   sessionTotalMinor: int,
     *   category: string|null,
     *   uiStatus: string,
     *   lastAdditionPrintedAt: string|null,
     *   hasOrderAfterAdditionPrinted: bool
     * }
     */
    public function toArray(): array
    {
        return [
            'restaurantTableId' => $this->restaurantTableId,
            'restaurantTableName' => $this->restaurantTableName,
            'activeSessionStaffName' => $this->activeSessionStaffName,
            'activeTableSessionId' => $this->activeTableSessionId,
            'unackedPlacedPosOrderCount' => $this->unackedPlacedPosOrderCount,
            'unackedPlacedLineExists' => $this->unackedPlacedLineExists,
            'oldestRelevantPlacedAt' => $this->oldestRelevantPlacedAt?->toIso8601String(),
            'additionOrCheckoutSignalActive' => $this->additionOrCheckoutSignalActive,
            'relevantPosOrderCount' => $this->relevantPosOrderCount,
            'sessionTotalMinor' => $this->sessionTotalMinor,
            'category' => $this->category?->value,
            'uiStatus' => $this->uiStatus->value,
            'lastAdditionPrintedAt' => $this->lastAdditionPrintedAt?->toIso8601String(),
            'hasOrderAfterAdditionPrinted' => $this->hasOrderAfterAdditionPrinted,
        ];
    }
}
