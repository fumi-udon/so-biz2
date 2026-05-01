@extends('pos2.layouts.bridge')

@section('bridge-title')
    @if (($intent ?? '') === 'addition')
        Addition
    @else
        Reçu
    @endif
@endsection

@section('bridge-body')
    <div class="min-h-[100dvh]">
        <livewire:pos.receipt-preview
            :shop-id="$shopId"
            :table-session-id="$tableSessionId"
            :intent="$intent"
            :expected-session-revision="$expectedRevision"
            :key="'pos2-bridge-rp-'.$shopId.'-'.$tableSessionId.'-'.$intent.'-'.$expectedRevision"
        />
        <livewire:pos.pos2-bridge-messenger />
    </div>
@endsection
