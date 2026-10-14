<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\ActivityLog;
use App\Models\Complaint;
use App\Models\Flat;
use App\Models\Notice;
use App\Models\Society;
use App\Models\Tenant;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DashboardController extends Controller
{
    private const TABS = ['dashboard', 'billing', 'complaints', 'notices', 'directory', 'architecture', 'societies', 'activity'];

    public function index(Request $request): View
    {
        $user = $request->user();
        $tab = $request->query(
            'tab',
            $user->role === 'super_admin' && ! $request->session()->get('active_society_id')
                ? 'societies'
                : 'dashboard'
        );
        abort_unless(in_array($tab, self::TABS, true), 404);

        $activeSociety = null;
        if ($user->role === 'super_admin') {
            $activeSocietyId = $request->session()->get('active_society_id');
            $activeSociety = $activeSocietyId
                ? Society::whereKey($activeSocietyId)->where('status', 'active')->first()
                : null;
            if ($activeSocietyId && ! $activeSociety) {
                $request->session()->forget('active_society_id');
            }
        }

        if ($user->role === 'super_admin' && in_array($tab, ['societies', 'activity'], true)) {
            return view('super-admin.index', [
                'user' => $user,
                'flat' => null,
                'activeSociety' => $activeSociety,
                'tab' => $tab,
                'societies' => $tab === 'societies'
                    ? Society::withCount(['users', 'flats', 'bills'])
                        ->with(['users' => fn ($query) => $query->where('role', 'admin')->orderBy('full_name')])
                        ->orderByRaw("CASE status WHEN 'pending' THEN 1 WHEN 'active' THEN 2 ELSE 3 END")
                        ->orderBy('name')
                        ->paginate(50)
                    : collect(),
                'activityLogs' => $tab === 'activity'
                    ? ActivityLog::with(['actor', 'society'])->orderByDesc('created_at')->paginate(50)
                    : collect(),
                'metrics' => [
                    'pending_societies' => Society::where('status', 'pending')->count(),
                    'active_societies' => Society::where('status', 'active')->count(),
                    'activity_count' => ActivityLog::count(),
                ],
            ]);
        }

        if ($user->role === 'super_admin') {
            abort_unless($activeSociety && in_array($tab, ['dashboard', 'billing', 'complaints', 'notices', 'directory'], true), 404);
        }

        abort_unless(in_array($tab, ['dashboard', 'billing', 'complaints', 'notices', 'directory', 'architecture'], true), 404);
        $flat = $user->flat;
        $flat?->load('tenant');
        $billQuery = Bill::with(['flat.owner'])->orderByDesc('bill_period')->orderByDesc('id');
        $complaintQuery = Complaint::with([
            'flat',
            'resident',
            'assignee',
            'logs.actor',
        ])->orderByRaw("CASE status WHEN 'submitted' THEN 1 WHEN 'in_progress' THEN 2 WHEN 'resolved' THEN 3 ELSE 4 END")
          ->orderByDesc('created_at');

        if ($user->role === 'resident') {
            $billQuery->where('flat_id', $flat?->id ?? 0);
            $complaintQuery->where('resident_user_id', $user->id);
        }

        $bills = $billQuery->limit($tab === 'billing' ? 100 : 5)->get();
        $complaints = $complaintQuery->limit($tab === 'complaints' ? 100 : 5)->get();
        $notices = Notice::with('author')
            ->where('is_published', true)
            ->where('scheduled_publish_at', '<=', now())
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->orderByDesc('pinned')
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'normal' THEN 3 ELSE 4 END")
            ->orderByDesc('scheduled_publish_at')
            ->limit($tab === 'notices' ? 100 : 5)
            ->get();

        $metrics = [
            'total_flats' => Flat::count(),
            'pending_dues' => Bill::whereIn('payment_status', ['unpaid', 'overdue'])->sum('total_amount'),
            'unpaid_bills' => Bill::whereIn('payment_status', ['unpaid', 'overdue'])->count(),
            'monthly_collected' => Bill::where('payment_status', 'paid')
                ->whereMonth('paid_date', now()->month)
                ->whereYear('paid_date', now()->year)
                ->sum('total_amount'),
            'open_complaints' => Complaint::whereIn('status', ['submitted', 'in_progress'])->count(),
            'active_notices' => Notice::where('is_published', true)
                ->where('scheduled_publish_at', '<=', now())
                ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->count(),
        ];

        return view('dashboard.index', [
            'user' => $user,
            'flat' => $flat,
            'activeSociety' => $activeSociety ?? $user->society,
            'tab' => $tab,
            'bills' => $bills,
            'complaints' => $complaints,
            'notices' => $notices,
            'metrics' => $metrics,
            'members' => $tab === 'directory'
                ? User::with('flat')->orderBy('full_name')->get()
                : collect(),
            'staff' => in_array($user->role, ['admin', 'super_admin'], true)
                ? User::whereIn('role', ['admin', 'staff'])->orderBy('full_name')->get()
                : collect(),
            'allFlats' => in_array($user->role, ['admin', 'super_admin'], true)
                ? Flat::with('owner')->orderBy('wing')->orderBy('flat_number')->get()
                : collect(),
            'tenants' => in_array($user->role, ['admin', 'super_admin'], true) && $tab === 'directory'
                ? Tenant::with('flat.owner')->orderBy('full_name')->get()
                : collect(),
        ]);
    }

    public function storeComplaint(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'category' => ['required', Rule::in(['plumbing', 'electrical', 'lift', 'security', 'general'])],
            'priority' => ['required', Rule::in(['urgent', 'high', 'medium', 'low'])],
            'subject' => ['required', 'string', 'max:200'],
            'description' => ['required', 'string', 'max:5000'],
        ]);

        $flat = $request->user()->flat;
        if (! $flat) {
            return back()->withErrors(['complaint' => 'Your account does not have a flat assigned yet.']);
        }

        $complaint = DB::transaction(function () use ($validated, $request, $flat) {
            $complaint = Complaint::create([
                ...$validated,
                'ticket_number' => 'TKT-'.now()->format('Y').'-'.Str::upper(Str::random(8)),
                'flat_id' => $flat->id,
                'resident_user_id' => $request->user()->id,
                'status' => 'submitted',
            ]);

            $complaint->logs()->create([
                'action_by_user_id' => $request->user()->id,
                'previous_status' => null,
                'new_status' => 'submitted',
                'remarks' => 'Complaint registered by resident.',
            ]);

            return $complaint;
        });

        return redirect()->route('dashboard', ['tab' => 'complaints'])
            ->with('success', "Complaint {$complaint->ticket_number} has been submitted.");
    }

    public function storeTenant(Request $request): RedirectResponse
    {
        abort_unless($request->user()->role === 'resident', 403);
        $flat = Flat::where('owner_user_id', $request->user()->id)->firstOrFail();

        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:150'],
            'agreement' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $tenant = Tenant::where('flat_id', $flat->id)->first();
        $previousAgreement = $tenant?->agreement_path;
        $newAgreement = null;

        if ($request->hasFile('agreement')) {
            $newAgreement = $request->file('agreement')->store('tenant-agreements', 'local');
            if ($newAgreement === false) {
                throw new \RuntimeException('The tenant agreement could not be saved.');
            }
        }

        try {
            DB::transaction(function () use ($flat, $validated, $newAgreement) {
                unset($validated['agreement']);
                if ($newAgreement !== null) {
                    $validated['agreement_path'] = $newAgreement;
                }
                Tenant::updateOrCreate(['flat_id' => $flat->id], $validated);
                $flat->update(['occupancy_status' => 'tenant_occupied']);
            });
        } catch (\Throwable $exception) {
            if ($newAgreement !== null) {
                Storage::disk('local')->delete($newAgreement);
            }

            throw $exception;
        }

        if ($newAgreement !== null && $previousAgreement !== null) {
            Storage::disk('local')->delete($previousAgreement);
        }

        return redirect()->route('dashboard', ['tab' => 'directory'])
            ->with('success', 'Tenant details saved for flat '.$flat->wing.'-'.$flat->flat_number.'.');
    }

    public function destroyTenant(Request $request): RedirectResponse
    {
        abort_unless($request->user()->role === 'resident', 403);
        $flat = Flat::where('owner_user_id', $request->user()->id)->firstOrFail();

        $tenant = Tenant::where('flat_id', $flat->id)->first();
        DB::transaction(function () use ($flat, $tenant) {
            $tenant?->delete();
            $flat->update(['occupancy_status' => 'owner_occupied']);
        });
        if ($tenant?->agreement_path !== null) {
            Storage::disk('local')->delete($tenant->agreement_path);
        }

        return redirect()->route('dashboard', ['tab' => 'directory'])
            ->with('success', 'Tenant details removed from flat '.$flat->wing.'-'.$flat->flat_number.'.');
    }

    public function downloadTenantAgreement(Request $request, Tenant $tenant)
    {
        abort_unless(
            in_array($request->user()->role, ['admin', 'super_admin'], true) ||
                $tenant->flat()->where('owner_user_id', $request->user()->id)->exists(),
            403
        );
        abort_if($tenant->agreement_path === null, 404);
        abort_unless(Storage::disk('local')->exists($tenant->agreement_path), 404);
        app(\App\Services\ActivityLogger::class)->record('agreement_downloaded', 'Tenant agreement downloaded.', $tenant);

        return Storage::disk('local')->download(
            $tenant->agreement_path,
            'tenant-agreement-'.$tenant->flat->wing.'-'.$tenant->flat->flat_number.'.pdf',
            ['Content-Type' => 'application/pdf']
        );
    }

    public function updateComplaint(Request $request, Complaint $complaint): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['submitted', 'in_progress', 'resolved', 'closed'])],
            'remarks' => ['required', 'string', 'max:2000'],
            'assigned_to_user_id' => [
                'nullable',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->whereIn('role', ['admin', 'staff'])
                    ->where('society_id', $complaint->society_id)),
            ],
        ]);

        DB::transaction(function () use ($validated, $complaint, $request) {
            $complaint = Complaint::whereKey($complaint->id)->lockForUpdate()->firstOrFail();
            $previousStatus = $complaint->status;
            $complaint->status = $validated['status'];

            if (array_key_exists('assigned_to_user_id', $validated)) {
                $complaint->assigned_to_user_id = $validated['assigned_to_user_id'];
            }

            if ($validated['status'] === 'resolved') {
                $complaint->resolution_notes = $validated['remarks'];
                $complaint->resolved_at = now();
            }
            if ($validated['status'] === 'closed') {
                $complaint->closed_at = now();
            }
            $complaint->save();

            $complaint->logs()->create([
                'action_by_user_id' => $request->user()->id,
                'previous_status' => $previousStatus,
                'new_status' => $validated['status'],
                'remarks' => $validated['remarks'],
            ]);
        });

        return redirect()->route('dashboard', ['tab' => 'complaints'])
            ->with('success', 'Complaint status updated.');
    }

    public function storeNotice(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::in(['water_supply', 'maintenance', 'meeting', 'security', 'event', 'general'])],
            'priority' => ['required', Rule::in(['urgent', 'high', 'normal', 'low'])],
            'content' => ['required', 'string', 'max:10000'],
            'pinned' => ['nullable', 'boolean'],
        ]);

        Notice::create([
            ...$validated,
            'pinned' => $request->boolean('pinned'),
            'is_published' => true,
            'scheduled_publish_at' => now(),
            'created_by_user_id' => $request->user()->id,
        ]);

        return redirect()->route('dashboard', ['tab' => 'notices'])->with('success', 'Notice published.');
    }

    public function storeResident(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->residentRules($request));
        $resident = DB::transaction(fn () => $this->createResident($validated));

        return redirect()->route('dashboard', ['tab' => 'directory'])
            ->with('success', "Resident {$resident->full_name} and flat {$resident->flat->wing}-{$resident->flat->flat_number} added.");
    }

    public function importResidents(Request $request): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:10240']]);
        [$rows, $errors] = $this->readCsvRows($request, [
            'full_name', 'email', 'phone', 'password', 'wing', 'flat_number',
            'floor', 'carpet_sqft', 'occupancy_status', 'parking_slot', 'monthly_maintenance_amount',
        ]);

        $seenEmails = [];
        $seenFlats = [];
        $residents = [];
        foreach ($rows as $rowNumber => $row) {
            $row = array_merge([
                'floor' => 1,
                'carpet_sqft' => 950,
                'occupancy_status' => 'owner_occupied',
                'parking_slot' => null,
                'monthly_maintenance_amount' => 3500,
            ], $row);
            $row['email'] = strtolower($row['email'] ?? '');
            $row['wing'] = strtoupper($row['wing'] ?? '');
            $row['flat_number'] = trim($row['flat_number'] ?? '');

            $validator = Validator::make($row, $this->residentRules(null, $row));
            foreach ($validator->errors()->all() as $message) {
                $errors[] = "Row {$rowNumber}: {$message}";
            }

            $flatKey = $row['wing'].'-'.$row['flat_number'];
            if (isset($seenEmails[$row['email']])) {
                $errors[] = "Row {$rowNumber}: email is repeated in this file.";
            }
            if (isset($seenFlats[$flatKey])) {
                $errors[] = "Row {$rowNumber}: flat {$flatKey} is repeated in this file.";
            }
            $seenEmails[$row['email']] = true;
            $seenFlats[$flatKey] = true;
            $residents[$rowNumber] = $row;
        }

        if ($errors) {
            return back()->withErrors(['import' => $errors]);
        }
        if (! $residents) {
            return back()->withErrors(['import' => 'The CSV file does not contain any resident rows.']);
        }

        DB::transaction(function () use ($residents) {
            foreach ($residents as $resident) {
                $this->createResident($resident);
            }
        });

        return redirect()->route('dashboard', ['tab' => 'directory'])
            ->with('success', count($residents).' residents imported successfully.');
    }

    public function importBills(Request $request): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:10240']]);
        [$rows, $errors] = $this->readCsvRows($request, [
            'wing', 'flat_number', 'bill_period', 'base_maintenance', 'utility_charges',
            'sinking_fund', 'penalty_fine', 'due_date', 'payment_status', 'payment_method',
            'payment_transaction_id', 'paid_date', 'notes',
        ]);

        $bills = [];
        $seenPeriods = [];
        foreach ($rows as $rowNumber => $row) {
            $row['wing'] = strtoupper($row['wing'] ?? '');
            $row['flat_number'] = trim($row['flat_number'] ?? '');
            $validator = Validator::make($row, [
                'wing' => ['required', 'string', 'max:10'],
                'flat_number' => ['required', 'string', 'max:20'],
                'bill_period' => ['required', 'date_format:Y-m'],
                'base_maintenance' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
                'utility_charges' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
                'sinking_fund' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
                'penalty_fine' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
                'due_date' => ['required', 'date_format:Y-m-d'],
                'payment_status' => ['required', Rule::in(['unpaid', 'paid'])],
                'payment_method' => ['nullable', Rule::in(['upi', 'netbanking', 'card', 'cheque', 'cash'])],
                'payment_transaction_id' => ['nullable', 'string', 'max:100'],
                'paid_date' => ['nullable', 'date_format:Y-m-d'],
                'notes' => ['nullable', 'string', 'max:2000'],
            ]);
            foreach ($validator->errors()->all() as $message) {
                $errors[] = "Row {$rowNumber}: {$message}";
            }

            if (($row['payment_status'] ?? null) === 'paid' && empty($row['payment_method'])) {
                $errors[] = "Row {$rowNumber}: payment_method is required when payment_status is paid.";
            }
            if (($row['payment_status'] ?? null) === 'unpaid' &&
                (! empty($row['payment_method']) || ! empty($row['payment_transaction_id']) || ! empty($row['paid_date']))) {
                $errors[] = "Row {$rowNumber}: payment details can only be supplied when payment_status is paid.";
            }

            $hasValidPeriod = isset($row['bill_period']) &&
                preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $row['bill_period']);

            $period = $hasValidPeriod ? $row['bill_period'].'-01' : null;
            if (isset($row['wing'], $row['flat_number']) && $row['wing'] !== '' && $row['flat_number'] !== '') {
                if ($hasValidPeriod) {
                    $key = $row['wing'].'-'.$row['flat_number'].'-'.$period;
                    if (isset($seenPeriods[$key])) {
                        $errors[] = "Row {$rowNumber}: this flat and billing month are repeated in the file.";
                    }
                    $seenPeriods[$key] = true;
                }

                $flat = Flat::where('wing', $row['wing'])->where('flat_number', $row['flat_number'])->first();
                if (! $flat) {
                    $errors[] = "Row {$rowNumber}: flat {$row['wing']}-{$row['flat_number']} does not exist.";
                } elseif ($hasValidPeriod && Bill::where('flat_id', $flat->id)->whereDate('bill_period', $period)->exists()) {
                    $errors[] = "Row {$rowNumber}: a bill already exists for flat {$row['wing']}-{$row['flat_number']} in {$row['bill_period']}.";
                } else {
                    $row['_flat_id'] = $flat->id;
                }
            }

            if ($hasValidPeriod && isset($row['due_date']) &&
                preg_match('/^\d{4}-\d{2}-\d{2}$/', $row['due_date']) &&
                $row['due_date'] < $period) {
                $errors[] = "Row {$rowNumber}: due_date must be on or after the billing month.";
            }
            $amounts = [$row['base_maintenance'] ?? null, $row['utility_charges'] ?? null,
                $row['sinking_fund'] ?? null, $row['penalty_fine'] ?? null];
            if (count(array_filter($amounts, 'is_numeric')) === 4 && array_sum($amounts) > 99999999.99) {
                $errors[] = "Row {$rowNumber}: the total charges exceed the maximum supported bill amount.";
            }
            $bills[$rowNumber] = $row;
        }

        if ($errors) {
            return back()->withErrors(['import' => $errors]);
        }
        if (! $bills) {
            return back()->withErrors(['import' => 'The CSV file does not contain any maintenance rows.']);
        }

        DB::transaction(function () use ($bills, $request) {
            foreach ($bills as $row) {
                $totalCents = (int) round((float) $row['base_maintenance'] * 100)
                    + (int) round((float) $row['utility_charges'] * 100)
                    + (int) round((float) $row['sinking_fund'] * 100)
                    + (int) round((float) $row['penalty_fine'] * 100);
                $total = $totalCents / 100;
                $paid = $row['payment_status'] === 'paid';

                Bill::create([
                    'bill_number' => 'BILL-'.str_replace('-', '', $row['bill_period']).'-'.$row['wing'].$row['flat_number'],
                    'flat_id' => $row['_flat_id'],
                    'bill_period' => $row['bill_period'].'-01',
                    'base_maintenance' => $row['base_maintenance'],
                    'utility_charges' => $row['utility_charges'],
                    'sinking_fund' => $row['sinking_fund'],
                    'penalty_fine' => $row['penalty_fine'],
                    'total_amount' => $total,
                    'due_date' => $row['due_date'],
                    'payment_status' => $row['payment_status'],
                    'paid_date' => $paid ? ($row['paid_date'] ?: now()) : null,
                    'payment_method' => $paid ? $row['payment_method'] : null,
                    'payment_transaction_id' => $paid
                        ? ($row['payment_transaction_id'] ?: 'REC-'.Str::upper(Str::random(10)))
                        : null,
                    'generated_by_user_id' => $request->user()->id,
                    'notes' => $row['notes'] ?: 'Monthly society maintenance charges.',
                ]);
            }
        });

        return redirect()->route('dashboard', ['tab' => 'billing'])
            ->with('success', count($bills).' monthly maintenance records imported successfully.');
    }

    public function residentImportTemplate()
    {
        return $this->csvTemplate('resident-import-template.csv', [
            'full_name', 'email', 'phone', 'password', 'wing', 'flat_number',
            'floor', 'carpet_sqft', 'occupancy_status', 'parking_slot', 'monthly_maintenance_amount',
        ]);
    }

    public function residentImportSample()
    {
        return $this->csvDownload('resident-import-sample.csv', [
            'full_name', 'email', 'phone', 'password', 'wing', 'flat_number',
            'floor', 'carpet_sqft', 'occupancy_status', 'parking_slot', 'monthly_maintenance_amount',
        ], [[
            'Sample Owner',
            'sample.owner@example.com',
            '9000000000',
            'SamplePass123',
            'Z',
            '999',
            '9',
            '900',
            'owner_occupied',
            'P-Z999',
            '3500',
        ]]);
    }

    public function billImportTemplate()
    {
        return $this->csvTemplate('maintenance-import-template.csv', [
            'wing', 'flat_number', 'bill_period', 'base_maintenance', 'utility_charges',
            'sinking_fund', 'penalty_fine', 'due_date', 'payment_status', 'payment_method',
            'payment_transaction_id', 'paid_date', 'notes',
        ]);
    }

    public function billImportSample()
    {
        $period = now()->format('Y-m');

        return $this->csvDownload('maintenance-import-sample.csv', [
            'wing', 'flat_number', 'bill_period', 'base_maintenance', 'utility_charges',
            'sinking_fund', 'penalty_fine', 'due_date', 'payment_status', 'payment_method',
            'payment_transaction_id', 'paid_date', 'notes',
        ], [[
            'Z',
            '999',
            $period,
            '3500',
            '450',
            '250',
            '0',
            now()->startOfMonth()->addMonth()->day(15)->format('Y-m-d'),
            'paid',
            'upi',
            'SAMPLE-TXN-001',
            now()->format('Y-m-d'),
            'Sample only - replace with actual details',
        ]]);
    }

    public function generateBills(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'bill_period' => ['required', 'date_format:Y-m'],
            'due_date' => ['required', 'date_format:Y-m-d'],
        ]);

        $period = $validated['bill_period'].'-01';
        if ($validated['due_date'] < $period) {
            return back()->withErrors(['due_date' => 'The due date must be on or after the billing month.']);
        }

        $created = DB::transaction(function () use ($request, $period, $validated) {
            $count = 0;

            Flat::orderBy('id')->chunkById(100, function ($flats) use (&$count, $request, $period, $validated) {
                foreach ($flats as $flat) {
                    $base = (float) $flat->monthly_maintenance_amount;
                    if (Bill::where('flat_id', $flat->id)->whereDate('bill_period', $period)->exists()) {
                        continue;
                    }

                    Bill::create([
                        'bill_number' => 'BILL-'.str_replace('-', '', substr($period, 0, 7)).'-'.$flat->wing.$flat->flat_number,
                        'flat_id' => $flat->id,
                        'bill_period' => $period,
                        'base_maintenance' => $base,
                        'utility_charges' => 450,
                        'sinking_fund' => 250,
                        'penalty_fine' => 0,
                        'total_amount' => $base + 700,
                        'due_date' => $validated['due_date'],
                        'payment_status' => 'unpaid',
                        'generated_by_user_id' => $request->user()->id,
                        'notes' => 'Standard monthly society maintenance charges.',
                    ]);
                    $count++;
                }
            });

            return $count;
        });

        $message = $created
            ? "{$created} bills generated for ".\Illuminate\Support\Carbon::parse($period)->format('F Y').'.'
            : 'Bills already exist for every flat in this billing period.';

        return redirect()->route('dashboard', ['tab' => 'billing'])->with('success', $message);
    }

    public function generateSingleBill(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'flat_id' => ['required', 'integer', Rule::exists('flats', 'id')],
            'bill_period' => ['required', 'date_format:Y-m'],
            'due_date' => ['required', 'date_format:Y-m-d'],
        ]);

        $period = $validated['bill_period'].'-01';
        if ($validated['due_date'] < $period) {
            return back()->withErrors(['due_date' => 'The due date must be on or after the billing month.']);
        }

        $bill = DB::transaction(function () use ($request, $validated, $period) {
            $flat = Flat::whereKey($validated['flat_id'])->lockForUpdate()->firstOrFail();
            if (Bill::where('flat_id', $flat->id)->whereDate('bill_period', $period)->exists()) {
                throw ValidationException::withMessages([
                    'flat_id' => "A bill already exists for flat {$flat->wing}-{$flat->flat_number} for {$validated['bill_period']}.",
                ]);
            }

            $base = (float) $flat->monthly_maintenance_amount;

            return Bill::create([
                'bill_number' => 'BILL-'.str_replace('-', '', $validated['bill_period']).'-'.$flat->wing.$flat->flat_number,
                'flat_id' => $flat->id,
                'bill_period' => $period,
                'base_maintenance' => $base,
                'utility_charges' => 450,
                'sinking_fund' => 250,
                'penalty_fine' => 0,
                'total_amount' => $base + 700,
                'due_date' => $validated['due_date'],
                'payment_status' => 'unpaid',
                'generated_by_user_id' => $request->user()->id,
                'notes' => 'Standard monthly society maintenance charges.',
            ]);
        });

        return redirect()->route('dashboard', ['tab' => 'billing'])
            ->with('success', "Bill {$bill->bill_number} generated for flat {$bill->flat->wing}-{$bill->flat->flat_number}.");
    }

    public function payBill(Request $request, Bill $bill): RedirectResponse
    {
        $validated = $request->validate([
            'payment_method' => ['required', Rule::in(['upi', 'netbanking', 'card', 'cheque', 'cash'])],
            'payment_transaction_id' => ['nullable', 'string', 'max:100'],
            'payment_proof' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $user = $request->user();
        abort_unless(
            in_array($user->role, ['admin', 'super_admin'], true) || $bill->flat()->where('owner_user_id', $user->id)->exists(),
            403
        );
        abort_if($bill->payment_status === 'paid', 409, 'This bill is already marked as paid.');

        if ($user->role === 'resident') {
            abort_if(
                $bill->payment_submitted_at !== null,
                409,
                'Payment proof is already awaiting administrator review.'
            );

            if (empty($validated['payment_transaction_id']) && ! $request->hasFile('payment_proof')) {
                return back()->withErrors([
                    'payment_proof' => 'Enter a transaction/reference ID or upload a payment screenshot.',
                ]);
            }

            $previousProof = $bill->payment_proof_path;
            $newProof = null;
            if ($request->hasFile('payment_proof')) {
                $newProof = $request->file('payment_proof')->store('payment-proofs', 'local');
                if ($newProof === false) {
                    throw new \RuntimeException('The payment screenshot could not be saved.');
                }
            }

            try {
                $bill->update([
                    'payment_method' => $validated['payment_method'],
                    'payment_transaction_id' => ($validated['payment_transaction_id'] ?? null) ?: null,
                    'payment_proof_path' => $newProof,
                    'payment_submitted_at' => now(),
                    'payment_review_note' => null,
                ]);
            } catch (\Throwable $exception) {
                if ($newProof !== null) {
                    Storage::disk('local')->delete($newProof);
                }
                throw $exception;
            }

            if ($previousProof !== null) {
                Storage::disk('local')->delete($previousProof);
            }

            return redirect()->route('dashboard', ['tab' => 'billing'])
                ->with('success', 'Payment proof submitted for '.$bill->bill_number.'. The society administrator will review it before marking the bill paid.');
        }

        $bill->update([
            'payment_status' => 'paid',
            'paid_date' => now(),
            'payment_method' => $validated['payment_method'],
            'payment_transaction_id' => ($validated['payment_transaction_id'] ?? null) ?: 'REC-'.Str::upper(Str::random(10)),
            'payment_proof_path' => null,
            'payment_submitted_at' => null,
            'payment_review_note' => null,
        ]);

        return redirect()->route('dashboard', ['tab' => 'billing'])->with('success', 'Payment recorded for '.$bill->bill_number.'.');
    }

    public function reviewBillPayment(Request $request, Bill $bill): RedirectResponse
    {
        $validated = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'review_note' => ['nullable', 'string', 'max:1000'],
        ]);

        abort_if($bill->payment_status === 'paid', 409, 'This bill is already marked as paid.');
        abort_if($bill->payment_submitted_at === null, 409, 'This bill has no payment proof awaiting review.');

        if ($validated['decision'] === 'reject' && empty($validated['review_note'])) {
            return back()->withErrors(['review_note' => 'Add a short reason so the owner knows what needs to be corrected.']);
        }

        if ($validated['decision'] === 'approve') {
            $bill->update([
                'payment_status' => 'paid',
                'paid_date' => now(),
                'payment_review_note' => $validated['review_note'] ?? null,
            ]);

            return redirect()->route('dashboard', ['tab' => 'billing'])
                ->with('success', 'Payment verified and bill '.$bill->bill_number.' marked as paid.');
        }

        $bill->update([
            'payment_submitted_at' => null,
            'payment_review_note' => $validated['review_note'],
        ]);

        return redirect()->route('dashboard', ['tab' => 'billing'])
            ->with('success', 'Payment proof rejected. The owner can resubmit the payment details.');
    }

    public function downloadPaymentProof(Request $request, Bill $bill)
    {
        abort_unless(
            in_array($request->user()->role, ['admin', 'super_admin'], true) ||
                ($request->user()->role === 'resident' && $bill->flat()->where('owner_user_id', $request->user()->id)->exists()),
            403
        );
        abort_if($bill->payment_proof_path === null, 404);
        abort_unless(Storage::disk('local')->exists($bill->payment_proof_path), 404);
        app(\App\Services\ActivityLogger::class)->record('payment_proof_downloaded', 'Payment proof downloaded for '.$bill->bill_number.'.', $bill);

        return Storage::disk('local')->download(
            $bill->payment_proof_path,
            $bill->bill_number.'-payment-proof.'.pathinfo($bill->payment_proof_path, PATHINFO_EXTENSION)
        );
    }

    public function downloadBill(Request $request, Bill $bill)
    {
        $bill->load(['flat.owner', 'flat.society']);
        abort_unless(
            in_array($request->user()->role, ['admin', 'super_admin'], true) ||
                ($request->user()->role === 'resident' && $bill->flat->owner_user_id === $request->user()->id),
            403
        );
        app(\App\Services\ActivityLogger::class)->record('bill_downloaded', 'Bill PDF downloaded: '.$bill->bill_number.'.', $bill);

        return Pdf::loadView('billing.invoice', ['bill' => $bill])
            ->setPaper('a4')
            ->download($bill->bill_number.'.pdf');
    }

    private function residentRules(?Request $request = null, ?array $row = null): array
    {
        $wing = strtoupper($row['wing'] ?? $request?->input('wing', ''));
        $societyId = $request?->user()?->role === 'super_admin'
            ? $request->session()->get('active_society_id')
            : $request?->user()?->society_id;
        return [
            'full_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:150', Rule::unique('users', 'email')],
            'phone' => ['required', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8'],
            'wing' => ['required', 'string', 'max:10', 'alpha_num'],
            'flat_number' => [
                'required', 'string', 'max:20',
                Rule::unique('flats', 'flat_number')->where(fn ($query) => $query
                    ->where('society_id', $societyId)
                    ->where('wing', $wing)),
            ],
            'floor' => ['nullable', 'integer', 'min:1'],
            'carpet_sqft' => ['nullable', 'integer', 'min:1'],
            'occupancy_status' => ['required', Rule::in(['owner_occupied', 'tenant_occupied', 'vacant'])],
            'parking_slot' => ['nullable', 'string', 'max:30'],
            'monthly_maintenance_amount' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
        ];
    }

    private function createResident(array $data): User
    {
        $resident = User::create([
            'full_name' => $data['full_name'],
            'email' => strtolower($data['email']),
            'phone' => $data['phone'],
            'password' => $data['password'],
            'role' => 'resident',
            'status' => 'active',
        ]);

        $wing = strtoupper($data['wing']);
        $flatNumber = $data['flat_number'];
        $resident->flat()->create([
            'wing' => $wing,
            'flat_number' => $flatNumber,
            'floor' => $data['floor'] ?: max(1, (int) substr($flatNumber, 0, 1)),
            'carpet_sqft' => $data['carpet_sqft'] ?: 950,
            'occupancy_status' => $data['occupancy_status'],
            'parking_slot' => $data['parking_slot'] ?: null,
            'monthly_maintenance_amount' => $data['monthly_maintenance_amount'],
        ]);

        return $resident;
    }

    private function readCsvRows(Request $request, array $requiredHeaders): array
    {
        $handle = fopen($request->file('file')->getRealPath(), 'rb');
        if ($handle === false) {
            return [[], ['The uploaded CSV file could not be read.']];
        }

        $errors = [];
        $rows = [];
        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            return [[], ['The uploaded CSV file is empty.']];
        }

        $header = array_map(fn ($value) => strtolower(trim((string) $value)), $header);
        if (isset($header[0])) {
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
        }
        $missing = array_diff($requiredHeaders, $header);
        if ($missing) {
            fclose($handle);
            return [[], ['Missing required CSV columns: '.implode(', ', $missing).'.']];
        }
        if (count($header) !== count(array_unique($header))) {
            fclose($handle);
            return [[], ['The CSV file contains duplicate column names.']];
        }

        $rowNumber = 1;
        while (($values = fgetcsv($handle)) !== false) {
            $rowNumber++;
            if (count(array_filter($values, fn ($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }
            if (count($values) !== count($header)) {
                $errors[] = "Row {$rowNumber}: the number of values does not match the header columns.";
                continue;
            }
            $rows[$rowNumber] = array_combine($header, array_map(
                fn ($value) => trim((string) $value) === '' ? null : trim((string) $value),
                $values
            ));
            if (count($rows) > 500) {
                $errors[] = 'A maximum of 500 rows can be imported at once.';
                break;
            }
        }
        fclose($handle);

        return [$rows, $errors];
    }

    private function csvTemplate(string $filename, array $headers)
    {
        return $this->csvDownload($filename, $headers);
    }

    private function csvDownload(string $filename, array $headers, array $rows = [])
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $output = fopen('php://output', 'wb');
            fputcsv($output, $headers);
            foreach ($rows as $row) {
                fputcsv($output, $row);
            }
            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
