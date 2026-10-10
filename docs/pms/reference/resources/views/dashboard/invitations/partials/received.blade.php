@if ($receivedInvitations->isEmpty())
    <div class="alert alert-secondary">No received invitations</div>
@else
    <div class="row">
        @foreach ($receivedInvitations as $invitation)
            <div class="col-12 mb-2">
                <div class="card shadow-sm">
                    <div class="card-body d-flex justify-content-between align-items-start flex-wrap">
                        <div class="me-2">
                            <h5 class="mb-1">
                                Invitation to {{ class_basename($invitation->invitable_type) }}:
                                {{ $invitation->invitable->name ?? ($invitation->invitable->title ?? '') }}
                            </h5>
                            <p class="mb-0"><strong>From:</strong> {{ $invitation->sender->name }}</p>
                            <p class="mb-0"><strong>Sent:</strong> {{ $invitation->created_at->diffForHumans() }}</p>
                            <p class="mb-0">
                                <strong>Status:</strong>
                                <span class="badge bg-{{ $invitation->status_color }}">
                                    {{ $invitation->status }}
                                </span>
                            </p>
                        </div>
                        <div class="mt-1 mt-md-0 text-end">
                            @if ($invitation->isPending())
                                <a href="{{ route('invitations.accept', $invitation->token) }}"
                                    class="btn btn-success btn-sm me-1">Accept</a>
                                <a href="{{ route('invitations.decline', $invitation->token) }}"
                                    class="btn btn-danger btn-sm">Decline</a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="mt-3">
        {!! $receivedInvitations->withQueryString()->links() !!}
    </div>
@endif
