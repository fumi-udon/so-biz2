@extends('pos2.layouts.bridge')

@section('bridge-title', 'Clôture')

@section('bridge-body')
    <div class="min-h-[100dvh]">
        <livewire:pos.pos2-bridge-cloture-host
            :shop-id="$shopId"
            :table-session-id="$tableSessionId"
            :expected-session-revision="$expectedRevision"
            :key="'pos2-bridge-ch-'.$shopId.'-'.$tableSessionId"
        />
        <livewire:pos.pos2-bridge-messenger />
    </div>
@endsection
