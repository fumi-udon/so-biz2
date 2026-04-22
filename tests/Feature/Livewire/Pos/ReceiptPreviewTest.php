<?php

namespace Tests\Feature\Livewire\Pos;

use App\Actions\Pos\FinalizeTableSettlementAction;
use App\Actions\Pos\FinalizeTableSettlementRequest;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PrintIntent;
use App\Enums\PrintJobStatus;
use App\Livewire\Pos\ReceiptPreview;
use App\Models\PrintJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;
use Tests\Support\BuildsPosDashboardFixtures;
use Tests\TestCase;

final class ReceiptPreviewTest extends TestCase
{
    use BuildsPosDashboardFixtures;
    use RefreshDatabase;

    public function test_print_from_preview_dispatches_pos_trigger_print_with_contract_payload(): void
    {
        $shop = $this->makeShop('receipt-preview-print');
        $session = $this->openActiveSession($shop, $this->makeCustomerTable($shop));
        $this->placeLinedOrder($shop, $session, 5_500, OrderStatus::Confirmed);
        $operator = $this->makeOperator('receipt-preview');

        Livewire::actingAs($operator)
            ->test(ReceiptPreview::class, [
                'shopId' => (int) $shop->id,
                'tableSessionId' => (int) $session->id,
                'intent' => PrintIntent::Addition->value,
                'expectedSessionRevision' => (int) $session->session_revision,
            ])
            ->call('printFromPreview')
            ->assertDispatched('pos-trigger-print', function (string $_eventName, array $params): bool {
                return isset($params['printJobId'], $params['jobKey'], $params['xml'], $params['opts'])
                    && is_int($params['printJobId'])
                    && is_string($params['jobKey'])
                    && is_string($params['xml'])
                    && str_contains($params['xml'], 'epos-print')
                    && ($params['opts']['timeoutMs'] ?? null) === 10_000;
            })
            ->assertDispatched('receipt-preview-printed', function (string $_eventName, array $params) use ($session): bool {
                return (int) ($params['table_session_id'] ?? 0) === (int) $session->id
                    && ($params['intent'] ?? '') === PrintIntent::Addition->value;
            })
            ->assertSet('uiState', 'success');

        $job = PrintJob::query()->firstOrFail();
        $this->assertSame(PrintIntent::Addition, $job->intent);
        $this->assertSame(PrintJobStatus::Pending, $job->status);
    }

    public function test_addition_intent_records_addition_and_advances_session_revision(): void
    {
        $shop = $this->makeShop('receipt-preview-addition');
        $session = $this->openActiveSession($shop, $this->makeCustomerTable($shop));
        $this->placeLinedOrder($shop, $session, 3_000, OrderStatus::Confirmed);
        $beforeRevision = (int) $session->session_revision;

        Livewire::actingAs($this->makeOperator('receipt-add'))
            ->test(ReceiptPreview::class, [
                'shopId' => (int) $shop->id,
                'tableSessionId' => (int) $session->id,
                'intent' => PrintIntent::Addition->value,
                'expectedSessionRevision' => $beforeRevision,
            ])
            ->call('printFromPreview');

        $session->refresh();
        $this->assertNotNull($session->last_addition_printed_at);
        $this->assertSame($beforeRevision + 1, (int) $session->session_revision);
    }

    public function test_copy_intent_does_not_record_addition_or_touch_session_revision(): void
    {
        $shop = $this->makeShop('receipt-preview-copy');
        $session = $this->openActiveSession($shop, $this->makeCustomerTable($shop));
        $this->placeLinedOrder($shop, $session, 2_000, OrderStatus::Confirmed);
        $beforeRevision = (int) $session->session_revision;

        Livewire::actingAs($this->makeOperator('receipt-copy'))
            ->test(ReceiptPreview::class, [
                'shopId' => (int) $shop->id,
                'tableSessionId' => (int) $session->id,
                'intent' => PrintIntent::Copy->value,
                'expectedSessionRevision' => $beforeRevision,
            ])
            ->call('printFromPreview')
            ->assertDispatched('pos-trigger-print');

        $session->refresh();
        $this->assertNull($session->last_addition_printed_at);
        $this->assertSame($beforeRevision, (int) $session->session_revision);

        $job = PrintJob::query()->firstOrFail();
        $this->assertSame(PrintIntent::Copy, $job->intent);
    }

    public function test_receipt_intent_print_includes_settlement_id_in_job_meta(): void
    {
        $shop = $this->makeShop('receipt-preview-settle');
        $session = $this->openActiveSession($shop, $this->makeCustomerTable($shop));
        $this->placeLinedOrder($shop, $session, 5_000, OrderStatus::Confirmed);
        $operator = $this->makeOperator('receipt-settle');

        app(FinalizeTableSettlementAction::class)->execute(
            new FinalizeTableSettlementRequest(
                shopId: (int) $shop->id,
                tableSessionId: (int) $session->id,
                expectedSessionRevision: (int) $session->session_revision,
                tenderedMinor: 10_000,
                paymentMethod: PaymentMethod::Cash,
                actorUserId: (int) $operator->id,
            )
        );

        $session = $session->fresh();

        Livewire::actingAs($operator)
            ->test(ReceiptPreview::class, [
                'shopId' => (int) $shop->id,
                'tableSessionId' => (int) $session->id,
                'intent' => PrintIntent::Receipt->value,
                'expectedSessionRevision' => (int) $session->session_revision,
            ])
            ->call('printFromPreview')
            ->assertDispatched('pos-trigger-print');

        $job = PrintJob::query()->firstOrFail();
        $this->assertSame(PrintIntent::Receipt, $job->intent);
        $this->assertArrayHasKey('settlement_id', (array) $job->payload_meta);
    }

    public function test_print_skips_physical_job_and_trigger_when_printer_disabled(): void
    {
        Config::set('pos.printer.physical_enabled', false);

        $shop = $this->makeShop('receipt-preview-no-print');
        $session = $this->openActiveSession($shop, $this->makeCustomerTable($shop));
        $this->placeLinedOrder($shop, $session, 5_500, OrderStatus::Confirmed);
        $operator = $this->makeOperator('receipt-no-print');

        Livewire::actingAs($operator)
            ->test(ReceiptPreview::class, [
                'shopId' => (int) $shop->id,
                'tableSessionId' => (int) $session->id,
                'intent' => PrintIntent::Addition->value,
                'expectedSessionRevision' => (int) $session->session_revision,
            ])
            ->call('printFromPreview')
            ->assertNotDispatched('pos-trigger-print')
            ->assertDispatched('receipt-preview-printed')
            ->assertSet('uiState', 'success');

        $this->assertDatabaseCount('print_jobs', 0);
        $session->refresh();
        $this->assertNotNull($session->last_addition_printed_at);
    }
}
