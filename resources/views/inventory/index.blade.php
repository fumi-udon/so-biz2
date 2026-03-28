<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>棚卸しポータル — {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">
    <x-client-nav />

    <div class="container py-4 py-md-5" style="max-width: 960px;">
        <header class="mb-4">
            <nav aria-label="breadcrumb" class="small mb-2">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}">ホーム</a></li>
                    <li class="breadcrumb-item active" aria-current="page">棚卸し</li>
                </ol>
            </nav>
            <h1 class="h3 fw-bold text-dark mb-2">棚卸しポータル</h1>
            <p class="text-secondary small mb-0">
                営業日 <span class="font-monospace fw-medium text-dark">{{ $dateString }}</span>
            </p>
        </header>

        @if (session('status'))
            <div class="alert alert-success small shadow-sm" role="alert">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger small shadow-sm" role="alert">{{ session('error') }}</div>
        @endif

        @if (empty($timingSections))
            <div class="border border-2 border-dashed rounded-3 p-5 text-center text-secondary bg-white shadow-sm">
                <i class="bi bi-inbox display-6 d-block mb-2 opacity-50"></i>
                表示する棚卸し品目がありません。
            </div>
        @else
            @foreach ($timingSections as $section)
                @php
                    $timingLabel = $section['timing'] !== '' ? $section['timing'] : '（タイミング未設定）';
                @endphp
                <section class="mb-5">
                    <h2 class="h5 fw-bold text-dark border-bottom pb-2 mb-3 d-flex align-items-center gap-2">
                        <i class="bi bi-clock-history text-primary"></i>
                        <span class="font-monospace">{{ $timingLabel }}</span>
                    </h2>

                    <div class="table-responsive shadow-sm rounded-3 border bg-white">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col" class="small text-secondary" style="min-width: 8rem;">責任者</th>
                                    <th scope="col" class="small text-secondary" style="min-width: 10rem;">ステータス</th>
                                    <th scope="col" class="small text-secondary text-end" style="width: 11rem;">アクション</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($section['rows'] as $row)
                                    <tr>
                                        <td class="fw-medium">
                                            {{ $row['staff_name'] }}
                                        </td>
                                        <td>
                                            @if ($row['complete'])
                                                <span class="badge rounded-pill bg-success px-3 py-2 shadow-sm">
                                                    済✨ ありがとう！
                                                </span>
                                            @else
                                                <span class="badge rounded-pill bg-danger bg-opacity-10 text-danger border border-danger px-3 py-2">
                                                    未実施
                                                </span>
                                                <span class="d-block small text-danger mt-1 mb-0">
                                                    本日の入力がまだ完了していません（{{ $row['filled'] }} / {{ $row['total'] }}）
                                                </span>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            @if ($row['total'] > 0)
                                                <a
                                                    href="{{ route('inventory.input', ['timing' => $section['timing'], 'staff_id' => $row['staff_id']]) }}"
                                                    class="btn btn-sm {{ $row['complete'] ? 'btn-outline-secondary' : 'btn-primary' }}"
                                                >
                                                    @if ($row['complete'])
                                                        Retry
                                                    @else
                                                        実施開始
                                                    @endif
                                                </a>
                                            @else
                                                <span class="text-muted small">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endforeach
        @endif
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
