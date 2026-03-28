<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>マイページ（タスク・棚卸し） — {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">
    <div class="container py-4 mx-auto" style="max-width: 28rem;">
        <header class="mb-4">
            <h1 class="h4 fw-bold mb-1">マイページ</h1>
            <p class="text-secondary small mb-0">営業日: {{ $dateString }}</p>
        </header>

        @if (session('status'))
            <div class="alert alert-success small" role="alert">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger small" role="alert">{{ session('error') }}</div>
        @endif

        <form method="get" action="{{ route('mypage.index') }}" class="mb-4">
            <label for="staff_select" class="form-label small fw-medium">スタッフを選択</label>
            <select name="staff_id" id="staff_select" class="form-select" onchange="this.form.submit()">
                <option value="">— 選択 —</option>
                @foreach ($staffList as $s)
                    <option value="{{ $s->id }}" @selected($staff && $staff->id === $s->id)>{{ $s->name }}</option>
                @endforeach
            </select>
        </form>

        @if (! $staff)
            <p class="border border-2 border-dashed rounded p-5 text-center text-secondary bg-white small">
                スタッフを選ぶと、本日のタスクと棚卸しが表示されます。
            </p>
        @else
            <form method="post" action="{{ route('mypage.store') }}">
                @csrf
                <input type="hidden" name="staff_id" value="{{ $staff->id }}">

                <div class="pb-5" style="padding-bottom: 6rem;">

                <div class="card shadow-sm mb-4">
                    <div class="card-header py-3 fw-semibold small">ルーティンタスク</div>
                    @if ($routineTasks->isEmpty())
                        <div class="card-body">
                            <p class="text-secondary small mb-0">割り当てられたタスクはありません。</p>
                        </div>
                    @else
                        <ul class="list-group list-group-flush">
                            @foreach ($routineTasks as $task)
                                <li class="list-group-item">
                                    <div class="form-check d-flex align-items-start gap-3">
                                        <input
                                            class="form-check-input fs-4 flex-shrink-0 mt-1"
                                            type="checkbox"
                                            name="routine_task[{{ $task->id }}]"
                                            value="1"
                                            id="routine-{{ $task->id }}"
                                            @checked(old('routine_task.'.$task->id, $routineLogIds->contains($task->id)))
                                        >
                                        <label class="form-check-label" for="routine-{{ $task->id }}">
                                            <span class="d-block text-secondary" style="font-size: 0.75rem;">{{ $task->category }} · {{ $task->timing }}</span>
                                            <span class="fw-medium">{{ $task->name }}</span>
                                        </label>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                <div class="card shadow-sm mb-4">
                    <div class="card-header py-3 fw-semibold small">棚卸し（数量）</div>
                    @if ($inventoryItems->isEmpty())
                        <div class="card-body">
                            <p class="text-secondary small mb-0">割り当てられた棚卸しはありません。</p>
                        </div>
                    @else
                        <ul class="list-group list-group-flush">
                            @foreach ($inventoryItems as $item)
                                <li class="list-group-item">
                                    <p class="text-secondary mb-1" style="font-size: 0.75rem;">{{ $item->category }} · {{ $item->timing }}</p>
                                    <p class="fw-medium mb-2">{{ $item->name }}</p>
                                    <div class="input-group">
                                        <input
                                            type="number"
                                            name="inventory_qty[{{ $item->id }}]"
                                            id="inv-{{ $item->id }}"
                                            step="0.01"
                                            min="0"
                                            inputmode="decimal"
                                            value="{{ old('inventory_qty.'.$item->id, $inventoryQuantities[$item->id] ?? '') }}"
                                            class="form-control text-end"
                                            placeholder="0"
                                            aria-label="{{ $item->name }} の数量"
                                        >
                                        <span class="input-group-text">{{ $item->unit }}</span>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <label for="pin_code" class="form-label small fw-medium">保存用 PIN（4桁）</label>
                        <input
                            type="password"
                            name="pin_code"
                            id="pin_code"
                            inputmode="numeric"
                            maxlength="4"
                            required
                            class="form-control form-control-lg font-monospace text-center"
                            placeholder="••••"
                            autocomplete="one-time-code"
                        >
                    </div>
                </div>

                </div>

                <div class="position-fixed bottom-0 start-0 end-0 bg-white border-top shadow py-3 px-3">
                    <div class="mx-auto" style="max-width: 28rem;">
                        <button type="submit" class="btn btn-dark btn-lg w-100">
                            一括保存
                        </button>
                    </div>
                </div>
            </form>
        @endif
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
