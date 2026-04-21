<?php

namespace Tests\Feature\Livewire\Pos;

use App\Actions\Pos\Print\DispatchPrintJobAction;
use App\Actions\Pos\Print\DispatchPrintJobRequest;
use App\Enums\PrintIntent;
use App\Enums\PrintJobStatus;
use App\Livewire\Pos\PrinterBridge;
use App\Models\PrintJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\BuildsPosDashboardFixtures;
use Tests\TestCase;

final class PrinterBridgeTest extends TestCase
{
    use BuildsPosDashboardFixtures;
    use RefreshDatabase;

    public function test_dispatched_event_marks_job_as_dispatched(): void
    {
        $job = $this->makePendingJob('pb-d');

        Livewire::test(PrinterBridge::class, ['shopId' => (int) $job->shop_id])
            ->dispatch('pos-print-dispatched', printJobId: (int) $job->id);

        $job->refresh();
        $this->assertSame(PrintJobStatus::Dispatched, $job->status);
        $this->assertSame(1, (int) $job->attempt_count);
    }

    public function test_ack_ok_marks_job_succeeded(): void
    {
        $job = $this->makePendingJob('pb-ok');

        Livewire::test(PrinterBridge::class, ['shopId' => (int) $job->shop_id])
            ->dispatch('pos-print-ack', printJobId: (int) $job->id, ok: true);

        $this->assertSame(PrintJobStatus::Succeeded, $job->fresh()->status);
    }

    public function test_ack_failure_records_code_and_message(): void
    {
        $job = $this->makePendingJob('pb-fail');

        Livewire::test(PrinterBridge::class, ['shopId' => (int) $job->shop_id])
            ->dispatch(
                'pos-print-ack',
                printJobId: (int) $job->id,
                ok: false,
                code: 'EPOS_TIMEOUT',
                message: 'hardware offline',
            );

        $job->refresh();
        $this->assertSame(PrintJobStatus::Failed, $job->status);
        $this->assertSame('EPOS_TIMEOUT', $job->last_error_code);
        $this->assertSame('hardware offline', $job->last_error_message);
    }

    public function test_ack_with_invalid_id_is_silent(): void
    {
        $shop = $this->makeShop('pb-bad');

        Livewire::test(PrinterBridge::class, ['shopId' => (int) $shop->id])
            ->dispatch('pos-print-ack', printJobId: 0, ok: true);

        $this->assertDatabaseCount('print_jobs', 0);
    }

    private function makePendingJob(string $suffix): PrintJob
    {
        $shop = $this->makeShop($suffix);
        $session = $this->openActiveSession($shop, $this->makeCustomerTable($shop));

        return app(DispatchPrintJobAction::class)->execute(new DispatchPrintJobRequest(
            shopId: (int) $shop->id,
            tableSessionId: (int) $session->id,
            intent: PrintIntent::Receipt,
            sessionRevisionSnapshot: 0,
            payloadXml: '<epos-print/>',
        ));
    }
}
