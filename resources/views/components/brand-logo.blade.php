@props(['variant' => 'full'])

@php
    $variant = in_array($variant, ['full', 'icon'], true) ? $variant : 'full';
    $alt = $variant === 'full' ? 'TaskFlow — Plan, Organize, Get Things Done' : 'TaskFlow';
@endphp

<img
    src="{{ asset("images/brand/taskflow-{$variant}-light.png") }}"
    alt="{{ $alt }}"
    {{ $attributes->class('dark:hidden') }}
>
<img
    src="{{ asset("images/brand/taskflow-{$variant}-dark.png") }}"
    alt="{{ $alt }}"
    {{ $attributes->class('hidden dark:block') }}
>
