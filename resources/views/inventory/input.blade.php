<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>棚卸し入力（{{ $timing }}） — {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">
    <x-client-nav />

    <div class="container py-4 mx-auto" style="max-width: 28rem;">
        <header class="mb-4">
            <nav aria-label="breadcrumb" class="small mb-2">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}">ホーム</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('inventory.index') }}">棚卸し</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $timing }}</li>
                </ol>
            </nav>
            <h1 class="h4 fw-bold mb-1">棚卸し入力</h1>
            <p class="text-secondary small mb-0">
                <span class="font-monospace">{{ $dateString }}</span>
                · タイミング <span class="font-monospace">{{ $timing }}</span>
            </p>
        </header>

        @if (session('error'))
            <div class="alert alert-danger small" role="alert">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger small" role="alert">
                <ul class="mb-0 ps-3">
                    @foreach ($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="post" action="{{ route('inventory.store') }}">
            @csrf
            <input type="hidden" name="staff_id" value="{{ $staff->id }}">
            <input type="hidden" name="timing" value="{{ $timing }}">

            <div class="card shadow-sm mb-4">
                <div class="card-header py-2 py-md-3 fw-semibold small">品目</div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th scope="col" class="small text-secondary" style="width: 52%; min-width: 8rem;">品目 / カテゴリ</th>
                                <th scope="col" class="small text-secondary" style="width: 48%; min-width: 7rem;">入力</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($inventoryItems as $item)
                                @php
                                    $inputType = $item->input_type ?? 'number';
                                @endphp
                                <tr>
                                    <td class="small py-2">
                                        <div class="fw-semibold text-break">{{ $item->name }}</div>
                                        <div class="text-secondary" style="font-size: 0.7rem;">{{ $item->category }}</div>
                                        @if ($inputType === 'number')
                                            <span class="badge rounded-pill bg-secondary bg-opacity-10 text-dark border small mt-1">{{ $item->unit }}</span>
                                        @endif
                                    </td>
                                    <td class="py-2">
                                        @if ($inputType === 'select')
                                            <select
                                                name="inventory_val[{{ $item->id }}]"
                                                id="inv-{{ $item->id }}"
                                                class="form-select form-select-sm @error('inventory_val.'.$item->id) is-invalid @enderror"
                                                aria-label="{{ $item->name }}"
                                            >
                                                <option value="">選択</option>
                                                @foreach ($item->options ?? [] as $opt)
                                                    <option
                                                        value="{{ $opt }}"
                                                        @selected(old('inventory_val.'.$item->id, $inventoryValues[$item->id] ?? '') == $opt)
                                                    >{{ $opt }}</option>
                                                @endforeach
                                            </select>
                                            @error('inventory_val.'.$item->id)
                                                <div class="invalid-feedback d-block small">{{ $message }}</div>
                                            @enderror
                                        @elseif ($inputType === 'text')
                                            <input
                                                type="text"
                                                name="inventory_val[{{ $item->id }}]"
                                                id="inv-{{ $item->id }}"
                                                value="{{ old('inventory_val.'.$item->id, $inventoryValues[$item->id] ?? '') }}"
                                                class="form-control form-control-sm @error('inventory_val.'.$item->id) is-invalid @enderror"
                                                aria-label="{{ $item->name }}"
                                            >
                                            @error('inventory_val.'.$item->id)
                                                <div class="invalid-feedback d-block small">{{ $message }}</div>
                                            @enderror
                                        @else
                                            <input
                                                type="number"
                                                name="inventory_val[{{ $item->id }}]"
                                                id="inv-{{ $item->id }}"
                                                step="0.01"
                                                min="0"
                                                inputmode="decimal"
                                                value="{{ old('inventory_val.'.$item->id, $inventoryValues[$item->id] ?? '') }}"
                                                class="form-control form-control-sm text-end @error('inventory_val.'.$item->id) is-invalid @enderror"
                                                placeholder="0"
                                                aria-label="{{ $item->name }}"
                                            >
                                            @error('inventory_val.'.$item->id)
                                                <div class="invalid-feedback d-block small">{{ $message }}</div>
                                            @enderror
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
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

            <div class="d-grid gap-2 pb-5">
                <button type="submit" class="btn btn-dark btn-lg">保存</button>
                <a href="{{ route('inventory.index', ['staff_id' => $staff->id]) }}" class="btn btn-outline-secondary">全体表に戻る</a>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
