{{-- 名前選択 → PIN（同一PINの複数人に対応） --}}
<div
    class="modal fade"
    id="mypagePinModal"
    tabindex="-1"
    aria-labelledby="mypagePinModalLabel"
    aria-hidden="true"
>
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 shadow">
            <form method="post" action="{{ route('mypage.open') }}">
                @csrf
                <div class="modal-header border-0 pb-0">
                    <h2 class="modal-title fs-6 fw-bold" id="mypagePinModalLabel">マイページを開く</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
                </div>
                <div class="modal-body pt-2">
                    <p class="text-secondary small mb-3">名前を選んでから、4桁PINを入力してください。</p>
                    <label for="mypage_modal_staff_id" class="form-label small mb-1">名前</label>
                    <select
                        name="staff_id"
                        id="mypage_modal_staff_id"
                        required
                        class="form-select rounded-4 mb-3"
                    >
                        <option value="" disabled selected>— 選択 —</option>
                        @foreach ($mypageStaffList as $s)
                            <option value="{{ $s->id }}">{{ $s->name }}</option>
                        @endforeach
                    </select>
                    @if ($mypageStaffList->isEmpty())
                        <p class="text-danger small mb-0">アクティブなスタッフがありません。</p>
                    @endif
                    <label for="mypage_modal_pin" class="form-label small mb-1">PIN（4桁）</label>
                    <input
                        type="password"
                        name="pin_code"
                        id="mypage_modal_pin"
                        inputmode="numeric"
                        maxlength="4"
                        required
                        class="form-control form-control-lg font-monospace text-center rounded-4"
                        placeholder="••••"
                        autocomplete="one-time-code"
                        @disabled($mypageStaffList->isEmpty())
                    >
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button
                        type="submit"
                        class="btn btn-success w-100 py-2 rounded-4 fw-semibold"
                        @disabled($mypageStaffList->isEmpty())
                    >
                        開く
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
