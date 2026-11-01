{{-- Colour-coded status badge: <x-status-pill :status="$approval->status" /> --}}
@props(['status', 'label' => null])
@php
    $value = strtolower((string) $status);
    $tone = match (true) {
        in_array($value, ['approved', 'paid', 'funded', 'active', 'completed', 'done', 'received', 'cleared', 'closed', 'assessed', 'reviewed'], true) => 'active',
        in_array($value, ['rejected', 'unfunded', 'cancelled', 'inactive', 'overdue', 'failed', 'reversed'], true) => 'inactive',
        in_array($value, ['pending', 'waiting', 'submitted', 'draft', 'open', 'partly_paid', 'partly_funded', 'partial'], true) => 'pending',
        default => null,
    };
@endphp
<span {{ $attributes->class(['status-pill', 'status-pill--'.$tone => $tone]) }}>{{ $label ?? ucfirst(str_replace('_', ' ', (string) $status)) }}</span>
