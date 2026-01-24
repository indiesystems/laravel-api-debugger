@extends('layouts.app')

@section('content')
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Debug Sessions</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="{{ route('api-debugger.index') }}">API Debugger</a></li>
                    <li class="breadcrumb-item active">Sessions</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="container-fluid">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                {{ session('success') }}
            </div>
        @endif

        <div class="row">
            {{-- Create New Session --}}
            <div class="col-lg-4">
                <div class="card card-outline card-primary">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-plus mr-2"></i>Create Debug Session
                        </h3>
                    </div>
                    <form action="{{ route('api-debugger.sessions.create') }}" method="POST">
                        @csrf

                        @if($errors->any())
                            <div class="alert alert-danger mx-3 mt-3">
                                <ul class="mb-0">
                                    @foreach($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <div class="card-body">
                            <div class="form-group">
                                <label>Debug Type</label>
                                <div class="custom-control custom-radio">
                                    <input type="radio" id="type-all" name="type" value="all"
                                           class="custom-control-input" {{ old('type') === 'all' ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="type-all">
                                        <strong>All Requests</strong>
                                        <small class="text-muted d-block">Logs every API request automatically</small>
                                    </label>
                                </div>
                                <div class="custom-control custom-radio mt-2">
                                    <input type="radio" id="type-tenant" name="type" value="tenant"
                                           class="custom-control-input" {{ old('type', 'tenant') === 'tenant' ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="type-tenant">
                                        <strong>Tenant</strong>
                                        <small class="text-muted d-block">Logs requests from a specific tenant</small>
                                    </label>
                                </div>
                                <div class="custom-control custom-radio mt-2">
                                    <input type="radio" id="type-user" name="type" value="user"
                                           class="custom-control-input" {{ old('type') === 'user' ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="type-user">
                                        <strong>User</strong>
                                        <small class="text-muted d-block">Logs requests from authenticated user (requires auth middleware)</small>
                                    </label>
                                </div>
                            </div>

                            <div class="form-group" id="target-field">
                                <label for="target_id" id="target-label">Tenant ID</label>
                                <input type="text" name="target_id" id="target_id"
                                       class="form-control @error('target_id') is-invalid @enderror"
                                       placeholder="Enter tenant ID" value="{{ old('target_id') }}">
                            </div>

                            <div class="form-group">
                                <label for="duration">Duration (minutes)</label>
                                <input type="number" name="duration" id="duration" class="form-control"
                                       value="{{ config('api-debugger.session.default_duration', 30) }}"
                                       min="1" max="{{ config('api-debugger.session.max_duration', 120) }}">
                                <small class="form-text text-muted">
                                    Max: {{ config('api-debugger.session.max_duration', 120) }} minutes
                                </small>
                            </div>

                            <div class="form-group">
                                <label for="label">Label (optional)</label>
                                <input type="text" name="label" id="label" class="form-control"
                                       placeholder="e.g., Debugging checkout issue">
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary btn-block">
                                <i class="fas fa-play mr-2"></i>Start Debugging
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Active Sessions --}}
            <div class="col-lg-8">
                <div class="card card-outline card-success">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-satellite-dish mr-2"></i>
                            Active Sessions ({{ $activeSessions->count() }})
                        </h3>
                    </div>
                    <div class="card-body p-0">
                        @if($activeSessions->isEmpty())
                            <div class="p-4 text-center text-muted">
                                <i class="fas fa-info-circle fa-2x mb-2"></i>
                                <p>No active debug sessions</p>
                            </div>
                        @else
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Target</th>
                                        <th>Remaining</th>
                                        <th>Logs</th>
                                        <th>Started</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($activeSessions as $session)
                                        <tr>
                                            <td>
                                                <strong>{{ $session->getTargetLabel() }}</strong>
                                                @if($session->label)
                                                    <br><small class="text-muted">{{ $session->label }}</small>
                                                @endif
                                                @if($session->token)
                                                    <div class="mt-1">
                                                        <input type="text" class="form-control form-control-sm d-inline-block"
                                                               style="width: auto; font-size: 11px;"
                                                               value="{{ $session->token }}"
                                                               id="token-{{ $session->id }}"
                                                               readonly>
                                                        <button type="button" class="btn btn-xs btn-secondary"
                                                                onclick="copyToken('{{ $session->id }}', '{{ $session->token }}')"
                                                                title="Copy token">
                                                            <i class="fas fa-copy"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-xs btn-info"
                                                                onclick="copyToken('{{ $session->id }}', 'X-Debug-Token: {{ $session->token }}')"
                                                                title="Copy as header">
                                                            Header
                                                        </button>
                                                    </div>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge badge-{{ $session->remainingMinutes() < 5 ? 'warning' : 'success' }}">
                                                    {{ $session->remainingMinutes() }} min
                                                </span>
                                            </td>
                                            <td>{{ $session->logs_count }}</td>
                                            <td>
                                                <small>{{ $session->created_at->format('M d, H:i') }}</small>
                                                @if($session->createdBy)
                                                    <br><small class="text-muted">by {{ $session->createdBy->name ?? $session->createdBy->email }}</small>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="{{ route('api-debugger.logs', ['session_id' => $session->id]) }}"
                                                       class="btn btn-info" title="View Logs">
                                                        <i class="fas fa-eye"></i>
                                                    </a>

                                                    <button type="button" class="btn btn-warning"
                                                            data-toggle="modal"
                                                            data-target="#extend-modal-{{ $session->id }}"
                                                            title="Extend">
                                                        <i class="fas fa-clock"></i>
                                                    </button>

                                                    <form action="{{ route('api-debugger.sessions.stop', $session) }}"
                                                          method="POST" class="d-inline">
                                                        @csrf
                                                        @method('PATCH')
                                                        <button type="submit" class="btn btn-danger" title="Stop">
                                                            <i class="fas fa-stop"></i>
                                                        </button>
                                                    </form>
                                                </div>

                                                {{-- Extend Modal --}}
                                                <div class="modal fade" id="extend-modal-{{ $session->id }}" tabindex="-1">
                                                    <div class="modal-dialog modal-sm">
                                                        <div class="modal-content">
                                                            <form action="{{ route('api-debugger.sessions.extend', $session) }}" method="POST">
                                                                @csrf
                                                                @method('PATCH')
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title">Extend Session</h5>
                                                                    <button type="button" class="close" data-dismiss="modal">
                                                                        <span>&times;</span>
                                                                    </button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    <div class="form-group">
                                                                        <label>Extend by (minutes)</label>
                                                                        <input type="number" name="duration" class="form-control"
                                                                               value="30" min="1"
                                                                               max="{{ config('api-debugger.session.max_duration', 120) }}">
                                                                    </div>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="submit" class="btn btn-primary">Extend</button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>
                </div>

                {{-- Recent Sessions --}}
                @if($recentSessions->isNotEmpty())
                    <div class="card card-outline card-secondary collapsed-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-history mr-2"></i>Recent Sessions
                            </h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Target</th>
                                        <th>Logs</th>
                                        <th>Stopped</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($recentSessions as $session)
                                        <tr>
                                            <td>{{ $session->getTargetLabel() }}</td>
                                            <td>{{ $session->logs_count }}</td>
                                            <td><small>{{ $session->updated_at->diffForHumans() }}</small></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="{{ route('api-debugger.logs', ['session_id' => $session->id]) }}"
                                                       class="btn btn-sm btn-info">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <form action="{{ route('api-debugger.sessions.delete', $session) }}"
                                                          method="POST" class="d-inline"
                                                          onsubmit="return confirm('Delete session and all its logs?')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-sm btn-danger">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</section>
@endsection

@section('scripts')
<script>
function copyToken(sessionId, text) {
    var input = document.getElementById('token-' + sessionId);
    var temp = document.createElement('textarea');
    temp.value = text;
    document.body.appendChild(temp);
    temp.select();
    document.execCommand('copy');
    document.body.removeChild(temp);

    // Flash feedback
    input.style.backgroundColor = '#d4edda';
    setTimeout(function() {
        input.style.backgroundColor = '';
    }, 500);
}

document.addEventListener('DOMContentLoaded', function() {
    const typeAll = document.getElementById('type-all');
    const typeTenant = document.getElementById('type-tenant');
    const typeUser = document.getElementById('type-user');
    const targetField = document.getElementById('target-field');
    const targetLabel = document.getElementById('target-label');
    const targetInput = document.getElementById('target_id');

    function toggleFields() {
        if (typeAll.checked) {
            targetField.classList.add('d-none');
            targetInput.removeAttribute('required');
        } else {
            targetField.classList.remove('d-none');
            targetInput.setAttribute('required', 'required');
            if (typeTenant.checked) {
                targetLabel.textContent = 'Tenant ID';
                targetInput.placeholder = 'Enter tenant ID';
            } else {
                targetLabel.textContent = 'User ID';
                targetInput.placeholder = 'Enter user ID';
            }
        }
    }

    typeAll.addEventListener('change', toggleFields);
    typeTenant.addEventListener('change', toggleFields);
    typeUser.addEventListener('change', toggleFields);
    toggleFields();
});
</script>
@endsection
