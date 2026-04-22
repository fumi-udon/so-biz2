<?php

namespace Tests\Feature\Actions\Pos\Print;

use App\Actions\Pos\Print\BypassPrintJobAction;
use App\Actions\Pos\Print\CompletePrintJobAction;
use App\Actions\Pos\Print\DispatchPrintJobAction;
use App\Actions\Pos\Print\DispatchPrintJobRequest;
use App\Enums\PrintIntent;
use App\Enums\PrintJobStatus;
use App\Exceptions\Pos\DiscountPinRejectedException;
use App\Models\PrintJob;
use App\Models\Shop;
use App\Support\Pos\EpsonReceiptXmlBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Support\BuildsPosDashboardFixtures;
use Tests\TestCase;

final class PrintJobActionTest extends TestCase
{
    use BuildsPosDashboardFixtures;
    use RefreshDatabase;

    public function test_dispatch_creates_pending_row_with_deterministic_key(): void
    {
        $shop = $this->makeShop('pj-1');
        $session = $this->openActiveSession($shop, $this->makeCustomerTable($shop));

        $req = new DispatchPrintJobRequest(
            shopId: (int) $shop->id,
            tableSessionId: (int) $session->id,
            intent: PrintIntent::Addition,
            sessionRevisionSnapshot: 3,
            payloadXml: '<epos-print/>',
        );

        $job = app(DispatchPrintJobAction::class)->execute($req);

        $this->assertSame(PrintJobStatus::Pending, $job->status);
        $this->assertSame(PrintIntent::Addition, $job->intent);
        $this->assertSame(0, (int) $job->attempt_count);
        $this->assertSame($req->idempotencyKey(), $job->idempotency_key);
    }

    public function test_dispatch_same_idempotency_key_returns_existing_row(): void
    {
        $shop = $this->makeShop('pj-dup');
        $session = $this->openActiveSession($shop, $this->makeCustomerTable($shop));
        $make = fn (): DispatchPrintJobRequest => new DispatchPrintJobRequest(
            shopId: (int) $shop->id,
            tableSessionId: (int) $session->id,
            intent: PrintIntent::Addition,
            sessionRevisionSnapshot: 7,
            payloadXml: '<epos-print/>',
        );

        $first = app(DispatchPrintJobAction::class)->execute($make());
        $second = app(DispatchPrintJobAction::class)->execute($make());

        $this->assertSame((int) $first->id, (int) $second->id);
        $this->assertDatabaseCount('print_jobs', 1);
    }

    public function test_copy_intent_with_distinct_nonces_creates_two_print_jobs(): void
    {
        $shop = $this->makeShop('pj-copy-nonce');
        $session = $this->openActiveSession($shop, $this->makeCustomerTable($shop));

        $make = fn (string $nonce): DispatchPrintJobRequest => new DispatchPrintJobRequest(
            shopId: (int) $shop->id,
            tableSessionId: (int) $session->id,
            intent: PrintIntent::Copy,
            sessionRevisionSnapshot: 5,
            payloadXml: '<epos-print/>',
            idempotencyNonce: $nonce,
        );

        $a = app(DispatchPrintJobAction::class)->execute($make('nonce-a'));
        $b = app(DispatchPrintJobAction::class)->execute($make('nonce-b'));

        $this->assertNotSame((int) $a->id, (int) $b->id);
        $this->assertDatabaseCount('print_jobs', 2);
    }

    public function test_copy_intent_same_nonce_returns_existing_row(): void
    {
        $shop = $this->makeShop('pj-copy-same');
        $session = $this->openActiveSession($shop, $this->makeCustomerTable($shop));

        $req = new DispatchPrintJobRequest(
            shopId: (int) $shop->id,
            tableSessionId: (int) $session->id,
            intent: PrintIntent::Copy,
            sessionRevisionSnapshot: 2,
            payloadXml: '<epos-print/>',
            idempotencyNonce: 'same-nonce',
        );

        $first = app(DispatchPrintJobAction::class)->execute($req);
        $second = app(DispatchPrintJobAction::class)->execute($req);

        $this->assertSame((int) $first->id, (int) $second->id);
        $this->assertDatabaseCount('print_jobs', 1);
    }

    public function test_different_revision_produces_new_print_job(): void
    {
        $shop = $this->makeShop('pj-rev');
        $session = $this->openActiveSession($shop, $this->makeCustomerTable($shop));

        $j1 = app(DispatchPrintJobAction::class)->execute(new DispatchPrintJobRequest(
            shopId: (int) $shop->id,
            tableSessionId: (int) $session->id,
            intent: PrintIntent::Addition,
            sessionRevisionSnapshot: 1,
            payloadXml: '<epos-print/>',
        ));
        $j2 = app(DispatchPrintJobAction::class)->execute(new DispatchPrintJobRequest(
            shopId: (int) $shop->id,
            tableSessionId: (int) $session->id,
            intent: PrintIntent::Addition,
            sessionRevisionSnapshot: 2,
            payloadXml: '<epos-print/>',
        ));

        $this->assertNotSame((int) $j1->id, (int) $j2->id);
        $this->assertDatabaseCount('print_jobs', 2);
    }

    public function test_mark_dispatched_then_succeeded_transitions_states(): void
    {
        $job = $this->makePendingJob('pj-ok');
        $cmp = app(CompletePrintJobAction::class);

        $after1 = $cmp->markDispatched((int) $job->id);
        $this->assertSame(PrintJobStatus::Dispatched, $after1->status);
        $this->assertSame(1, (int) $after1->attempt_count);
        $this->assertNotNull($after1->dispatched_at);

        $after2 = $cmp->markSucceeded((int) $job->id);
        $this->assertSame(PrintJobStatus::Succeeded, $after2->status);
        $this->assertNotNull($after2->completed_at);
    }

    public function test_mark_failed_records_error_and_is_retryable_to_succeeded(): void
    {
        $job = $this->makePendingJob('pj-fail');
        $cmp = app(CompletePrintJobAction::class);

        $failed = $cmp->markFailed((int) $job->id, 'ECONNREFUSED', 'printer offline');
        $this->assertSame(PrintJobStatus::Failed, $failed->status);
        $this->assertSame('ECONNREFUSED', $failed->last_error_code);

        $recovered = $cmp->markSucceeded((int) $job->id);
        $this->assertSame(PrintJobStatus::Succeeded, $recovered->status);
    }

    public function test_succeeded_cannot_be_overwritten_by_failed(): void
    {
        $job = $this->makePendingJob('pj-succ');
        $cmp = app(CompletePrintJobAction::class);

        $cmp->markSucceeded((int) $job->id);

        $this->expectException(RuntimeException::class);
        $cmp->markFailed((int) $job->id, 'E', 'x');
    }

    public function test_bypass_requires_manager_level_pin(): void
    {
        $shop = $this->makeShop('pj-bypass-lvl');
        $operator = $this->makeOperator();
        $staffLv3 = $this->makeApprover($shop, level: 3, pin: '1234');
        $job = $this->makePendingJob('pj-bypass-lvl', $shop);

        $this->expectException(RuntimeException::class);
        app(BypassPrintJobAction::class)->execute(
            printJobId: (int) $job->id,
            operatorUserId: (int) $operator->id,
            approverStaffId: (int) $staffLv3->id,
            approverPin: '1234',
            reason: 'printer offline',
        );
    }

    public function test_bypass_with_manager_pin_marks_bypassed(): void
    {
        $shop = $this->makeShop('pj-bypass-ok');
        $operator = $this->makeOperator();
        $manager = $this->makeApprover($shop, level: 4, pin: '5678');
        $job = $this->makePendingJob('pj-bypass-ok', $shop);

        $out = app(BypassPrintJobAction::class)->execute(
            printJobId: (int) $job->id,
            operatorUserId: (int) $operator->id,
            approverStaffId: (int) $manager->id,
            approverPin: '5678',
            reason: 'printer offline',
        );

        $this->assertSame(PrintJobStatus::Bypassed, $out->status);
        $this->assertNotNull($out->bypassed_at);
        $this->assertSame('printer offline', $out->bypass_reason);
        $this->assertSame((int) $operator->id, (int) $out->bypassed_by_user_id);
    }

    public function test_bypass_wrong_pin_throws_and_leaves_status_untouched(): void
    {
        $shop = $this->makeShop('pj-bypass-pin');
        $operator = $this->makeOperator();
        $manager = $this->makeApprover($shop, level: 4, pin: '5678');
        $job = $this->makePendingJob('pj-bypass-pin', $shop);

        $this->expectException(DiscountPinRejectedException::class);
        try {
            app(BypassPrintJobAction::class)->execute(
                printJobId: (int) $job->id,
                operatorUserId: (int) $operator->id,
                approverStaffId: (int) $manager->id,
                approverPin: '0000',
                reason: 'bad pin',
            );
        } finally {
            $this->assertSame(PrintJobStatus::Pending, $job->fresh()->status);
        }
    }

    public function test_bypassing_succeeded_job_is_rejected(): void
    {
        $shop = $this->makeShop('pj-no-bypass-succ');
        $operator = $this->makeOperator();
        $manager = $this->makeApprover($shop, level: 4, pin: '5678');
        $job = $this->makePendingJob('pj-no-bypass-succ', $shop);
        app(CompletePrintJobAction::class)->markSucceeded((int) $job->id);

        $this->expectException(RuntimeException::class);
        app(BypassPrintJobAction::class)->execute(
            printJobId: (int) $job->id,
            operatorUserId: (int) $operator->id,
            approverStaffId: (int) $manager->id,
            approverPin: '5678',
            reason: 'nope',
        );
    }

    public function test_xml_builder_produces_expected_receipt_structure(): void
    {
        $xml = app(EpsonReceiptXmlBuilder::class)->build([
            'shop_name' => 'BinKitM9',
            'table_label' => 'TC01',
            'intent' => PrintIntent::Receipt,
            'lines' => [
                ['qty' => 2, 'name' => 'Couscous', 'amount_minor' => 24_000],
                ['qty' => 1, 'name' => 'Thé', 'amount_minor' => 3_000],
            ],
            'subtotal_minor' => 27_000,
            'order_discount_minor' => 0,
            'rounding_adjustment_minor' => 100,
            'final_total_minor' => 26_900,
            'tendered_minor' => 30_000,
            'change_minor' => 3_100,
            'printed_at' => '2026-04-19 12:34',
        ]);

        $this->assertStringContainsString('<epos-print', $xml);
        $this->assertStringContainsString('BinKitM9', $xml);
        $this->assertStringContainsString('TOTAL (TTC)', $xml);
        $this->assertStringContainsString('TABLE NO:', $xml);
        $this->assertStringContainsString('Couscous', $xml);
        $this->assertStringContainsString('Thé', $xml);
        $this->assertStringContainsString('<cut type="feed"', $xml);

        $xmlCopy = app(EpsonReceiptXmlBuilder::class)->build([
            'shop_name' => 'S',
            'table_label' => 'T1',
            'intent' => PrintIntent::Copy,
            'lines' => [['qty' => 1, 'name' => 'X', 'amount_minor' => 100]],
            'subtotal_minor' => 100,
            'final_total_minor' => 100,
            'printed_at' => '2026-04-19 12:34',
            'duplicate_original_at' => 'Settled: 2026-04-19 11:00',
        ]);
        $this->assertStringContainsString('DUPLICATA', $xmlCopy);
        $this->assertStringContainsString('Settled: 2026-04-19 11:00', $xmlCopy);
    }

    private function makePendingJob(string $suffix, ?Shop $shop = null): PrintJob
    {
        $shop ??= $this->makeShop($suffix);
        $session = $this->openActiveSession($shop, $this->makeCustomerTable($shop));

        return app(DispatchPrintJobAction::class)->execute(new DispatchPrintJobRequest(
            shopId: (int) $shop->id,
            tableSessionId: (int) $session->id,
            intent: PrintIntent::Addition,
            sessionRevisionSnapshot: 0,
            payloadXml: '<epos-print/>',
        ));
    }
}
