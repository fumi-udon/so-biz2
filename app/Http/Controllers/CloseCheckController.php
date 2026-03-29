<?php

namespace App\Http\Controllers;

use App\Models\CloseCheckLog;
use App\Models\CloseTask;
use App\Models\Staff;
use App\Services\RoutineInventoryCompletionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CloseCheckController extends Controller
{
    public function index(): View
    {
        $tasks = CloseTask::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        $staffList = Staff::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $incompleteLines = app(RoutineInventoryCompletionService::class)->globalIncompleteSummaries();

        return view('close_check.index', [
            'tasks' => $tasks,
            'staffList' => $staffList,
            'incompleteLines' => $incompleteLines,
        ]);
    }

    public function process(Request $request): RedirectResponse
    {
        $incomplete = app(RoutineInventoryCompletionService::class)->globalIncompleteSummaries();
        if (! empty($incomplete)) {
            abort(403, '未完了のタスクまたは棚卸しが残っているため、クローズ処理を実行できません。');
        }

        $validated = $request->validate([
            'staff_id' => ['required', 'integer', 'exists:staff,id'],
            'pin_code' => ['required', 'string', 'digits:4'],
        ]);

        $staff = Staff::query()
            ->where('id', $validated['staff_id'])
            ->where('is_active', true)
            ->first();

        if (! $staff) {
            return redirect()
                ->route('close-check.index')
                ->withInput($request->except('pin_code'))
                ->with('error', 'スタッフが見つからないか、無効です。');
        }

        if ($staff->pin_code === null || $staff->pin_code === '') {
            return redirect()
                ->route('close-check.index')
                ->withInput($request->except('pin_code'))
                ->with('error', 'PIN が設定されていません。管理者に連絡してください。');
        }

        if (! hash_equals((string) $staff->pin_code, (string) $validated['pin_code'])) {
            return redirect()
                ->route('close-check.index')
                ->withInput($request->except('pin_code'))
                ->with('error', 'PIN が正しくありません。');
        }

        CloseCheckLog::query()->create([
            'staff_id' => $staff->id,
            'date' => now()->toDateString(),
            'completed_at' => now(),
        ]);

        return redirect()
            ->route('close-check.success')
            ->with('closed_staff_name', $staff->name);
    }

    public function success(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('closed_staff_name')) {
            return redirect()->route('close-check.index');
        }

        return view('close_check.success', [
            'closedStaffName' => $request->session()->get('closed_staff_name'),
        ]);
    }
}
