<table class="table table-hover table-striped">
    <thead>
        <tr>
            <th style="width: 100px">Time</th>
            <th style="width: 80px">Method</th>
            <th>URL</th>
            <th style="width: 80px">Status</th>
            <th style="width: 100px">Duration</th>
            <th style="width: 120px">IP</th>
            <th style="width: 120px">User</th>
        </tr>
    </thead>
    <tbody>
        @forelse($logs as $log)
            <tr class="log-row" data-log-id="{{ $log->id }}" data-json-url="{{ route('api-debugger.logs.json', $log) }}" style="cursor: pointer" onclick="showLogDetail(this)">
                <td>
                    <small>{{ $log->requested_at->format('H:i:s') }}</small>
                    <br>
                    <small class="text-muted">{{ $log->requested_at->format('m/d') }}</small>
                </td>
                <td>
                    <span class="badge badge-{{ $log->method_color }}">{{ $log->method }}</span>
                </td>
                <td class="text-truncate" style="max-width: 300px" title="{{ $log->full_url }}">
                    {{ $log->url }}
                    @if($log->route_name)
                        <br><small class="text-muted">{{ $log->route_name }}</small>
                    @endif
                </td>
                <td>
                    <span class="badge badge-{{ $log->status_color }}">{{ $log->status_code }}</span>
                    @if($log->has_exception)
                        <i class="fas fa-exclamation-triangle text-danger" title="{{ $log->exception_class }}"></i>
                    @endif
                </td>
                <td>
                    <span class="{{ $log->duration_ms > 1000 ? 'text-warning font-weight-bold' : '' }}">
                        {{ $log->formatted_duration }}
                    </span>
                </td>
                <td>
                    <small>{{ $log->ip_address }}</small>
                </td>
                <td>
                    @if($log->user)
                        <small title="{{ $log->user->email ?? '' }}">
                            {{ $log->user->name ?? $log->user->email ?? '#' . $log->user_id }}
                        </small>
                    @else
                        <small class="text-muted">Guest</small>
                    @endif
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="7" class="text-center text-muted py-4">
                    <i class="fas fa-inbox fa-2x mb-2"></i>
                    <p>No logs found</p>
                </td>
            </tr>
        @endforelse
    </tbody>
</table>
