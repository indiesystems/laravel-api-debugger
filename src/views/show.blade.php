@extends('layouts.app')

@section('content')
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">
                    <span class="badge badge-{{ $log->method_color }}">{{ $log->method }}</span>
                    {{ $log->url }}
                </h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="{{ route('api-debugger.index') }}">API Debugger</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('api-debugger.logs') }}">Logs</a></li>
                    <li class="breadcrumb-item active">#{{ $log->id }}</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="container-fluid">
        {{-- Overview --}}
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-2">
                                <strong>Status</strong><br>
                                <span class="badge badge-{{ $log->status_color }} badge-lg">{{ $log->status_code }}</span>
                            </div>
                            <div class="col-md-2">
                                <strong>Duration</strong><br>
                                {{ $log->formatted_duration }}
                            </div>
                            <div class="col-md-2">
                                <strong>Time</strong><br>
                                {{ $log->requested_at->format(config('api-debugger.ui.date_format')) }}
                            </div>
                            <div class="col-md-2">
                                <strong>IP Address</strong><br>
                                {{ $log->ip_address }}
                            </div>
                            <div class="col-md-2">
                                <strong>User</strong><br>
                                @if($log->user)
                                    {{ $log->user->name ?? $log->user->email }}
                                @else
                                    <span class="text-muted">Guest</span>
                                @endif
                            </div>
                            <div class="col-md-2">
                                <strong>Tenant</strong><br>
                                {{ $log->tenant_id ?? '-' }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            {{-- Request --}}
            <div class="col-lg-6">
                <div class="card card-outline card-info">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-arrow-right mr-2"></i>Request
                        </h3>
                    </div>
                    <div class="card-body">
                        <dl class="row">
                            <dt class="col-sm-4">Full URL</dt>
                            <dd class="col-sm-8 text-break">{{ $log->full_url }}</dd>

                            <dt class="col-sm-4">Route</dt>
                            <dd class="col-sm-8">{{ $log->route_name ?? 'N/A' }}</dd>

                            <dt class="col-sm-4">Action</dt>
                            <dd class="col-sm-8"><code>{{ $log->route_action ?? 'N/A' }}</code></dd>

                            <dt class="col-sm-4">Content-Type</dt>
                            <dd class="col-sm-8">{{ $log->request_content_type ?? 'N/A' }}</dd>

                            <dt class="col-sm-4">Size</dt>
                            <dd class="col-sm-8">{{ $log->formatted_request_size }}</dd>

                            <dt class="col-sm-4">User Agent</dt>
                            <dd class="col-sm-8"><small>{{ $log->user_agent ?? 'N/A' }}</small></dd>
                        </dl>

                        <x-AdminLteUiComponentsView::collapsible-card title="Headers" :collapsed="true">
                            <pre class="mb-0 small">{{ json_encode($log->getRequestHeadersForDisplay(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                        </x-AdminLteUiComponentsView::collapsible-card>

                        @if($log->request_query)
                            <x-AdminLteUiComponentsView::collapsible-card title="Query Parameters" :collapsed="true">
                                <pre class="mb-0 small">{{ json_encode($log->request_query, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                            </x-AdminLteUiComponentsView::collapsible-card>
                        @endif

                        @if($log->request_body)
                            <x-AdminLteUiComponentsView::collapsible-card title="Body" :collapsed="false">
                                @if($log->is_json_request)
                                    <pre class="mb-0 small">{{ json_encode($log->parsed_request_body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                @else
                                    <pre class="mb-0 small">{{ $log->request_body }}</pre>
                                @endif
                            </x-AdminLteUiComponentsView::collapsible-card>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Response --}}
            <div class="col-lg-6">
                <div class="card card-outline card-{{ $log->status_color }}">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-arrow-left mr-2"></i>Response
                            <span class="badge badge-{{ $log->status_color }}">{{ $log->status_code }}</span>
                        </h3>
                    </div>
                    <div class="card-body">
                        <dl class="row">
                            <dt class="col-sm-4">Responded At</dt>
                            <dd class="col-sm-8">{{ $log->responded_at?->format(config('api-debugger.ui.date_format')) ?? 'N/A' }}</dd>

                            <dt class="col-sm-4">Duration</dt>
                            <dd class="col-sm-8">{{ $log->formatted_duration }}</dd>

                            <dt class="col-sm-4">Content-Type</dt>
                            <dd class="col-sm-8">{{ $log->response_content_type ?? 'N/A' }}</dd>

                            <dt class="col-sm-4">Size</dt>
                            <dd class="col-sm-8">{{ $log->formatted_response_size }}</dd>

                            <dt class="col-sm-4">Memory Peak</dt>
                            <dd class="col-sm-8">{{ $log->memory_peak_mb ? round($log->memory_peak_mb, 2) . ' MB' : 'N/A' }}</dd>
                        </dl>

                        <x-AdminLteUiComponentsView::collapsible-card title="Headers" :collapsed="true">
                            <pre class="mb-0 small">{{ json_encode($log->getResponseHeadersForDisplay(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                        </x-AdminLteUiComponentsView::collapsible-card>

                        @if($log->response_body)
                            <x-AdminLteUiComponentsView::collapsible-card title="Body" :collapsed="false">
                                @if($log->is_json_response)
                                    <pre class="mb-0 small">{{ json_encode($log->parsed_response_body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                @else
                                    <pre class="mb-0 small">{{ $log->response_body }}</pre>
                                @endif
                            </x-AdminLteUiComponentsView::collapsible-card>
                        @endif

                        @if($log->has_exception)
                            <div class="alert alert-danger mt-3">
                                <h5><i class="fas fa-exclamation-triangle mr-2"></i>Exception</h5>
                                <p class="mb-1"><strong>{{ $log->exception_class }}</strong></p>
                                <p>{{ $log->exception_message }}</p>

                                <x-AdminLteUiComponentsView::collapsible-card title="Stack Trace" :collapsed="true" color="danger">
                                    <pre class="mb-0 small text-danger">{{ $log->exception_trace }}</pre>
                                </x-AdminLteUiComponentsView::collapsible-card>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Actions --}}
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <a href="{{ route('api-debugger.logs') }}" class="btn btn-secondary">
                            <i class="fas fa-arrow-left mr-2"></i>Back to Logs
                        </a>
                        <form action="{{ route('api-debugger.logs.delete', $log) }}" method="POST" class="d-inline"
                              onsubmit="return confirm('Delete this log?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger float-right">
                                <i class="fas fa-trash mr-2"></i>Delete Log
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
