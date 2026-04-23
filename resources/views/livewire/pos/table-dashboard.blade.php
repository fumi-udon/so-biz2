@if (config('app.speed_test', env('SPEED_TEST', false)))
    <x-pos-speed-panel />
@endif
