<div class="mb-3">
    <div class="btn-group">
        <a href="{{ route('api-debugger.index') }}"
           class="btn btn-{{ request()->routeIs('api-debugger.index') ? 'primary' : 'outline-primary' }}">
            <i class="fas fa-tachometer-alt mr-1"></i> Dashboard
        </a>
        <a href="{{ route('api-debugger.logs') }}"
           class="btn btn-{{ request()->routeIs('api-debugger.logs*') ? 'primary' : 'outline-primary' }}">
            <i class="fas fa-list mr-1"></i> Logs
        </a>
        <a href="{{ route('api-debugger.sessions') }}"
           class="btn btn-{{ request()->routeIs('api-debugger.sessions*') ? 'primary' : 'outline-primary' }}">
            <i class="fas fa-satellite-dish mr-1"></i> Sessions
        </a>
        <a href="{{ route('api-debugger.routes') }}"
           class="btn btn-{{ request()->routeIs('api-debugger.routes') ? 'primary' : 'outline-primary' }}">
            <i class="fas fa-sitemap mr-1"></i> Routes
        </a>
    </div>
</div>
