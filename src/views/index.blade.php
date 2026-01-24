@extends('layouts.app')

@section('content')
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">API Debugger</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="{{ url('/') }}">Home</a></li>
                    <li class="breadcrumb-item active">API Debugger</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="container-fluid">
        {{-- Stats Row --}}
        <div class="row">
            <div class="col-lg-3 col-6">
                <x-AdminLteUiComponentsView::small-box
                    title="{{ number_format($stats['total_logs']) }}"
                    subtitle="Total Logs"
                    icon="fas fa-database"
                    color="info"
                />
            </div>
            <div class="col-lg-3 col-6">
                <x-AdminLteUiComponentsView::small-box
                    title="{{ number_format($stats['logs_today']) }}"
                    subtitle="Logs Today"
                    icon="fas fa-calendar-day"
                    color="success"
                />
            </div>
            <div class="col-lg-3 col-6">
                <x-AdminLteUiComponentsView::small-box
                    title="{{ $stats['active_sessions'] }}"
                    subtitle="Active Sessions"
                    icon="fas fa-satellite-dish"
                    color="warning"
                />
            </div>
            <div class="col-lg-3 col-6">
                <x-AdminLteUiComponentsView::small-box
                    title="{{ $stats['error_rate'] }}%"
                    subtitle="Error Rate Today"
                    icon="fas fa-exclamation-triangle"
                    color="{{ $stats['error_rate'] > 10 ? 'danger' : 'secondary' }}"
                />
            </div>
        </div>

        <div class="row">
            {{-- Active Sessions --}}
            <div class="col-lg-6">
                <div class="card card-outline card-primary">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-satellite-dish mr-2"></i>
                            Active Debug Sessions
                        </h3>
                        <div class="card-tools">
                            <a href="{{ route('api-debugger.sessions') }}" class="btn btn-tool">
                                <i class="fas fa-cog"></i> Manage
                            </a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        @if($sessions->isEmpty())
                            <div class="p-4 text-center text-muted">
                                <i class="fas fa-info-circle fa-2x mb-2"></i>
                                <p>No active debug sessions</p>
                                <a href="{{ route('api-debugger.sessions') }}" class="btn btn-sm btn-primary">
                                    <i class="fas fa-plus"></i> Create Session
                                </a>
                            </div>
                        @else
                            <ul class="list-group list-group-flush">
                                @foreach($sessions as $session)
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong>{{ $session->getTargetLabel() }}</strong>
                                            @if($session->label)
                                                <small class="text-muted">({{ $session->label }})</small>
                                            @endif
                                            <br>
                                            <small class="text-muted">
                                                <i class="fas fa-clock"></i>
                                                {{ $session->remainingMinutes() }} min remaining
                                                &bull;
                                                <i class="fas fa-file-alt"></i>
                                                {{ $session->logs_count }} logs
                                            </small>
                                        </div>
                                        <div>
                                            <a href="{{ route('api-debugger.logs', ['session_id' => $session->id]) }}"
                                               class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <form action="{{ route('api-debugger.sessions.stop', $session) }}"
                                                  method="POST" class="d-inline">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="btn btn-sm btn-danger">
                                                    <i class="fas fa-stop"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Quick Stats --}}
            <div class="col-lg-6">
                <div class="card card-outline card-info">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-chart-line mr-2"></i>
                            Performance Overview
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-6">
                                <div class="info-box bg-light">
                                    <span class="info-box-icon bg-info"><i class="fas fa-tachometer-alt"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">Avg Response Time</span>
                                        <span class="info-box-number">
                                            {{ $stats['avg_response_time'] ? number_format($stats['avg_response_time'], 2) . ' ms' : 'N/A' }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="info-box bg-light">
                                    <span class="info-box-icon bg-{{ config('api-debugger.enabled') ? 'success' : 'secondary' }}">
                                        <i class="fas fa-power-off"></i>
                                    </span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">Debugger Status</span>
                                        <span class="info-box-number">
                                            {{ config('api-debugger.enabled') ? 'Enabled' : 'Disabled' }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-3">
                            <a href="{{ route('api-debugger.logs') }}" class="btn btn-primary btn-block">
                                <i class="fas fa-list mr-2"></i> View All Logs
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
