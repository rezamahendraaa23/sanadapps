@props(['status'])

@php($map = ['paid' => 'paid', 'unpaid' => 'unpaid', 'partial' => 'partial', 'overdue' => 'overdue', 'void' => 'void'])

<x-ui.status-badge :status="$map[$status]" {{ $attributes }} />
