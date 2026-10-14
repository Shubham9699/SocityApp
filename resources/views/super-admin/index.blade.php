@extends('layouts.app')

@section('title', $tab === 'activity' ? 'Activity log' : 'Society management')

@section('content')
@if ($tab === 'societies')
    <section class="page-heading">
        <div><span class="eyebrow">PLATFORM MANAGEMENT</span><h1>Societies</h1><p>Approve new society registrations, review administrators, and manage workspace access.</p></div>
    </section>
    <section class="metric-grid">
        <article class="metric-card"><div class="metric-top"><span class="metric-icon icon-orange">!</span><span class="metric-label">Pending approval</span></div><strong class="metric-value">{{ number_format($metrics['pending_societies']) }}</strong><span class="metric-note">Societies awaiting review</span></article>
        <article class="metric-card"><div class="metric-top"><span class="metric-icon icon-green">✓</span><span class="metric-label">Active societies</span></div><strong class="metric-value">{{ number_format($metrics['active_societies']) }}</strong><span class="metric-note">Approved workspaces</span></article>
        <article class="metric-card"><div class="metric-top"><span class="metric-icon icon-blue">⌁</span><span class="metric-label">Activity records</span></div><strong class="metric-value">{{ number_format($metrics['activity_count']) }}</strong><span class="metric-note">Audited platform events</span></article>
    </section>
    <section class="panel super-admin-panel">
        <div class="section-heading"><div><span class="eyebrow">SOCIETY DIRECTORY</span><h2>Registered communities</h2></div><span class="table-count">{{ $societies->total() }} societies</span></div>
        @if ($societies->isEmpty())
            <div class="empty-state"><strong>No societies registered</strong><span>New applications will appear here for review.</span></div>
        @else
            <div class="table-scroll"><table><thead><tr><th>SOCIETY</th><th>ADMINISTRATOR</th><th>MEMBERS</th><th>FLATS</th><th>BILLS</th><th>STATUS</th><th>ACCESS</th></tr></thead><tbody>
                @foreach ($societies as $society)
                    <tr>
                        <td><strong>{{ $society->name }}</strong><small>{{ $society->address ?: $society->slug }}</small></td>
                        <td>@forelse ($society->users as $admin)<strong>{{ $admin->full_name }}</strong><small>{{ $admin->email }}</small>@empty<span class="subtle-note">No admin account</span>@endforelse</td>
                        <td>{{ $society->users_count }}</td><td>{{ $society->flats_count }}</td><td>{{ $society->bills_count }}</td>
                        <td><span class="badge badge-{{ $society->status === 'active' ? 'paid' : ($society->status === 'pending' ? 'unpaid' : 'overdue') }}">{{ str_replace('_', ' ', $society->status) }}</span></td>
                        <td>
                            @if ($society->status === 'active')
                                <form method="post" action="{{ route('super-admin.societies.switch', $society) }}">@csrf
                                    <button class="button button-small button-primary" type="submit">Open workspace</button>
                                </form>
                            @endif
                            @if ($society->status !== 'active')
                                <form method="post" action="{{ route('super-admin.societies.status', $society) }}">@csrf @method('PATCH')
                                    <input type="hidden" name="status" value="active">
                                    <button class="button button-small button-primary" type="submit">{{ $society->status === 'pending' ? 'Approve society' : 'Reactivate' }}</button>
                                </form>
                            @else
                                <form method="post" action="{{ route('super-admin.societies.status', $society) }}">@csrf @method('PATCH')
                                    <input type="hidden" name="status" value="suspended">
                                    <button class="button button-small button-soft" type="submit">Suspend access</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody></table></div>
            {{ $societies->links() }}
        @endif
    </section>
@else
    <section class="page-heading">
        <div><span class="eyebrow">AUDIT TRAIL</span><h1>Activity log</h1><p>Recent sign-ins and society-management changes across all workspaces.</p></div>
    </section>
    <section class="panel super-admin-panel">
        <div class="section-heading"><div><span class="eyebrow">ALL SOCIETIES</span><h2>Recorded activity</h2></div><span class="table-count">{{ $activityLogs->total() }} records</span></div>
        @if ($activityLogs->isEmpty())
            <div class="empty-state"><strong>No activity recorded yet</strong><span>Changes and account activity will appear here.</span></div>
        @else
            <div class="activity-log-list">
                @foreach ($activityLogs as $entry)
                    <article class="activity-log-row">
                        <span class="activity-log-icon">{{ strtoupper(substr($entry->action, 0, 1)) }}</span>
                        <div class="activity-log-copy">
                            <strong>{{ $entry->description }}</strong>
                            <span>{{ $entry->actor?->full_name ?? 'System / unregistered user' }} · {{ $entry->society?->name ?? 'Platform-wide' }}</span>
                            @if ($entry->changed_fields)<small>Changed: {{ implode(', ', $entry->changed_fields) }}</small>@endif
                        </div>
                        <time datetime="{{ $entry->created_at->toIso8601String() }}">{{ $entry->created_at->format('d M Y, g:i A') }}</time>
                    </article>
                @endforeach
            </div>
            {{ $activityLogs->links() }}
        @endif
    </section>
@endif
@endsection
