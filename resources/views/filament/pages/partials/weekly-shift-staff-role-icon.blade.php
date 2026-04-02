{{-- Rôle → icône (mini Heroicons). Données: $staff (Staff), optionnel $class --}}
@php
    $icon = 'heroicon-m-user';
    $class = $class ?? 'h-3 w-3 shrink-0 text-gray-600 dark:text-gray-300';
    if ($staff->is_manager ?? false) {
        $icon = 'heroicon-m-clipboard-document-check';
    } else {
        $r = strtolower((string) ($staff->role ?? ''));
        $jl = strtolower((string) ($staff->jobLevel?->name ?? ''));
        $hay = $r.' '.$jl;
        if ($hay !== ' ' && (
            str_contains($hay, 'kit') || str_contains($hay, 'cuis') || str_contains($hay, 'cook')
            || str_contains($hay, 'kitchen') || str_contains($hay, '調理')
        )) {
            $icon = 'heroicon-m-fire';
        } elseif (str_contains($hay, 'hall') || str_contains($hay, 'salle') || str_contains($hay, 'serve') || str_contains($hay, 'ホール')) {
            $icon = 'heroicon-m-users';
        } elseif (str_contains($hay, 'manage') || str_contains($hay, 'chef') || str_contains($hay, 'マネ') || str_contains($hay, 'dir')) {
            $icon = 'heroicon-m-clipboard-document-check';
        }
    }
@endphp
<x-filament::icon :icon="$icon" class="{{ $class }}" />
