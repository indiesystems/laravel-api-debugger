@extends('layouts.app')

@section('content')
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">API Request Logs</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="{{ route('api-debugger.index') }}">API Debugger</a></li>
                    <li class="breadcrumb-item active">Logs</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="container-fluid">
        @include('api-debugger::partials.nav')

        {{-- Filters --}}
        <div class="card card-outline card-secondary {{ request()->hasAny(['method', 'status', 'search', 'session_id', 'tenant_id', 'user_id']) ? '' : 'collapsed-card' }}">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-filter mr-2"></i>Filters
                </h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-tool" data-card-widget="collapse">
                        <i class="fas fa-{{ request()->hasAny(['method', 'status', 'search', 'session_id', 'tenant_id', 'user_id']) ? 'minus' : 'plus' }}"></i>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('api-debugger.logs') }}" id="filter-form">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Method</label>
                                <select name="method" class="form-control form-control-sm">
                                    <option value="">All Methods</option>
                                    @foreach(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $method)
                                        <option value="{{ $method }}" {{ request('method') === $method ? 'selected' : '' }}>
                                            {{ $method }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Status</label>
                                <select name="status" class="form-control form-control-sm">
                                    <option value="">All Statuses</option>
                                    <option value="success" {{ request('status') === 'success' ? 'selected' : '' }}>Success (2xx)</option>
                                    <option value="error" {{ request('status') === 'error' ? 'selected' : '' }}>Error (4xx/5xx)</option>
                                    <option value="200" {{ request('status') === '200' ? 'selected' : '' }}>200 OK</option>
                                    <option value="201" {{ request('status') === '201' ? 'selected' : '' }}>201 Created</option>
                                    <option value="400" {{ request('status') === '400' ? 'selected' : '' }}>400 Bad Request</option>
                                    <option value="401" {{ request('status') === '401' ? 'selected' : '' }}>401 Unauthorized</option>
                                    <option value="403" {{ request('status') === '403' ? 'selected' : '' }}>403 Forbidden</option>
                                    <option value="404" {{ request('status') === '404' ? 'selected' : '' }}>404 Not Found</option>
                                    <option value="422" {{ request('status') === '422' ? 'selected' : '' }}>422 Validation Error</option>
                                    <option value="500" {{ request('status') === '500' ? 'selected' : '' }}>500 Server Error</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Search (URL, Route, IP)</label>
                                <input type="text" name="search" class="form-control form-control-sm"
                                       value="{{ request('search') }}" placeholder="Search...">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <div>
                                    <button type="submit" class="btn btn-sm btn-primary">
                                        <i class="fas fa-search"></i> Filter
                                    </button>
                                    <a href="{{ route('api-debugger.logs') }}" class="btn btn-sm btn-secondary">
                                        <i class="fas fa-times"></i> Clear
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {{-- Logs Table --}}
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-list mr-2"></i>
                    Request Logs
                    <small class="text-muted">({{ $logs->total() }} total)</small>
                </h3>
                <div class="card-tools">
                    <div class="custom-control custom-switch d-inline-block mr-3">
                        <input type="checkbox" class="custom-control-input" id="auto-refresh"
                               {{ config('api-debugger.ui.auto_refresh') ? 'checked' : '' }}>
                        <label class="custom-control-label" for="auto-refresh">Auto-refresh</label>
                    </div>
                    <button type="button" class="btn btn-tool" id="refresh-btn" title="Refresh">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
            </div>
            <div class="card-body table-responsive p-0" id="logs-container">
                @include('api-debugger::partials.logs-table')
            </div>
            <div class="card-footer clearfix">
                {{ $logs->links() }}
            </div>
        </div>
    </div>
</section>

{{-- Log Detail Modal --}}
<div class="modal fade" id="log-detail-modal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <span id="modal-method" class="badge"></span>
                    <span id="modal-url"></span>
                </h5>
                <button type="button" class="close" data-dismiss="modal" onclick="closeLogModal()">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body" id="modal-body">
                <div class="text-center py-5">
                    <i class="fas fa-spinner fa-spin fa-2x"></i>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
// Close modal function (vanilla JS fallback)
function closeLogModal() {
    var modalEl = document.getElementById('log-detail-modal');
    modalEl.classList.remove('show');
    modalEl.style.display = 'none';
    document.body.classList.remove('modal-open');
    var backdrop = document.getElementById('modal-backdrop');
    if (backdrop) backdrop.remove();
}

// Global function for log detail modal
function showLogDetail(row) {
    const jsonUrl = row.dataset.jsonUrl;
    const modalEl = document.getElementById('log-detail-modal');
    const modalBody = document.getElementById('modal-body');

    modalBody.innerHTML = '<div class="text-center py-5"><i class="fas fa-spinner fa-spin fa-2x"></i></div>';

    // Try Bootstrap 5 first, then Bootstrap 4/jQuery, then fallback
    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        new bootstrap.Modal(modalEl).show();
    } else if (typeof jQuery !== 'undefined' && jQuery.fn.modal) {
        jQuery(modalEl).modal('show');
    } else {
        modalEl.classList.add('show');
        modalEl.style.display = 'block';
        document.body.classList.add('modal-open');
        var backdrop = document.createElement('div');
        backdrop.className = 'modal-backdrop fade show';
        backdrop.id = 'modal-backdrop';
        document.body.appendChild(backdrop);
    }

    fetch(jsonUrl)
        .then(response => {
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            document.getElementById('modal-method').textContent = data.method;
            document.getElementById('modal-method').className = 'badge badge-' + data.method_color;
            document.getElementById('modal-url').textContent = data.url;
            modalBody.innerHTML = renderLogDetail(data);
        })
        .catch(error => {
            modalBody.innerHTML = '<div class="alert alert-danger">Failed to load details: ' + error.message + '</div>';
        });
}

document.addEventListener('DOMContentLoaded', function() {
    const refreshInterval = {{ config('api-debugger.ui.refresh_interval', 5) }} * 1000;
    let autoRefreshTimer = null;
    const autoRefreshCheckbox = document.getElementById('auto-refresh');
    const refreshBtn = document.getElementById('refresh-btn');
    const logsContainer = document.getElementById('logs-container');

    function refreshLogs() {
        const currentUrl = new URL(window.location.href);
        fetch(currentUrl.toString(), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => response.text())
        .then(html => {
            logsContainer.innerHTML = html;
        });
    }

    function startAutoRefresh() {
        if (autoRefreshTimer) clearInterval(autoRefreshTimer);
        autoRefreshTimer = setInterval(refreshLogs, refreshInterval);
    }

    function stopAutoRefresh() {
        if (autoRefreshTimer) {
            clearInterval(autoRefreshTimer);
            autoRefreshTimer = null;
        }
    }

    autoRefreshCheckbox.addEventListener('change', function() {
        if (this.checked) {
            startAutoRefresh();
        } else {
            stopAutoRefresh();
        }
    });

    refreshBtn.addEventListener('click', function() {
        this.querySelector('i').classList.add('fa-spin');
        refreshLogs();
        setTimeout(() => this.querySelector('i').classList.remove('fa-spin'), 500);
    });

    if (autoRefreshCheckbox.checked) {
        startAutoRefresh();
    }
});

function renderLogDetail(data) {
    return `
        <div class="row">
            <div class="col-md-6">
                <div class="card card-outline card-info">
                    <div class="card-header"><h5 class="card-title mb-0">Request</h5></div>
                    <div class="card-body">
                        <p><strong>Time:</strong> ${data.requested_at}</p>
                        <p><strong>URL:</strong> <code>${data.full_url}</code></p>
                        <p><strong>Route:</strong> ${data.route_name || 'N/A'}</p>
                        <p><strong>IP:</strong> ${data.ip_address}</p>
                        <p><strong>Size:</strong> ${data.request_size || 'N/A'}</p>

                        <h6 class="mt-3">Headers</h6>
                        <pre class="bg-light p-2 small" style="max-height:200px;overflow:auto">${JSON.stringify(data.request_headers, null, 2)}</pre>

                        ${data.request_query && Object.keys(data.request_query).length ? `
                            <h6 class="mt-3">Query Parameters</h6>
                            <pre class="bg-light p-2 small">${JSON.stringify(data.request_query, null, 2)}</pre>
                        ` : ''}

                        ${data.request_body ? `
                            <h6 class="mt-3">Body</h6>
                            <pre class="bg-light p-2 small" style="max-height:300px;overflow:auto">${typeof data.request_body === 'object' ? JSON.stringify(data.request_body, null, 2) : escapeHtml(data.request_body)}</pre>
                        ` : ''}
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card card-outline card-${data.status_color}">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            Response
                            <span class="badge badge-${data.status_color}">${data.status_code}</span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <p><strong>Time:</strong> ${data.responded_at}</p>
                        <p><strong>Duration:</strong> ${data.duration}</p>
                        <p><strong>Size:</strong> ${data.response_size || 'N/A'}</p>
                        <p><strong>Memory Peak:</strong> ${data.memory_peak_mb || 'N/A'}</p>

                        <h6 class="mt-3">Headers</h6>
                        <pre class="bg-light p-2 small" style="max-height:200px;overflow:auto">${JSON.stringify(data.response_headers, null, 2)}</pre>

                        ${data.response_body ? `
                            <h6 class="mt-3">Body</h6>
                            <pre class="bg-light p-2 small" style="max-height:300px;overflow:auto">${typeof data.response_body === 'object' ? JSON.stringify(data.response_body, null, 2) : escapeHtml(data.response_body)}</pre>
                        ` : ''}

                        ${data.has_exception ? `
                            <div class="alert alert-danger mt-3">
                                <h6><i class="fas fa-exclamation-triangle"></i> Exception</h6>
                                <p class="mb-1"><strong>${data.exception_class}</strong></p>
                                <p class="mb-2">${escapeHtml(data.exception_message)}</p>
                                <details>
                                    <summary>Stack Trace</summary>
                                    <pre class="small mt-2" style="max-height:200px;overflow:auto">${escapeHtml(data.exception_trace)}</pre>
                                </details>
                            </div>
                        ` : ''}
                    </div>
                </div>
            </div>
        </div>
    `;
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>
@endsection
