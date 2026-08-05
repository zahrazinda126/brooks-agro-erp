@extends('layouts.app')

@section('title', $module->name)

@section('content')

    <div class="page-head">
        <h2>{{ $module->name }}</h2>
        <p>{{ $module->group }}</p>
    </div>

    <div class="card">
        <p class="card-label">Not built yet</p>
        <p class="muted" style="margin:0 0 12px;">
            Your account has access to this screen, but it has not been built
            yet. It is scheduled as part of the module rollout.
        </p>
        <a class="btn btn--quiet btn--inline" href="{{ route('dashboard') }}">Back to dashboard</a>
    </div>

    <div class="card">
        <p class="card-label">Your rights here</p>
        <p style="margin:0;">
            @foreach (['view' => 'View', 'create' => 'Add', 'edit' => 'Change', 'delete' => 'Remove', 'approve' => 'Approve'] as $action => $label)
                @if ($user->canDo($module->key, $action) || ($action === 'view' && $user->canAccess($module->key)))
                    <span class="pill" style="margin:0 4px 4px 0;">{{ $label }}</span>
                @endif
            @endforeach
        </p>
    </div>

@endsection