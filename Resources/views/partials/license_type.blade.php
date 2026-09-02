@php
    $labels = [
        'one_time'        => __('Einmalig'),
        'monthly'         => __('Monatlich'),
        'yearly'          => __('Jährlich'),
        'yearly_per_user' => __('Jährlich je Nutzer'),
        'monthly_per_user' => __('Monatlich je Nutzer'),
    ];
@endphp
{{ $labels[$type] ?? $type }}
