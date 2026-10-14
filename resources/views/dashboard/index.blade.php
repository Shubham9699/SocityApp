@extends('layouts.app')

@section('title', ucfirst($tab === 'dashboard' ? 'Overview' : $tab))

@section('content')
@php($isSocietyAdmin = in_array($user->role, ['admin', 'super_admin'], true))
@if ($tab === 'dashboard')
    <section class="welcome-row">
        <div><span class="eyebrow">{{ now()->format('l, d F Y') }}</span><h1>{{ $isSocietyAdmin ? 'Good day' : 'Welcome home' }}, {{ explode(' ', $user->full_name)[0] }} <span class="wave">✦</span></h1><p>{{ $isSocietyAdmin ? 'Here is what is happening across your community today.' : 'Here is your society account at a glance.' }}</p></div>
        @if ($isSocietyAdmin)<a class="button button-primary" href="{{ route('dashboard', ['tab' => 'billing']) }}">Manage society <span>→</span></a>@else<a class="button button-primary" href="{{ route('dashboard', ['tab' => 'complaints']) }}">Get support <span>→</span></a>@endif
    </section>

    @if ($isSocietyAdmin)
        <section class="metric-grid">
            <article class="metric-card"><div class="metric-top"><span class="metric-icon icon-blue">₹</span><span class="metric-label">Pending dues</span></div><strong class="metric-value">₹{{ number_format($metrics['pending_dues']) }}</strong><span class="metric-note">{{ $metrics['unpaid_bills'] }} unpaid or overdue bills</span></article>
            <article class="metric-card"><div class="metric-top"><span class="metric-icon icon-green">↗</span><span class="metric-label">Collected this month</span></div><strong class="metric-value">₹{{ number_format($metrics['monthly_collected']) }}</strong><span class="metric-note">Payments recorded this month</span></article>
            <article class="metric-card"><div class="metric-top"><span class="metric-icon icon-orange">⌁</span><span class="metric-label">Open complaints</span></div><strong class="metric-value">{{ number_format($metrics['open_complaints']) }}</strong><span class="metric-note">Submitted or in progress</span></article>
            <article class="metric-card"><div class="metric-top"><span class="metric-icon icon-purple">♧</span><span class="metric-label">Registered flats</span></div><strong class="metric-value">{{ number_format($metrics['total_flats']) }}</strong><span class="metric-note">{{ $metrics['active_notices'] }} active society notices</span></article>
        </section>
        <section class="content-grid">
            <article class="panel panel-wide">
                <div class="section-heading"><div><span class="eyebrow">NEEDS ATTENTION</span><h2>Recent complaints</h2></div><a class="text-link" href="{{ route('dashboard', ['tab' => 'complaints']) }}">View helpdesk →</a></div>
                @forelse ($complaints as $complaint)
                    <div class="list-row"><span class="row-icon icon-orange">⌁</span><div class="row-main"><strong>{{ $complaint->subject }}</strong><small>{{ $complaint->ticket_number }} · {{ $complaint->resident->full_name }} · {{ $complaint->flat->wing }}-{{ $complaint->flat->flat_number }}</small></div><span class="badge badge-{{ $complaint->status }}">{{ str_replace('_', ' ', $complaint->status) }}</span></div>
                @empty
                    <div class="empty-state"><strong>You're all caught up</strong><span>No complaints need attention right now.</span></div>
                @endforelse
            </article>
            <article class="panel">
                <div class="section-heading"><div><span class="eyebrow">COMMUNITY</span><h2>Latest notices</h2></div><a class="text-link" href="{{ route('dashboard', ['tab' => 'notices']) }}">All notices →</a></div>
                @forelse ($notices as $notice)
                    <div class="notice-mini"><span class="notice-dot priority-{{ $notice->priority }}"></span><div><strong>{{ $notice->title }}</strong><small>{{ $notice->scheduled_publish_at->format('d M, g:i A') }}</small></div></div>
                @empty
                    <div class="empty-state"><strong>No notices yet</strong><span>Society announcements appear here.</span></div>
                @endforelse
            </article>
        </section>
    @else
        <section class="metric-grid resident-metrics">
            <article class="metric-card"><div class="metric-top"><span class="metric-icon icon-blue">₹</span><span class="metric-label">Current month bill</span></div><strong class="metric-value">₹{{ number_format((float) ($bills->first()?->total_amount ?? 0)) }}</strong><span class="metric-note">{{ $flat ? 'Flat '.$flat->wing.'-'.$flat->flat_number : 'No flat assigned' }}</span></article>
            <article class="metric-card"><div class="metric-top"><span class="metric-icon icon-orange">⌁</span><span class="metric-label">My open requests</span></div><strong class="metric-value">{{ $complaints->whereIn('status', ['submitted', 'in_progress'])->count() }}</strong><span class="metric-note">Track your maintenance issues</span></article>
            <article class="metric-card"><div class="metric-top"><span class="metric-icon icon-purple">◉</span><span class="metric-label">Community notices</span></div><strong class="metric-value">{{ $metrics['active_notices'] }}</strong><span class="metric-note">Updates from your society office</span></article>
            <article class="metric-card"><div class="metric-top"><span class="metric-icon icon-green">♧</span><span class="metric-label">Your home</span></div><strong class="metric-value metric-home">{{ $flat ? $flat->wing.'-'.$flat->flat_number : '—' }}</strong><span class="metric-note">{{ $flat ? number_format($flat->carpet_sqft).' sq. ft.' : 'Contact the society office' }}</span></article>
        </section>
        <section class="content-grid">
            <article class="panel panel-wide">
                <div class="section-heading"><div><span class="eyebrow">YOUR ACCOUNT</span><h2>Recent bills</h2></div><a class="text-link" href="{{ route('dashboard', ['tab' => 'billing']) }}">View billing →</a></div>
                @forelse ($bills as $bill)
                    <div class="list-row"><span class="row-icon icon-blue">₹</span><div class="row-main"><strong>{{ $bill->bill_period->format('F Y') }} maintenance</strong><small>{{ $bill->bill_number }} · Due {{ $bill->due_date->format('d M Y') }}</small></div><strong class="row-amount">₹{{ number_format((float) $bill->total_amount) }}</strong><span class="badge badge-{{ $bill->payment_status }}">{{ str_replace('_', ' ', $bill->payment_status) }}</span></div>
                @empty<div class="empty-state"><strong>No bills yet</strong><span>Your maintenance bills will show here.</span></div>@endforelse
            </article>
            <article class="panel">
                <div class="section-heading"><div><span class="eyebrow">HELPDESK</span><h2>Recent requests</h2></div><a class="text-link" href="{{ route('dashboard', ['tab' => 'complaints']) }}">View all →</a></div>
                @forelse ($complaints as $complaint)
                    <div class="notice-mini"><span class="notice-dot priority-{{ $complaint->priority }}"></span><div><strong>{{ $complaint->subject }}</strong><small>{{ $complaint->ticket_number }} · {{ str_replace('_', ' ', $complaint->status) }}</small></div></div>
                @empty<div class="empty-state"><strong>All good?</strong><span>New requests can be created in the helpdesk.</span></div>@endforelse
            </article>
        </section>
    @endif

@elseif ($tab === 'billing')
    <section class="page-heading"><div><span class="eyebrow">MAINTENANCE ACCOUNTS</span><h1>Billing & payments</h1><p>Review charges, due dates and payment history.</p></div></section>
    @if ($isSocietyAdmin)
        <details class="panel billing-tools">
            <summary><span><strong>Billing tools</strong><small>Generate monthly or individual bills, or import records</small></span><span class="billing-tools-toggle">Manage</span></summary>
            <div class="billing-tool-grid">
        <section class="panel form-panel">
            <div class="section-heading"><div><span class="eyebrow">BATCH INVOICING</span><h2>Generate monthly bills</h2><p>Create bills for {{ $metrics['total_flats'] }} registered flats. Existing bills are left unchanged.</p></div></div>
            <form method="post" action="{{ route('bills.generate') }}" class="inline-form bill-generator">@csrf
                <div><label for="bill_period">Billing month</label><input id="bill_period" name="bill_period" type="month" value="{{ old('bill_period', now()->startOfMonth()->addMonth()->format('Y-m')) }}" required></div>
                <div><label for="due_date">Payment due date</label><input id="due_date" name="due_date" type="date" value="{{ old('due_date', now()->startOfMonth()->addMonth()->day(15)->format('Y-m-d')) }}" required></div>
                <button class="button button-primary" type="submit">Generate bills <span>→</span></button>
            </form>
        </section>
        <section class="panel form-panel">
            <div class="section-heading"><div><span class="eyebrow">INDIVIDUAL INVOICING</span><h2>Generate a bill for one owner</h2><p>Create a bill for one selected flat. Uses its saved maintenance amount plus standard utility and sinking-fund charges.</p></div></div>
            <form method="post" action="{{ route('bills.generate-single') }}" class="inline-form bill-generator">@csrf
                <div><label for="single_bill_flat">Owner / flat</label><select id="single_bill_flat" name="flat_id" required>
                    <option value="">Choose an owner and flat</option>
                    @foreach ($allFlats as $ownerFlat)
                        <option value="{{ $ownerFlat->id }}" @selected((string) old('flat_id') === (string) $ownerFlat->id)>{{ $ownerFlat->owner->full_name }} · {{ $ownerFlat->wing }}-{{ $ownerFlat->flat_number }}</option>
                    @endforeach
                </select></div>
                <div><label for="single_bill_period">Billing month</label><input id="single_bill_period" name="bill_period" type="month" value="{{ old('bill_period', now()->startOfMonth()->addMonth()->format('Y-m')) }}" required></div>
                <div><label for="single_bill_due_date">Payment due date</label><input id="single_bill_due_date" name="due_date" type="date" value="{{ old('due_date', now()->startOfMonth()->addMonth()->day(15)->format('Y-m-d')) }}" required></div>
                <button class="button button-primary" type="submit">Generate single bill <span>→</span></button>
            </form>
        </section>
        <section class="panel form-panel">
            <div class="section-heading"><div><span class="eyebrow">MONTHLY IMPORT</span><h2>Upload maintenance and payments</h2><p>Import charges and optional received-payment details for each flat. All rows are checked before anything is saved.</p></div></div>
            <form method="post" action="{{ route('bills.import') }}" enctype="multipart/form-data" class="inline-form">@csrf
                <div><label for="maintenance_csv">Maintenance CSV file</label><input id="maintenance_csv" name="file" type="file" accept=".csv,text/csv" required></div>
                <button class="button button-primary" type="submit">Upload monthly records <span>→</span></button>
            </form>
            <p class="subtle-note">Use the <a class="text-link" href="{{ route('bills.import-template') }}">blank maintenance CSV template</a> or <a class="text-link" href="{{ route('bills.import-sample') }}">download a sample with example data</a>. The sample uses flat Z-999; replace it with real flat details before importing. Open in Excel and save as CSV before uploading. Payment rows marked paid must include a payment method. Imports are all-or-nothing.</p>
        </section>
            </div>
        </details>
    @endif
    <section class="billing-history">
        <div class="section-heading billing-history-heading"><div><span class="eyebrow">{{ $isSocietyAdmin ? 'SOCIETY ACCOUNTS' : 'YOUR ACCOUNT' }}</span><h2>{{ $isSocietyAdmin ? 'All maintenance bills' : 'Your bill history' }}</h2></div><span class="table-count">{{ $bills->count() }} bills</span></div>
        @if ($bills->isEmpty())
            <div class="panel empty-state"><strong>No bills found</strong><span>{{ $isSocietyAdmin ? 'Generate bills for the society to get started.' : 'Your bills will appear here once generated.' }}</span></div>
        @else
            <div class="bill-card-list">
                @foreach ($bills as $bill)
                    <article class="panel bill-card">
                        <div class="bill-card-heading">
                            <div><span class="eyebrow">{{ $bill->bill_period->format('F Y') }} BILL</span><h3>{{ $bill->bill_number }}</h3>
                                @if ($isSocietyAdmin)<p>{{ $bill->flat->owner->full_name }} · {{ $bill->flat->wing }}-{{ $bill->flat->flat_number }}</p>@endif
                            </div>
                            <span class="badge badge-{{ $bill->payment_status }}">{{ $bill->payment_submitted_at && $bill->payment_status !== 'paid' ? 'awaiting verification' : str_replace('_', ' ', $bill->payment_status) }}</span>
                        </div>
                        <div class="bill-card-details">
                            <div class="bill-total"><span>Total amount</span><strong>₹{{ number_format((float) $bill->total_amount, 2) }}</strong></div>
                            <div><span>Due date</span><strong>{{ $bill->due_date->format('d M Y') }}</strong></div>
                            <div><span>Maintenance</span><strong>₹{{ number_format((float) $bill->base_maintenance, 2) }}</strong></div>
                            <div><span>Other charges</span><strong>₹{{ number_format((float) $bill->utility_charges + (float) $bill->sinking_fund + (float) $bill->penalty_fine, 2) }}</strong></div>
                        </div>
                        @if ($bill->notes)<p class="bill-notes">{{ $bill->notes }}</p>@endif
                        <div class="bill-card-actions">
                            <a class="button button-soft" href="{{ route('bills.download', $bill) }}">Download bill PDF</a>
                            <div class="bill-payment-area">
                            @if ($user->role === 'resident' && $bill->payment_review_note)<p class="payment-review-note"><strong>Admin note:</strong> {{ $bill->payment_review_note }}</p>@endif
                            @if ($isSocietyAdmin && $bill->payment_submitted_at && $bill->payment_status !== 'paid')
                                <div class="payment-submission"><strong>Payment proof submitted</strong><span>{{ strtoupper($bill->payment_method ?? 'payment submitted') }} · {{ $bill->payment_transaction_id ?: 'No transaction reference' }}</span>
                                    @if ($bill->payment_proof_path)<a class="text-link" href="{{ route('bills.payment-proof', $bill) }}">View screenshot</a>@endif
                                </div>
                                <div class="payment-review-actions">
                                    <form method="post" action="{{ route('bills.review-payment', $bill) }}">@csrf
                                        <input type="hidden" name="decision" value="approve">
                                        <button class="button button-primary" type="submit">Verify payment</button>
                                    </form>
                                    <form method="post" action="{{ route('bills.review-payment', $bill) }}" class="payment-reject-form">@csrf
                                        <input type="hidden" name="decision" value="reject">
                                        <input name="review_note" type="text" maxlength="1000" placeholder="Reason for rejection" aria-label="Reason for rejecting payment proof" required>
                                        <button class="button button-soft" type="submit">Reject</button>
                                    </form>
                                </div>
                            @elseif ($bill->payment_status === 'paid')
                                <div class="payment-submission"><strong>Paid</strong><span>{{ strtoupper($bill->payment_method ?? 'payment recorded') }}@if ($bill->payment_transaction_id) · {{ $bill->payment_transaction_id }}@endif</span>
                                    @if ($bill->payment_proof_path)<a class="text-link" href="{{ route('bills.payment-proof', $bill) }}">View payment screenshot</a>@endif
                                </div>
                            @elseif ($user->role === 'resident' && $bill->payment_submitted_at)
                                <div class="payment-submission payment-pending"><strong>Proof submitted · awaiting verification</strong><span>{{ strtoupper($bill->payment_method ?? 'payment submitted') }}@if ($bill->payment_transaction_id) · {{ $bill->payment_transaction_id }}@endif</span>
                                    @if ($bill->payment_proof_path)<a class="text-link" href="{{ route('bills.payment-proof', $bill) }}">View submitted screenshot</a>@endif
                                </div>
                            @else
                                <details class="payment-submit">
                                    <summary>{{ $user->role === 'resident' ? 'Pay / submit payment proof' : 'Record payment' }}</summary>
                                    <form method="post" action="{{ route('bills.pay', $bill) }}" enctype="multipart/form-data" class="payment-form">@csrf
                                        <div><label for="payment_method_{{ $bill->id }}">Payment method</label><select id="payment_method_{{ $bill->id }}" name="payment_method" required><option value="upi">UPI</option><option value="netbanking">Netbanking</option><option value="card">Card</option><option value="cheque">Cheque</option><option value="cash">Cash</option></select></div>
                                        <div><label for="payment_reference_{{ $bill->id }}">Transaction/reference ID</label><input id="payment_reference_{{ $bill->id }}" name="payment_transaction_id" type="text" maxlength="100" placeholder="Enter payment reference"></div>
                                        @if ($user->role === 'resident')
                                            <div><label for="payment_proof_{{ $bill->id }}">Payment screenshot (optional)</label><input id="payment_proof_{{ $bill->id }}" name="payment_proof" type="file" accept="image/jpeg,image/png,image/webp"><small>JPG, PNG, or WebP · max 5 MB</small></div>
                                            <p class="subtle-note">Provide a transaction ID, screenshot, or both. The bill stays unpaid until an admin verifies the proof.</p>
                                            <button class="button button-primary" type="submit">Submit for verification</button>
                                        @else
                                            <button class="button button-primary" type="submit">Record payment</button>
                                        @endif
                                    </form>
                                </details>
                            @endif
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
            @if ($user->role === 'resident')<p class="subtle-note">Submitting proof does not transfer money. Complete payment using your bank or UPI app first. An admin reviews the proof before marking your bill paid.</p>@endif
        @endif
    </section>

@elseif ($tab === 'complaints')
    <section class="page-heading"><div><span class="eyebrow">RESIDENT SUPPORT</span><h1>Maintenance helpdesk</h1><p>Submit an issue and follow its progress with the society team.</p></div></section>
    @if ($user->role === 'resident')
        <section class="panel form-panel">
            <div class="section-heading"><div><span class="eyebrow">NEW REQUEST</span><h2>Tell us what needs attention</h2><p>Your request will be linked to {{ $flat ? $flat->wing.'-'.$flat->flat_number : 'your account' }}.</p></div></div>
            <form method="post" action="{{ route('complaints.store') }}" class="stack-form form-two-col">@csrf
                <div><label for="category">Issue category</label><select id="category" name="category" required><option value="plumbing">Plumbing</option><option value="electrical">Electrical</option><option value="lift">Lift & elevator</option><option value="security">Security & parking</option><option value="general">General maintenance</option></select></div>
                <div><label for="priority">Urgency</label><select id="priority" name="priority" required><option value="low">Low</option><option value="medium" selected>Medium</option><option value="high">High</option><option value="urgent">Urgent</option></select></div>
                <div class="form-full"><label for="subject">Subject</label><input id="subject" name="subject" type="text" maxlength="200" placeholder="Briefly describe the issue" required></div>
                <div class="form-full"><label for="description">Details</label><textarea id="description" name="description" rows="3" maxlength="5000" placeholder="Add location and any helpful details" required></textarea></div>
                <div class="form-full"><button class="button button-primary" type="submit">Submit request <span>→</span></button></div>
            </form>
        </section>
    @endif
    <section class="panel">
        <div class="section-heading"><div><span class="eyebrow">SERVICE TRACKER</span><h2>{{ $isSocietyAdmin ? 'Society complaints' : 'Your requests' }}</h2></div><span class="table-count">{{ $complaints->count() }} requests</span></div>
        @forelse ($complaints as $complaint)
            <article class="complaint-card">
                <div class="complaint-head"><div><span class="eyebrow">{{ $complaint->ticket_number }} · {{ strtoupper($complaint->category) }}</span><h3>{{ $complaint->subject }}</h3></div><div class="badge-stack"><span class="badge badge-{{ $complaint->priority }}">{{ $complaint->priority }} priority</span><span class="badge badge-{{ $complaint->status }}">{{ str_replace('_', ' ', $complaint->status) }}</span></div></div>
                <p>{{ $complaint->description }}</p>
                <div class="complaint-meta"><span>{{ $complaint->resident->full_name }} · {{ $complaint->flat->wing }}-{{ $complaint->flat->flat_number }}</span><span>Opened {{ $complaint->created_at->format('d M Y') }}</span><span>Assigned to {{ $complaint->assignee?->full_name ?? 'Society team' }}</span></div>
                @if ($complaint->logs->isNotEmpty())
                    <details class="timeline"><summary>View status history</summary><div class="timeline-list">@foreach ($complaint->logs as $log)<div class="timeline-row"><span class="timeline-dot"></span><div><strong>{{ $log->actor->full_name }} · {{ str_replace('_', ' ', $log->new_status) }}</strong><p>{{ $log->remarks }}</p><small>{{ $log->created_at->format('d M Y, g:i A') }}</small></div></div>@endforeach</div></details>
                @endif
                @if (in_array($user->role, ['admin', 'staff'], true) && ! in_array($complaint->status, ['closed'], true))
                    <form method="post" action="{{ route('complaints.update', $complaint) }}" class="inline-form status-form">@csrf @method('PATCH')
                        <div><label for="status-{{ $complaint->id }}">Update status</label><select id="status-{{ $complaint->id }}" name="status"><option value="submitted" @selected($complaint->status === 'submitted')>Submitted</option><option value="in_progress" @selected($complaint->status === 'in_progress')>In progress</option><option value="resolved" @selected($complaint->status === 'resolved')>Resolved</option><option value="closed" @selected($complaint->status === 'closed')>Closed</option></select></div>
                        <div><label for="assignee-{{ $complaint->id }}">Assign to</label><select id="assignee-{{ $complaint->id }}" name="assigned_to_user_id"><option value="">No change</option>@foreach ($staff as $member)<option value="{{ $member->id }}" @selected($complaint->assigned_to_user_id === $member->id)>{{ $member->full_name }}</option>@endforeach</select></div>
                        <div class="status-remarks"><label for="remarks-{{ $complaint->id }}">Update note</label><input id="remarks-{{ $complaint->id }}" name="remarks" type="text" maxlength="2000" placeholder="Add a note for the resident" required></div>
                        <button class="button button-small button-primary" type="submit">Save update</button>
                    </form>
                @endif
            </article>
        @empty
            <div class="empty-state"><strong>No requests yet</strong><span>Maintenance requests will appear here after submission.</span></div>
        @endforelse
    </section>

@elseif ($tab === 'notices')
    <section class="page-heading"><div><span class="eyebrow">SOCIETY UPDATES</span><h1>Notice board</h1><p>Important information and announcements for the {{ $activeSociety?->name ?? $user->society?->name ?? 'community' }} community.</p></div></section>
    @if ($isSocietyAdmin)
        <section class="panel form-panel">
            <div class="section-heading"><div><span class="eyebrow">ADMIN CIRCULAR</span><h2>Publish a notice</h2><p>Share an announcement with residents.</p></div></div>
            <form method="post" action="{{ route('notices.store') }}" class="stack-form form-two-col">@csrf
                <div><label for="notice_title">Notice title</label><input id="notice_title" name="title" type="text" maxlength="255" required></div>
                <div class="form-grid">
                    <div><label for="notice_category">Category</label><select id="notice_category" name="category"><option value="water_supply">Water supply</option><option value="maintenance">Maintenance</option><option value="meeting">Meeting</option><option value="security">Security</option><option value="event">Event</option><option value="general">General</option></select></div>
                    <div><label for="notice_priority">Priority</label><select id="notice_priority" name="priority"><option value="urgent">Urgent</option><option value="high">High</option><option value="normal" selected>Normal</option><option value="low">Low</option></select></div>
                </div>
                <div class="form-full"><label for="notice_content">Notice content</label><textarea id="notice_content" name="content" rows="3" maxlength="10000" required></textarea></div>
                <label class="check-line"><input type="checkbox" name="pinned" value="1"> Pin this notice to the top</label>
                <div><button class="button button-primary" type="submit">Publish notice <span>→</span></button></div>
            </form>
        </section>
    @endif
    <section class="panel">
        <div class="section-heading"><div><span class="eyebrow">COMMUNITY FEED</span><h2>Latest announcements</h2></div><span class="table-count">{{ $notices->count() }} notices</span></div>
        <div class="notice-filters"><input id="noticeSearch" type="search" placeholder="Search notices…" aria-label="Search notices"><select id="noticeCategory" aria-label="Filter notice category"><option value="">All categories</option><option value="water_supply">Water supply</option><option value="maintenance">Maintenance</option><option value="meeting">Meeting</option><option value="security">Security</option><option value="event">Event</option><option value="general">General</option></select></div>
        <div class="notice-grid" id="noticeGrid">
            @forelse ($notices as $notice)
                <article class="notice-card" data-category="{{ $notice->category }}" data-search="{{ strtolower($notice->title.' '.$notice->content) }}">
                    <div class="notice-card-top"><span class="notice-category">{{ str_replace('_', ' ', $notice->category) }}</span><span class="badge badge-{{ $notice->priority }}">{{ $notice->priority }}</span>@if ($notice->pinned)<span class="pinned-label">PINNED</span>@endif</div>
                    <h3>{{ $notice->title }}</h3><p>{{ $notice->content }}</p>
                    <div class="notice-byline"><span>{{ $notice->author->full_name }}</span><span>{{ $notice->scheduled_publish_at->format('d M Y') }}</span></div>
                </article>
            @empty<div class="empty-state"><strong>No active notices</strong><span>New society updates will appear here.</span></div>@endforelse
        </div>
        <p class="empty-search" id="noticeEmpty" hidden>No notices match that search.</p>
    </section>

@elseif ($tab === 'directory')
    <section class="page-heading"><div><span class="eyebrow">{{ strtoupper($activeSociety?->name ?? $user->society?->name ?? 'SOCIETY') }} COMMUNITY</span><h1>Resident directory</h1><p>Find residents and their registered flats.</p></div></section>
    @if ($user->role === 'resident' && $flat)
        <section class="panel form-panel">
            <div class="section-heading"><div><span class="eyebrow">YOUR FLAT · {{ $flat->wing }}-{{ $flat->flat_number }}</span><h2>{{ $flat->tenant ? 'Manage tenant details' : 'Add a tenant' }}</h2><p>Save the current tenant's contact information. Tenants do not receive a portal login.</p></div></div>
            <form method="post" action="{{ route('tenant.store') }}" enctype="multipart/form-data" class="stack-form form-two-col">@csrf
                <div><label for="tenant_name">Tenant full name</label><input id="tenant_name" name="full_name" type="text" maxlength="100" value="{{ old('full_name', $flat->tenant?->full_name) }}" required></div>
                <div><label for="tenant_phone">Tenant phone</label><input id="tenant_phone" name="phone" type="text" maxlength="20" value="{{ old('phone', $flat->tenant?->phone) }}" required></div>
                <div><label for="tenant_email">Tenant email (optional)</label><input id="tenant_email" name="email" type="email" maxlength="150" value="{{ old('email', $flat->tenant?->email) }}"></div>
                <div><label for="tenant_agreement">Rent agreement (PDF, max 10 MB)</label><input id="tenant_agreement" name="agreement" type="file" accept="application/pdf,.pdf"><small>{{ $flat->tenant?->agreement_path ? 'Choose a new PDF only if you want to replace the current agreement.' : 'Upload an agreement PDF if available.' }}</small></div>
                <div><button class="button button-primary" type="submit">{{ $flat->tenant ? 'Update tenant' : 'Add tenant' }} <span>→</span></button></div>
            </form>
            @if ($flat->tenant)
                @if ($flat->tenant->agreement_path)
                    <p class="subtle-note"><a class="text-link" href="{{ route('tenant-agreements.download', $flat->tenant) }}">Download current rent agreement (PDF)</a></p>
                @endif
                <form method="post" action="{{ route('tenant.destroy') }}" class="tenant-remove-form">@csrf @method('DELETE')
                    <button class="button button-soft" type="submit">Remove tenant</button>
                </form>
            @endif
        </section>
    @endif
    @if ($isSocietyAdmin)
        <section class="panel form-panel">
            <div class="section-heading"><div><span class="eyebrow">NEW OWNER</span><h2>Add resident manually</h2><p>Create the owner login and assign their flat.</p></div></div>
            <form method="post" action="{{ route('residents.store') }}" class="stack-form form-two-col">@csrf
                <div><label for="owner_name">Full name</label><input id="owner_name" name="full_name" type="text" maxlength="100" value="{{ old('full_name') }}" required></div>
                <div><label for="owner_email">Email</label><input id="owner_email" name="email" type="email" maxlength="150" value="{{ old('email') }}" required></div>
                <div><label for="owner_phone">Phone</label><input id="owner_phone" name="phone" type="text" maxlength="20" value="{{ old('phone') }}" required></div>
                <div><label for="owner_password">Initial password</label><input id="owner_password" name="password" type="password" minlength="8" required></div>
                <div class="form-grid">
                    <div><label for="owner_wing">Wing</label><input id="owner_wing" name="wing" type="text" maxlength="10" value="{{ old('wing') }}" required></div>
                    <div><label for="owner_flat">Flat number</label><input id="owner_flat" name="flat_number" type="text" maxlength="20" value="{{ old('flat_number') }}" required></div>
                </div>
                <div class="form-grid">
                    <div><label for="owner_floor">Floor</label><input id="owner_floor" name="floor" type="number" min="1" value="{{ old('floor', 1) }}"></div>
                    <div><label for="owner_area">Carpet area (sq. ft.)</label><input id="owner_area" name="carpet_sqft" type="number" min="1" value="{{ old('carpet_sqft', 950) }}"></div>
                </div>
                <div class="form-grid">
                    <div><label for="owner_occupancy">Occupancy</label><select id="owner_occupancy" name="occupancy_status"><option value="owner_occupied">Owner occupied</option><option value="tenant_occupied">Tenant occupied</option><option value="vacant">Vacant</option></select></div>
                    <div><label for="owner_parking">Parking slot</label><input id="owner_parking" name="parking_slot" type="text" maxlength="30" value="{{ old('parking_slot') }}"></div>
                </div>
                <div><label for="owner_maintenance">Monthly maintenance amount</label><input id="owner_maintenance" name="monthly_maintenance_amount" type="number" min="0" step="0.01" value="{{ old('monthly_maintenance_amount', 3500) }}" required></div>
                <div><button class="button button-primary" type="submit">Add resident <span>→</span></button></div>
            </form>
        </section>
        <section class="panel form-panel">
            <div class="section-heading"><div><span class="eyebrow">BULK IMPORT</span><h2>Upload residents from Excel</h2><p>Imports up to 500 residents with their flat details and initial passwords.</p></div></div>
            <form method="post" action="{{ route('residents.import') }}" enctype="multipart/form-data" class="inline-form">@csrf
                <div><label for="residents_csv">Resident CSV file</label><input id="residents_csv" name="file" type="file" accept=".csv,text/csv" required></div>
                <button class="button button-primary" type="submit">Upload residents <span>→</span></button>
            </form>
            <p class="subtle-note">Download the <a class="text-link" href="{{ route('residents.import-template') }}">blank resident CSV template</a> or <a class="text-link" href="{{ route('residents.import-sample') }}">sample CSV with example data</a>. Replace all sample data, including its password, before importing. Open in Excel and save as CSV. Keep all columns; name, email, phone, password, wing and flat number are required. The whole import is rejected if any row has an error.</p>
        </section>
    @endif
    @if ($isSocietyAdmin)
        <section class="panel">
            <div class="section-heading"><div><span class="eyebrow">TENANCY RECORDS</span><h2>All tenant details & agreements</h2><p>Current tenant contacts and uploaded rent agreements across the society.</p></div><span class="table-count">{{ $tenants->count() }} tenants</span></div>
            @if ($tenants->isEmpty())
                <div class="empty-state"><strong>No tenant records yet</strong><span>Tenant details and agreements added by owners will appear here.</span></div>
            @else
                <div class="table-scroll"><table><thead><tr><th>TENANT</th><th>FLAT</th><th>OWNER</th><th>PHONE</th><th>EMAIL</th><th>AGREEMENT</th></tr></thead><tbody>
                    @foreach ($tenants as $tenant)
                        <tr>
                            <td><strong>{{ $tenant->full_name }}</strong></td>
                            <td>{{ $tenant->flat->wing }}-{{ $tenant->flat->flat_number }}</td>
                            <td>{{ $tenant->flat->owner->full_name }}<small>{{ $tenant->flat->owner->email }}</small></td>
                            <td>{{ $tenant->phone }}</td>
                            <td>{{ $tenant->email ?: '—' }}</td>
                            <td>@if ($tenant->agreement_path)<a class="text-link" href="{{ route('tenant-agreements.download', $tenant) }}">Download PDF</a>@else<span class="subtle-note">Not uploaded</span>@endif</td>
                        </tr>
                    @endforeach
                </tbody></table></div>
            @endif
        </section>
    @endif
    <section class="panel">
        <div class="section-heading"><div><span class="eyebrow">MEMBERS</span><h2>Society directory</h2></div><span class="table-count">{{ $members->count() }} members</span></div>
        <div class="notice-filters"><input id="directorySearch" type="search" placeholder="Search name, flat or email…" aria-label="Search directory"></div>
        <div class="table-scroll"><table id="directoryTable"><thead><tr><th>MEMBER</th><th>FLAT</th><th>PHONE</th><th>EMAIL</th><th>ROLE</th></tr></thead><tbody>
            @foreach ($members as $member)
                <tr data-search="{{ strtolower($member->full_name.' '.$member->email.' '.$member->phone.' '.($member->flat ? $member->flat->wing.'-'.$member->flat->flat_number : '')) }}"><td><strong>{{ $member->full_name }}</strong><small>{{ ucfirst($member->status) }}</small></td><td>{{ $member->flat ? $member->flat->wing.'-'.$member->flat->flat_number : '—' }}</td><td>{{ $member->phone }}</td><td>{{ $member->email }}</td><td><span class="role-label">{{ $member->role }}</span></td></tr>
            @endforeach
        </tbody></table></div>
        <p class="empty-search" id="directoryEmpty" hidden>No residents match that search.</p>
    </section>

@else
    <section class="page-heading"><div><span class="eyebrow">TECHNOLOGY</span><h1>About this application</h1><p>A server-rendered society portal built on Laravel.</p></div></section>
    <section class="panel architecture-panel">
        <div class="architecture-intro"><span class="brand-mark">SM</span><div><h2>{{ $activeSociety?->name ?? $user->society?->name ?? 'Society' }} Portal</h2><p>Resident and committee tools in one secure web application.</p></div></div>
        <div class="tech-grid"><article><span class="tech-number">01</span><h3>Laravel 10</h3><p>Authentication, server-side validation, session security, role checks and database transactions.</p></article><article><span class="tech-number">02</span><h3>MySQL / MariaDB</h3><p>Relational models and migrations for residents, flats, bills, notices, complaints and audit history.</p></article><article><span class="tech-number">03</span><h3>HTML5, CSS3 & jQuery</h3><p>Responsive Blade views with lightweight progressive JavaScript interactions.</p></article></div>
    </section>
@endif
@endsection
