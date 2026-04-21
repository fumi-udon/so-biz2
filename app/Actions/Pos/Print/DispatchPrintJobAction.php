<?php

namespace App\Actions\Pos\Print;

use App\Enums\PrintJobStatus;
use App\Models\PrintJob;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Creates a new print_jobs row (status=pending) for a given logical print,
 * or reuses the existing row if the same (session_id, session_revision,
 * intent) combination was already requested (UNIQUE idempotency_key).
 *
 * Either way the caller gets the row back, so the Livewire bridge can
 * dispatch the browser event with the job's id. The JS side will call back
 * into {@see CompletePrintJobAction} with the same id + ack status.
 */
final class DispatchPrintJobAction
{
    public function execute(DispatchPrintJobRequest $req): PrintJob
    {
        $key = $req->idempotencyKey();

        return DB::transaction(function () use ($req, $key): PrintJob {
            $existing = PrintJob::query()
                ->where('idempotency_key', $key)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            try {
                return PrintJob::query()->create([
                    'shop_id' => $req->shopId,
                    'table_session_id' => $req->tableSessionId,
                    'order_id' => $req->orderId,
                    'intent' => $req->intent,
                    'idempotency_key' => $key,
                    'payload_xml' => $req->payloadXml,
                    'payload_meta' => $req->payloadMeta,
                    'status' => PrintJobStatus::Pending,
                    'attempt_count' => 0,
                ]);
            } catch (QueryException $e) {
                // Race: another request inserted the same idempotency_key
                // between SELECT and INSERT. Re-read and return.
                if ((int) ($e->errorInfo[1] ?? 0) === 1062 || str_contains((string) $e->getMessage(), 'UNIQUE')) {
                    $row = PrintJob::query()->where('idempotency_key', $key)->first();
                    if ($row !== null) {
                        return $row;
                    }
                }
                throw $e;
            }
        });
    }
}
