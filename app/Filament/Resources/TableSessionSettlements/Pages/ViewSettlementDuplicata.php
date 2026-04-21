<?php

namespace App\Filament\Resources\TableSessionSettlements\Pages;

use App\Filament\Resources\TableSessionSettlements\TableSessionSettlementResource;
use App\Models\TableSessionSettlement;
use Filament\Resources\Pages\Page;
use Livewire\Attributes\On;

class ViewSettlementDuplicata extends Page
{
    protected static string $resource = TableSessionSettlementResource::class;

    protected static string $view = 'filament.resources.table-session-settlements.pages.view-settlement-duplicata';

    public TableSessionSettlement $record;

    public int $expectedSessionRevision = 0;

    public static function canAccess(array $parameters = []): bool
    {
        return TableSessionSettlementResource::canPrintDuplicata(auth()->user());
    }

    public function mount(int|string $record): void
    {
        $this->record = TableSessionSettlement::query()
            ->with(['tableSession', 'shop'])
            ->findOrFail($record);

        $session = $this->record->tableSession;
        $this->expectedSessionRevision = (int) (
            $this->record->session_revision_at_settle
            ?? ($session !== null ? $session->session_revision : 0)
        );
    }

    public function getTitle(): string
    {
        return __('pos.settlement_history_duplicata');
    }

    #[On('close-receipt')]
    public function redirectToList(): void
    {
        $this->redirect(TableSessionSettlementResource::getUrl());
    }
}
