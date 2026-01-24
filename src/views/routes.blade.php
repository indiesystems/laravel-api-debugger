@extends('layouts.app')

@section('content')
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">API Routes</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="{{ route('api-debugger.index') }}">API Debugger</a></li>
                    <li class="breadcrumb-item active">Routes</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="container-fluid">
        @include('api-debugger::partials.nav')

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-sitemap mr-2"></i>
                    Available API Routes
                    <small class="text-muted">({{ $routes->count() }} routes)</small>
                </h3>
                <div class="card-tools d-flex align-items-center">
                    <div class="custom-control custom-switch mr-3">
                        <input type="checkbox" class="custom-control-input" id="show-all"
                               {{ request()->boolean('all') ? 'checked' : '' }}
                               onchange="window.location.href='{{ route('api-debugger.routes') }}' + (this.checked ? '?all=1' : '')">
                        <label class="custom-control-label" for="show-all">All routes</label>
                    </div>
                    <select id="method-filter" class="form-control form-control-sm mr-2" style="width: 100px;">
                        <option value="">All methods</option>
                        <option value="GET">GET</option>
                        <option value="POST">POST</option>
                        <option value="PUT">PUT</option>
                        <option value="PATCH">PATCH</option>
                        <option value="DELETE">DELETE</option>
                    </select>
                    <div class="input-group input-group-sm" style="width: 200px;">
                        <input type="text" id="route-search" class="form-control" placeholder="Filter routes...">
                        <div class="input-group-append">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-hover table-striped" id="routes-table">
                    <thead>
                        <tr>
                            <th style="width: 100px">Methods</th>
                            <th>URI</th>
                            <th>Route Name</th>
                            <th>Parameters</th>
                            <th>Controller</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($routes as $route)
                            <tr class="route-row">
                                <td>
                                    @foreach($route['methods'] as $method)
                                        @if($method !== 'HEAD')
                                            @php
                                                $color = match($method) {
                                                    'GET' => 'info',
                                                    'POST' => 'success',
                                                    'PUT', 'PATCH' => 'warning',
                                                    'DELETE' => 'danger',
                                                    default => 'secondary'
                                                };
                                            @endphp
                                            <span class="badge badge-{{ $color }}">{{ $method }}</span>
                                        @endif
                                    @endforeach
                                </td>
                                <td>
                                    <code class="text-primary">/{{ $route['uri'] }}</code>
                                </td>
                                <td>
                                    @if($route['name'])
                                        <code class="text-muted">{{ $route['name'] }}</code>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if(count($route['parameters']) > 0)
                                        @foreach($route['parameters'] as $param)
                                            <span class="badge badge-light border">
                                                {{ str_contains($param, '?') ? str_replace('?', '', $param) . ' (optional)' : $param }}
                                            </span>
                                        @endforeach
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    <small class="text-muted">
                                        @if(str_contains($route['action'], '@'))
                                            {{ class_basename(explode('@', $route['action'])[0]) }}
                                            <span class="text-info">@ {{ explode('@', $route['action'])[1] }}</span>
                                        @elseif($route['action'] === 'Closure')
                                            <em>Closure</em>
                                        @else
                                            {{ $route['action'] }}
                                        @endif
                                    </small>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">
                                    <i class="fas fa-inbox fa-2x mb-2"></i>
                                    <p>No API routes found</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card card-outline card-info">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-info-circle mr-2"></i>Debug Token Usage
                </h3>
            </div>
            <div class="card-body">
                <p>You can pass the debug token in two ways:</p>
                <div class="row">
                    <div class="col-md-6">
                        <h6>Via Header</h6>
                        <pre class="bg-dark text-light p-3 rounded"><code>curl -H "X-Debug-Token: YOUR_TOKEN" \
     {{ url('/api/your-endpoint') }}</code></pre>
                    </div>
                    <div class="col-md-6">
                        <h6>Via Query Parameter</h6>
                        <pre class="bg-dark text-light p-3 rounded"><code>{{ url('/api/your-endpoint') }}?_debug_token=YOUR_TOKEN</code></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('route-search');
    const methodFilter = document.getElementById('method-filter');
    const rows = document.querySelectorAll('.route-row');

    function filterRoutes() {
        const query = searchInput.value.toLowerCase();
        const method = methodFilter.value;

        rows.forEach(function(row) {
            const text = row.textContent.toLowerCase();
            const rowMethods = row.querySelector('td:first-child').textContent;

            const matchesSearch = !query || text.includes(query);
            const matchesMethod = !method || rowMethods.includes(method);

            row.style.display = (matchesSearch && matchesMethod) ? '' : 'none';
        });
    }

    searchInput.addEventListener('input', filterRoutes);
    methodFilter.addEventListener('change', filterRoutes);
});
</script>
@endsection
