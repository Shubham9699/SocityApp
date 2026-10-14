<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Complaint;
use App\Models\Flat;
use App\Models\Society;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class SocietyPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_is_available_to_guests(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Sign in to your account')
            ->assertHeader('Cache-Control', 'must-revalidate, no-cache, no-store, private')
            ->assertSee('data-theme-toggle', false)
            ->assertSee('Switch to dark mode');
    }

    public function test_browser_back_navigation_cannot_reuse_login_or_dashboard_pages(): void
    {
        $resident = $this->createUser('resident', 'resident@example.com');

        $this->actingAs($resident)->get(route('dashboard'))
            ->assertOk()
            ->assertHeader('Cache-Control', 'must-revalidate, no-cache, no-store, private');

        $this->get(route('login'))->assertRedirect(route('dashboard'));

        $this->post(route('logout'))
            ->assertRedirect(route('login'))
            ->assertHeader('Cache-Control', 'must-revalidate, no-cache, no-store, private');

        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_dashboard_has_a_theme_switch_that_can_be_persisted_by_the_browser(): void
    {
        $resident = $this->createUser('resident', 'resident@example.com');

        $this->actingAs($resident)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-theme-toggle', false)
            ->assertSee('Switch to dark mode');
    }

    public function test_resident_can_sign_in_and_view_their_dashboard(): void
    {
        $resident = $this->createUser('resident', 'resident@example.com');

        $this->post(route('login.store'), [
            'email' => $resident->email,
            'password' => 'Secret@123',
            'role' => 'resident',
        ])->assertRedirect(route('dashboard'));

        $this->get(route('dashboard'))->assertOk()->assertSee('Welcome home');
        $this->assertDatabaseHas('activity_logs', [
            'actor_user_id' => $resident->id,
            'action' => 'login',
        ]);
    }

    public function test_resident_cannot_generate_society_bills(): void
    {
        $resident = $this->createUser('resident', 'resident@example.com');

        $this->actingAs($resident)
            ->post(route('bills.generate'), [
                'bill_period' => now()->format('Y-m'),
                'due_date' => now()->format('Y-m').'-15',
            ])
            ->assertForbidden();

        $this->post(route('bills.generate-single'), [
            'flat_id' => 1,
            'bill_period' => now()->format('Y-m'),
            'due_date' => now()->format('Y-m').'-15',
        ])->assertForbidden();
    }

    public function test_administrator_can_generate_a_bill_for_a_single_owner_flat(): void
    {
        $admin = $this->createUser('admin', 'admin@example.com');
        $owner = $this->createUser('resident', 'owner@example.com');
        $selectedFlat = $this->createFlat($owner, 'A', '101', 3800);
        $otherOwner = $this->createUser('resident', 'other@example.com');
        $this->createFlat($otherOwner, 'B', '202', 4200);

        $payload = [
            'flat_id' => $selectedFlat->id,
            'bill_period' => '2026-10',
            'due_date' => '2026-10-15',
        ];

        $this->actingAs($admin)->post(route('bills.generate-single'), $payload)
            ->assertRedirect(route('dashboard', ['tab' => 'billing']))
            ->assertSessionHas('success');

        $this->assertDatabaseCount('bills', 1);
        $this->assertDatabaseHas('bills', [
            'flat_id' => $selectedFlat->id,
            'bill_period' => '2026-10-01 00:00:00',
            'base_maintenance' => 3800,
            'utility_charges' => 450,
            'sinking_fund' => 250,
            'total_amount' => 4500,
            'payment_status' => 'unpaid',
        ]);

        $this->post(route('bills.generate-single'), $payload)
            ->assertRedirect()
            ->assertSessionHasErrors('flat_id');
        $this->assertDatabaseCount('bills', 1);
    }

    public function test_administrator_can_add_a_resident_and_assign_a_flat(): void
    {
        $admin = $this->createUser('admin', 'admin@example.com');

        $this->actingAs($admin)->post(route('residents.store'), [
            'full_name' => 'Owner One',
            'email' => 'owner.one@example.com',
            'phone' => '555-0198',
            'password' => 'OwnerPass123',
            'wing' => 'a',
            'flat_number' => '104',
            'floor' => 1,
            'carpet_sqft' => 900,
            'occupancy_status' => 'owner_occupied',
            'parking_slot' => 'P-A104',
            'monthly_maintenance_amount' => 4200,
        ])->assertRedirect(route('dashboard', ['tab' => 'directory']));

        $this->assertDatabaseHas('users', ['email' => 'owner.one@example.com', 'role' => 'resident']);
        $this->assertDatabaseHas('flats', [
            'wing' => 'A',
            'flat_number' => '104',
            'monthly_maintenance_amount' => 4200,
        ]);
    }

    public function test_administrator_can_download_sample_csv_files_for_both_imports(): void
    {
        $admin = $this->createUser('admin', 'admin@example.com');

        $residentSample = $this->actingAs($admin)->get(route('residents.import-sample'))
            ->assertDownload('resident-import-sample.csv')
            ->streamedContent();
        $this->assertStringContainsString('sample.owner@example.com', $residentSample);
        $this->assertStringContainsString('SamplePass123', $residentSample);

        $billSample = $this->get(route('bills.import-sample'))
            ->assertDownload('maintenance-import-sample.csv')
            ->streamedContent();
        $this->assertStringContainsString('SAMPLE-TXN-001', $billSample);
        $this->assertStringContainsString('Sample only - replace with actual details', $billSample);

        $this->get(route('dashboard', ['tab' => 'directory']))
            ->assertOk()
            ->assertSee(route('residents.import-sample'));
        $this->get(route('dashboard', ['tab' => 'billing']))
            ->assertOk()
            ->assertSee(route('bills.import-sample'));
    }

    public function test_owner_can_add_update_and_remove_tenant_contact_details_for_their_flat(): void
    {
        $owner = $this->createUser('resident', 'owner@example.com');
        $flat = $this->createFlat($owner, 'A', '101', 3800);

        $this->actingAs($owner)->post(route('tenant.store'), [
            'full_name' => 'Tenant Person',
            'phone' => '555-0188',
            'email' => 'tenant@example.com',
        ])->assertRedirect(route('dashboard', ['tab' => 'directory']));

        $this->assertDatabaseHas('tenants', [
            'flat_id' => $flat->id,
            'full_name' => 'Tenant Person',
            'email' => 'tenant@example.com',
        ]);
        $this->assertDatabaseHas('flats', ['id' => $flat->id, 'occupancy_status' => 'tenant_occupied']);

        $this->post(route('tenant.store'), [
            'full_name' => 'Updated Tenant',
            'phone' => '555-0199',
            'email' => '',
        ])->assertRedirect(route('dashboard', ['tab' => 'directory']));

        $this->assertDatabaseHas('tenants', [
            'flat_id' => $flat->id,
            'full_name' => 'Updated Tenant',
            'phone' => '555-0199',
            'email' => null,
        ]);
        $this->assertDatabaseCount('tenants', 1);

        $this->delete(route('tenant.destroy'))
            ->assertRedirect(route('dashboard', ['tab' => 'directory']));

        $this->assertDatabaseCount('tenants', 0);
        $this->assertDatabaseHas('flats', ['id' => $flat->id, 'occupancy_status' => 'owner_occupied']);
    }

    public function test_administrator_cannot_manage_tenant_records(): void
    {
        $admin = $this->createUser('admin', 'admin@example.com');

        $this->actingAs($admin)->post(route('tenant.store'), [
            'full_name' => 'Tenant Person',
            'phone' => '555-0188',
        ])->assertForbidden();
    }

    public function test_owner_can_upload_agreement_and_admin_can_view_all_tenant_details_and_download_it(): void
    {
        Storage::fake('local');
        $owner = $this->createUser('resident', 'owner@example.com');
        $flat = $this->createFlat($owner, 'A', '101', 3800);
        $this->actingAs($owner)->post(route('tenant.store'), [
            'full_name' => 'Tenant Person',
            'phone' => '555-0188',
            'email' => 'tenant@example.com',
            'agreement' => UploadedFile::fake()->createWithContent(
                'rent-agreement.pdf',
                "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF"
            ),
        ])->assertRedirect(route('dashboard', ['tab' => 'directory']));

        $tenant = Tenant::where('flat_id', $flat->id)->firstOrFail();
        $this->assertNotNull($tenant->agreement_path);
        Storage::disk('local')->assertExists($tenant->agreement_path);

        $this->get(route('tenant-agreements.download', $tenant))
            ->assertDownload('tenant-agreement-A-101.pdf');

        $admin = $this->createUser('admin', 'admin@example.com');
        $this->actingAs($admin)->get(route('dashboard', ['tab' => 'directory']))
            ->assertOk()
            ->assertSee('All tenant details & agreements', false)
            ->assertSee('Tenant Person')
            ->assertSee('tenant@example.com')
            ->assertSee(route('tenant-agreements.download', $tenant));
        $this->get(route('tenant-agreements.download', $tenant))
            ->assertDownload('tenant-agreement-A-101.pdf');

        $otherOwner = $this->createUser('resident', 'other-owner@example.com');
        $this->actingAs($otherOwner)->get(route('tenant-agreements.download', $tenant))
            ->assertForbidden();
    }

    public function test_resident_import_is_atomic_and_reports_invalid_rows(): void
    {
        $admin = $this->createUser('admin', 'admin@example.com');
        $csv = implode("\n", [
            'full_name,email,phone,password,wing,flat_number,floor,carpet_sqft,occupancy_status,parking_slot,monthly_maintenance_amount',
            'Valid Owner,valid.owner@example.com,555-0101,OwnerPass123,A,101,1,900,owner_occupied,P-A101,3800',
            'Invalid Owner,invalid.owner@example.com,555-0102,short,B,202,2,1000,tenant_occupied,,4200',
        ]);

        $this->actingAs($admin)->post(route('residents.import'), [
            'file' => UploadedFile::fake()->createWithContent('owners.csv', $csv),
        ])->assertRedirect()->assertSessionHasErrors('import');

        $this->assertDatabaseMissing('users', ['email' => 'valid.owner@example.com']);
        $this->assertDatabaseMissing('flats', ['wing' => 'A', 'flat_number' => '101']);
    }

    public function test_administrator_can_import_monthly_bills_and_payment_records(): void
    {
        $admin = $this->createUser('admin', 'admin@example.com');
        $resident = $this->createUser('resident', 'resident@example.com');
        $this->createFlat($resident, 'A', '101', 3800);
        $this->createFlat($admin, 'B', '202', 4200);
        $csv = implode("\n", [
            'wing,flat_number,bill_period,base_maintenance,utility_charges,sinking_fund,penalty_fine,due_date,payment_status,payment_method,payment_transaction_id,paid_date,notes',
            'A,101,2026-10,3800,450,250,0,2026-10-15,paid,upi,TX-001,2026-10-05,October paid',
            'B,202,2026-10,4200,450,250,0,2026-10-15,unpaid,,,,October unpaid',
        ]);

        $this->actingAs($admin)->post(route('bills.import'), [
            'file' => UploadedFile::fake()->createWithContent('maintenance.csv', $csv),
        ])->assertRedirect(route('dashboard', ['tab' => 'billing']));

        $this->assertDatabaseHas('bills', [
            'flat_id' => 1,
            'bill_period' => '2026-10-01 00:00:00',
            'total_amount' => 4500,
            'payment_status' => 'paid',
            'payment_transaction_id' => 'TX-001',
        ]);
        $this->assertDatabaseHas('bills', [
            'flat_id' => 2,
            'bill_period' => '2026-10-01 00:00:00',
            'total_amount' => 4900,
            'payment_status' => 'unpaid',
        ]);
    }

    public function test_bill_pdf_can_be_downloaded_by_the_owner_and_admin_only(): void
    {
        $owner = $this->createUser('resident', 'owner@example.com');
        $flat = $this->createFlat($owner, 'A', '101', 3800);
        $bill = Bill::create([
            'bill_number' => 'BILL-202610-A101',
            'flat_id' => $flat->id,
            'bill_period' => '2026-10-01',
            'base_maintenance' => 3800,
            'utility_charges' => 450,
            'sinking_fund' => 250,
            'penalty_fine' => 0,
            'total_amount' => 4500,
            'due_date' => '2026-10-15',
            'payment_status' => 'unpaid',
            'generated_by_user_id' => $owner->id,
        ]);

        $this->actingAs($owner)->get(route('bills.download', $bill))
            ->assertDownload('BILL-202610-A101.pdf')
            ->assertHeader('Content-Type', 'application/pdf');

        $admin = $this->createUser('admin', 'admin@example.com');
        $this->actingAs($admin)->get(route('bills.download', $bill))
            ->assertDownload('BILL-202610-A101.pdf')
            ->assertHeader('Content-Type', 'application/pdf');

        $otherOwner = $this->createUser('resident', 'other-owner@example.com');
        $this->actingAs($otherOwner)->get(route('bills.download', $bill))
            ->assertForbidden();
    }

    public function test_billing_page_shows_clear_bill_cards_and_grouped_admin_tools(): void
    {
        $owner = $this->createUser('resident', 'owner@example.com');
        $flat = $this->createFlat($owner, 'A', '101', 3800);
        $bill = Bill::create([
            'bill_number' => 'BILL-202610-A101',
            'flat_id' => $flat->id,
            'bill_period' => '2026-10-01',
            'base_maintenance' => 3800,
            'utility_charges' => 450,
            'sinking_fund' => 250,
            'penalty_fine' => 0,
            'total_amount' => 4500,
            'due_date' => '2026-10-15',
            'payment_status' => 'unpaid',
            'generated_by_user_id' => $owner->id,
        ]);

        $this->actingAs($owner)->get(route('dashboard', ['tab' => 'billing']))
            ->assertOk()
            ->assertSee($bill->bill_number)
            ->assertSee('Total amount')
            ->assertSee('Download bill PDF')
            ->assertSee('Pay / submit payment proof')
            ->assertSee('Submit for verification');

        $admin = $this->createUser('admin', 'admin@example.com');
        $this->actingAs($admin)->get(route('dashboard', ['tab' => 'billing']))
            ->assertOk()
            ->assertSee('Billing tools')
            ->assertSee('All maintenance bills')
            ->assertSee($owner->full_name)
            ->assertSee('Record payment');
    }

    public function test_monthly_bill_import_rejects_every_row_when_one_row_is_invalid(): void
    {
        $admin = $this->createUser('admin', 'admin@example.com');
        $resident = $this->createUser('resident', 'resident@example.com');
        $this->createFlat($resident, 'A', '101', 3800);
        $csv = implode("\n", [
            'wing,flat_number,bill_period,base_maintenance,utility_charges,sinking_fund,penalty_fine,due_date,payment_status,payment_method,payment_transaction_id,paid_date,notes',
            'A,101,2026-10,3800,450,250,0,2026-10-15,unpaid,,,,October bill',
            'A,101,2026-10,3800,450,250,0,2026-10-15,paid,,TX-002,2026-10-05,Missing payment method',
        ]);

        $this->actingAs($admin)->post(route('bills.import'), [
            'file' => UploadedFile::fake()->createWithContent('maintenance.csv', $csv),
        ])->assertRedirect()->assertSessionHasErrors('import');

        $this->assertDatabaseCount('bills', 0);
    }

    public function test_bill_generation_skips_flats_already_billed_for_the_month(): void
    {
        $admin = $this->createUser('admin', 'admin@example.com');
        $this->createFlat($admin, 'A', '101', 3500);
        $this->createFlat($admin, 'B', '202', 4000);

        $payload = [
            'bill_period' => now()->format('Y-m'),
            'due_date' => now()->format('Y-m').'-15',
        ];

        $this->actingAs($admin)->post(route('bills.generate'), $payload)->assertRedirect();
        $this->post(route('bills.generate'), $payload)->assertRedirect();

        $this->assertSame(2, Bill::count());
        $this->assertDatabaseHas('bills', ['flat_id' => 1, 'total_amount' => 4200]);
    }

    public function test_resident_can_record_payment_for_their_own_bill(): void
    {
        $resident = $this->createUser('resident', 'resident@example.com');
        $flat = $this->createFlat($resident, 'A', '101', 3500);
        $bill = Bill::create([
            'bill_number' => 'BILL-'.now()->format('Ym').'-A101',
            'flat_id' => $flat->id,
            'bill_period' => now()->startOfMonth(),
            'base_maintenance' => 3500,
            'utility_charges' => 450,
            'sinking_fund' => 250,
            'penalty_fine' => 0,
            'total_amount' => 4200,
            'due_date' => now()->startOfMonth()->addDays(14),
            'payment_status' => 'unpaid',
            'generated_by_user_id' => $resident->id,
        ]);

        $this->actingAs($resident)->post(route('bills.pay', $bill), [
            'payment_method' => 'upi',
            'payment_transaction_id' => 'DEMO-RECEIPT-01',
        ])->assertRedirect();

        $this->assertDatabaseHas('bills', [
            'id' => $bill->id,
            'payment_status' => 'unpaid',
            'payment_transaction_id' => 'DEMO-RECEIPT-01',
        ]);
        $this->assertNotNull($bill->fresh()->payment_submitted_at);
        $this->assertNull($bill->fresh()->paid_date);
    }

    public function test_owner_payment_proof_can_be_rejected_then_resubmitted_and_verified_by_admin(): void
    {
        Storage::fake('local');
        $owner = $this->createUser('resident', 'owner@example.com');
        $flat = $this->createFlat($owner, 'A', '101', 3800);
        $bill = Bill::create([
            'bill_number' => 'BILL-'.now()->format('Ym').'-A101',
            'flat_id' => $flat->id,
            'bill_period' => now()->startOfMonth(),
            'base_maintenance' => 3800,
            'utility_charges' => 450,
            'sinking_fund' => 250,
            'penalty_fine' => 0,
            'total_amount' => 4500,
            'due_date' => now()->startOfMonth()->addDays(14),
            'payment_status' => 'unpaid',
            'generated_by_user_id' => $owner->id,
        ]);

        $this->actingAs($owner)->post(route('bills.pay', $bill), [
            'payment_method' => 'upi',
            'payment_proof' => UploadedFile::fake()->image('payment.png'),
        ])->assertRedirect(route('dashboard', ['tab' => 'billing']));

        $bill->refresh();
        $this->assertSame('unpaid', $bill->payment_status);
        $this->assertNotNull($bill->payment_submitted_at);
        Storage::disk('local')->assertExists($bill->payment_proof_path);

        $this->get(route('bills.payment-proof', $bill))->assertOk();

        $admin = $this->createUser('admin', 'admin@example.com');
        $this->actingAs($admin)->get(route('bills.payment-proof', $bill))->assertOk();
        $this->post(route('bills.review-payment', $bill), [
            'decision' => 'reject',
            'review_note' => 'Please provide a readable screenshot.',
        ])->assertRedirect(route('dashboard', ['tab' => 'billing']));

        $bill->refresh();
        $this->assertSame('unpaid', $bill->payment_status);
        $this->assertNull($bill->payment_submitted_at);
        $this->assertSame('Please provide a readable screenshot.', $bill->payment_review_note);

        $this->actingAs($owner)->post(route('bills.pay', $bill), [
            'payment_method' => 'upi',
            'payment_transaction_id' => 'TX-RETRY-01',
        ])->assertRedirect(route('dashboard', ['tab' => 'billing']));
        $this->assertNull($bill->fresh()->payment_proof_path);

        $this->actingAs($admin)->post(route('bills.review-payment', $bill), [
            'decision' => 'approve',
        ])->assertRedirect(route('dashboard', ['tab' => 'billing']));

        $this->assertDatabaseHas('bills', [
            'id' => $bill->id,
            'payment_status' => 'paid',
            'payment_transaction_id' => 'TX-RETRY-01',
            'payment_review_note' => null,
        ]);
        $this->assertNotNull($bill->fresh()->paid_date);
    }

    public function test_owner_must_submit_a_transaction_reference_or_payment_screenshot(): void
    {
        $owner = $this->createUser('resident', 'owner@example.com');
        $flat = $this->createFlat($owner, 'A', '101', 3800);
        $bill = Bill::create([
            'bill_number' => 'BILL-'.now()->format('Ym').'-A101',
            'flat_id' => $flat->id,
            'bill_period' => now()->startOfMonth(),
            'base_maintenance' => 3800,
            'utility_charges' => 450,
            'sinking_fund' => 250,
            'penalty_fine' => 0,
            'total_amount' => 4500,
            'due_date' => now()->startOfMonth()->addDays(14),
            'payment_status' => 'unpaid',
            'generated_by_user_id' => $owner->id,
        ]);

        $this->actingAs($owner)->post(route('bills.pay', $bill), [
            'payment_method' => 'upi',
        ])->assertRedirect()->assertSessionHasErrors('payment_proof');

        $this->assertNull($bill->fresh()->payment_submitted_at);
        $this->assertSame('unpaid', $bill->fresh()->payment_status);
    }

    public function test_resident_cannot_record_payment_for_another_flat(): void
    {
        $resident = $this->createUser('resident', 'resident@example.com');
        $otherResident = $this->createUser('resident', 'other@example.com');
        $otherFlat = $this->createFlat($otherResident, 'B', '202', 4000);
        $bill = Bill::create([
            'bill_number' => 'BILL-'.now()->format('Ym').'-B202',
            'flat_id' => $otherFlat->id,
            'bill_period' => now()->startOfMonth(),
            'base_maintenance' => 4000,
            'utility_charges' => 450,
            'sinking_fund' => 250,
            'penalty_fine' => 0,
            'total_amount' => 4700,
            'due_date' => now()->startOfMonth()->addDays(14),
            'payment_status' => 'unpaid',
            'generated_by_user_id' => $otherResident->id,
        ]);

        $this->actingAs($resident)->post(route('bills.pay', $bill), [
            'payment_method' => 'upi',
        ])->assertForbidden();

        $this->assertDatabaseHas('bills', ['id' => $bill->id, 'payment_status' => 'unpaid']);
    }

    public function test_resident_registration_creates_user_and_flat_and_signs_them_in(): void
    {
        $society = Society::firstOrFail();
        $this->post(route('register.store'), [
            'full_name' => 'New Resident',
            'email' => 'new.resident@example.com',
            'phone' => '555-0199',
            'society_id' => $society->id,
            'password' => 'ResidentPass123',
            'password_confirmation' => 'ResidentPass123',
            'wing' => 'C',
            'flat_number' => '305',
            'occupancy_status' => 'owner_occupied',
        ])->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('users', ['email' => 'new.resident@example.com', 'role' => 'resident']);
        $this->assertDatabaseHas('flats', ['wing' => 'C', 'flat_number' => '305']);
        $this->assertAuthenticated();
    }

    public function test_society_registration_waits_for_super_admin_approval(): void
    {
        $this->post(route('society-register.store'), [
            'society_name' => 'Green Valley Heights',
            'address' => '12 Garden Road',
            'admin_name' => 'Meera Shah',
            'email' => 'meera@greenvalley.example',
            'phone' => '555-0111',
            'password' => 'SocietyPass123',
            'password_confirmation' => 'SocietyPass123',
        ])->assertRedirect(route('login'))
            ->assertSessionHas('success');

        $society = Society::where('slug', 'green-valley-heights')->firstOrFail();
        $this->assertSame('pending', $society->status);
        $this->assertDatabaseHas('users', [
            'email' => 'meera@greenvalley.example',
            'role' => 'admin',
            'status' => 'pending',
            'society_id' => $society->id,
        ]);
        $this->post(route('login.store'), [
            'email' => 'meera@greenvalley.example',
            'password' => 'SocietyPass123',
            'role' => 'admin',
        ])->assertRedirect()->assertSessionHasErrors('email');

        $superAdmin = $this->createUser('super_admin', 'platform@example.com');
        $this->actingAs($superAdmin)->post(route('notices.store'), [
            'title' => 'No workspace selected',
            'category' => 'general',
            'priority' => 'normal',
            'content' => 'This must not be created without choosing a society.',
        ])->assertForbidden();

        $this->actingAs($superAdmin)->patch(route('super-admin.societies.status', $society), [
            'status' => 'active',
        ])->assertRedirect(route('dashboard', ['tab' => 'societies']));

        $this->assertDatabaseHas('societies', ['id' => $society->id, 'status' => 'active']);
        $this->assertDatabaseHas('users', ['email' => 'meera@greenvalley.example', 'status' => 'active']);
        $this->assertDatabaseHas('activity_logs', [
            'society_id' => $society->id,
            'actor_user_id' => $superAdmin->id,
            'action' => 'status_changed',
        ]);

        $this->actingAs($superAdmin)->post(route('super-admin.societies.switch', $society))
            ->assertRedirect(route('dashboard'));
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Green Valley Heights')
            ->assertSee('Billing');
        $this->post(route('notices.store'), [
            'title' => 'Platform workspace test',
            'category' => 'general',
            'priority' => 'normal',
            'content' => 'Created inside the selected society workspace.',
        ])->assertRedirect(route('dashboard', ['tab' => 'notices']));
        $this->assertDatabaseHas('notices', [
            'title' => 'Platform workspace test',
            'society_id' => $society->id,
        ]);
        $this->delete(route('super-admin.societies.leave'))
            ->assertRedirect(route('dashboard', ['tab' => 'societies']));
        $this->get(route('dashboard', ['tab' => 'activity']))
            ->assertOk()
            ->assertSee('workspace');
    }

    public function test_existing_society_administrator_can_be_promoted_to_super_admin_by_confirmed_command(): void
    {
        $admin = $this->createUser('admin', 'promote@example.com');

        $this->artisan('society:promote-super-admin', ['email' => $admin->email])
            ->expectsConfirmation('Promote '.$admin->full_name.' ('.$admin->email.') to super admin?', 'yes')
            ->expectsOutput($admin->email.' is now a super admin.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'role' => 'super_admin',
            'status' => 'active',
        ]);
    }

    public function test_society_administrators_cannot_view_another_societys_bills_or_records(): void
    {
        $societyOne = Society::create(['name' => 'River Park', 'slug' => 'river-park', 'status' => 'active']);
        $societyTwo = Society::create(['name' => 'Hill View', 'slug' => 'hill-view', 'status' => 'active']);
        $admin = User::create([
            'full_name' => 'River Admin',
            'email' => 'river.admin@example.com',
            'phone' => '555-0101',
            'password' => 'Secret@123',
            'role' => 'admin',
            'status' => 'active',
            'society_id' => $societyOne->id,
        ]);
        $owner = User::create([
            'full_name' => 'Hill Resident',
            'email' => 'hill.resident@example.com',
            'phone' => '555-0102',
            'password' => 'Secret@123',
            'role' => 'resident',
            'status' => 'active',
            'society_id' => $societyTwo->id,
        ]);
        $flat = Flat::create([
            'wing' => 'A',
            'flat_number' => '101',
            'owner_user_id' => $owner->id,
            'monthly_maintenance_amount' => 3500,
            'society_id' => $societyTwo->id,
        ]);
        $bill = Bill::create([
            'bill_number' => 'BILL-202610-A101',
            'flat_id' => $flat->id,
            'bill_period' => '2026-10-01',
            'total_amount' => 4500,
            'due_date' => '2026-10-15',
            'payment_status' => 'unpaid',
            'generated_by_user_id' => $admin->id,
            'society_id' => $societyTwo->id,
        ]);

        $this->actingAs($admin)->get(route('dashboard', ['tab' => 'billing']))
            ->assertOk()
            ->assertDontSee($bill->bill_number)
            ->assertDontSee('Hill Resident');
        $this->get(route('bills.download', $bill))->assertNotFound();
    }

    public function test_resident_can_submit_a_complaint_and_staff_can_update_its_history(): void
    {
        $resident = $this->createUser('resident', 'resident@example.com');
        $admin = $this->createUser('admin', 'admin@example.com');
        $staff = $this->createUser('staff', 'staff@example.com');
        $flat = $this->createFlat($resident, 'A', '101', 3500);

        $this->actingAs($resident)->post(route('complaints.store'), [
            'category' => 'plumbing',
            'priority' => 'high',
            'subject' => 'Leaking kitchen tap',
            'description' => 'Water is leaking continuously below the kitchen sink.',
        ])->assertRedirect();

        $complaint = Complaint::firstOrFail();
        $this->assertSame($flat->id, $complaint->flat_id);
        $this->assertDatabaseCount('complaint_logs', 1);

        $this->actingAs($admin)->patch(route('complaints.update', $complaint), [
            'status' => 'in_progress',
            'assigned_to_user_id' => $staff->id,
            'remarks' => 'Maintenance visit scheduled.',
        ])->assertRedirect();

        $this->assertDatabaseHas('complaints', [
            'id' => $complaint->id,
            'status' => 'in_progress',
            'assigned_to_user_id' => $staff->id,
        ]);
        $this->assertDatabaseCount('complaint_logs', 2);
    }

    public function test_only_an_administrator_can_publish_a_notice(): void
    {
        $resident = $this->createUser('resident', 'resident@example.com');

        $this->actingAs($resident)->post(route('notices.store'), [
            'title' => 'Water shutdown',
            'category' => 'water_supply',
            'priority' => 'urgent',
            'content' => 'Water supply will be interrupted on Monday.',
        ])->assertForbidden();
    }

    public function test_password_reset_requests_return_a_generic_confirmation(): void
    {
        Notification::fake();
        $resident = $this->createUser('resident', 'resident@example.com');

        $this->post(route('password.email'), ['email' => $resident->email])
            ->assertRedirect()
            ->assertSessionHas('status', 'If an account matches that email, a password reset link has been sent.');
        Notification::assertSentTo($resident, ResetPassword::class);

        $this->post(route('password.email'), ['email' => 'unknown@example.com'])
            ->assertRedirect()
            ->assertSessionHas('status', 'If an account matches that email, a password reset link has been sent.');
    }

    public function test_password_can_be_changed_with_a_valid_reset_token(): void
    {
        $resident = $this->createUser('resident', 'resident@example.com');
        $token = Password::createToken($resident);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $resident->email,
            'password' => 'BrandNewPass123',
            'password_confirmation' => 'BrandNewPass123',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('BrandNewPass123', $resident->fresh()->password));
    }

    private function createUser(string $role, string $email): User
    {
        return User::create([
            'full_name' => ucfirst($role).' User',
            'email' => $email,
            'phone' => '555-0100',
            'password' => 'Secret@123',
            'role' => $role,
            'status' => 'active',
            'society_id' => $role === 'super_admin' ? null : Society::firstOrFail()->id,
        ]);
    }

    private function createFlat(User $owner, string $wing, string $number, float $maintenance): Flat
    {
        return Flat::create([
            'wing' => $wing,
            'flat_number' => $number,
            'floor' => 1,
            'carpet_sqft' => 900,
            'owner_user_id' => $owner->id,
            'occupancy_status' => 'owner_occupied',
            'parking_slot' => 'P-'.$wing.$number,
            'monthly_maintenance_amount' => $maintenance,
            'society_id' => Society::firstOrFail()->id,
        ]);
    }
}
