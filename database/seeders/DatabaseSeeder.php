<?php

namespace Database\Seeders;

use App\Models\Bill;
use App\Models\Complaint;
use App\Models\Flat;
use App\Models\Notice;
use App\Models\Society;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $society = Society::where('slug', 'lotus-orchid')->firstOrFail();
        $admin = User::create([
            'full_name' => 'Sunita Sharma',
            'email' => 'admin@societymanager.org',
            'phone' => '+91 98201 12345',
            'password' => 'Secret@123',
            'role' => 'admin',
            'society_id' => $society->id,
            'avatar_url' => 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?w=150',
        ]);
        $ramesh = User::create([
            'full_name' => 'Ramesh Patel',
            'email' => 'ramesh.patel@gmail.com',
            'phone' => '+91 98980 54321',
            'password' => 'Secret@123',
            'role' => 'resident',
            'society_id' => $society->id,
            'avatar_url' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=150',
        ]);
        $ananya = User::create([
            'full_name' => 'Ananya Deshmukh',
            'email' => 'ananya.d@outlook.com',
            'phone' => '+91 98111 67890',
            'password' => 'Secret@123',
            'role' => 'resident',
            'society_id' => $society->id,
            'avatar_url' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150',
        ]);
        $vikram = User::create([
            'full_name' => 'Vikram Malhotra',
            'email' => 'vikram.m@gmail.com',
            'phone' => '+91 97654 32109',
            'password' => 'Secret@123',
            'role' => 'resident',
            'society_id' => $society->id,
            'avatar_url' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=150',
        ]);
        $staff = User::create([
            'full_name' => 'Suresh Kadam',
            'email' => 'maintenance.lead@societymanager.org',
            'phone' => '+91 99000 11223',
            'password' => 'Secret@123',
            'role' => 'staff',
            'society_id' => $society->id,
        ]);

        $flats = collect([
            ['wing' => 'A', 'flat_number' => '402', 'floor' => 4, 'carpet_sqft' => 1150, 'owner_user_id' => $ramesh->id, 'occupancy_status' => 'owner_occupied', 'parking_slot' => 'P-A42', 'monthly_maintenance_amount' => 4200],
            ['wing' => 'B', 'flat_number' => '201', 'floor' => 2, 'carpet_sqft' => 890, 'owner_user_id' => $ananya->id, 'occupancy_status' => 'tenant_occupied', 'parking_slot' => 'P-B18', 'monthly_maintenance_amount' => 3500],
            ['wing' => 'A', 'flat_number' => '104', 'floor' => 1, 'carpet_sqft' => 950, 'owner_user_id' => $vikram->id, 'occupancy_status' => 'owner_occupied', 'parking_slot' => 'P-A12', 'monthly_maintenance_amount' => 3800],
            ['wing' => 'C', 'flat_number' => '603', 'floor' => 6, 'carpet_sqft' => 1400, 'owner_user_id' => $admin->id, 'occupancy_status' => 'owner_occupied', 'parking_slot' => 'P-C05', 'monthly_maintenance_amount' => 4800],
        ])->map(fn (array $flat) => Flat::create([...$flat, 'society_id' => $society->id]));

        $period = now()->startOfMonth();
        $previousPeriod = $period->copy()->subMonth();
        $dueDate = $period->copy()->day(15);
        $bills = [
            [
                'flat_id' => $flats[0]->id,
                'bill_period' => $period,
                'base_maintenance' => 4200,
                'utility_charges' => 450,
                'sinking_fund' => 250,
                'total_amount' => 4900,
                'due_date' => $dueDate,
                'payment_status' => 'unpaid',
                'notes' => 'Includes monthly clubhouse and garden maintenance.',
            ],
            [
                'flat_id' => $flats[0]->id,
                'bill_period' => $previousPeriod,
                'base_maintenance' => 4200,
                'utility_charges' => 380,
                'sinking_fund' => 250,
                'total_amount' => 4830,
                'due_date' => $previousPeriod->copy()->day(15),
                'payment_status' => 'paid',
                'paid_date' => $previousPeriod->copy()->day(11)->setTime(14, 22),
                'payment_method' => 'upi',
                'payment_transaction_id' => 'UPI/DEMO/2601',
                'notes' => 'Paid on time via resident portal.',
            ],
            [
                'flat_id' => $flats[1]->id,
                'bill_period' => $period,
                'base_maintenance' => 3500,
                'utility_charges' => 320,
                'sinking_fund' => 200,
                'total_amount' => 4020,
                'due_date' => $dueDate,
                'payment_status' => 'unpaid',
                'notes' => 'Regular monthly society dues.',
            ],
            [
                'flat_id' => $flats[2]->id,
                'bill_period' => $period,
                'base_maintenance' => 3800,
                'utility_charges' => 410,
                'sinking_fund' => 250,
                'penalty_fine' => 150,
                'total_amount' => 4610,
                'due_date' => $period->copy()->day(5),
                'payment_status' => 'overdue',
                'notes' => 'Includes a late payment fee.',
            ],
        ];

        foreach ($bills as $bill) {
            Bill::create([
                ...$bill,
                'bill_number' => 'BILL-'.$bill['bill_period']->format('Ym').'-'.$flats->firstWhere('id', $bill['flat_id'])->wing.$flats->firstWhere('id', $bill['flat_id'])->flat_number,
                'generated_by_user_id' => $admin->id,
                'society_id' => $society->id,
            ]);
        }

        foreach ([
            ['title' => 'Urgent: Water Tank Cleaning & Supply Interruption', 'category' => 'water_supply', 'priority' => 'urgent', 'content' => 'Overhead water tanks will be cleaned and sanitized. Please store sufficient water before the scheduled interruption.', 'pinned' => true],
            ['title' => 'Annual General Body Meeting', 'category' => 'meeting', 'priority' => 'high', 'content' => 'Residents are invited to the annual general body meeting at the society clubhouse. The agenda includes audited accounts, lift modernization and solar installation.', 'pinned' => true],
            ['title' => 'Fire Safety Drill & Extinguisher Inspection', 'category' => 'security', 'priority' => 'normal', 'content' => 'The fire safety team will conduct an evacuation drill and inspect lobby extinguishers. Please keep corridors clear.', 'pinned' => false],
            ['title' => 'Festive Decoration & Visitor Parking Guidelines', 'category' => 'event', 'priority' => 'low', 'content' => 'Visitor parking spaces are reserved for temporary loading and emergency vehicles during society events.', 'pinned' => false],
        ] as $notice) {
            Notice::create([
                ...$notice,
                'scheduled_publish_at' => now()->subDays(4),
                'created_by_user_id' => $admin->id,
                'society_id' => $society->id,
            ]);
        }

        $complaint = Complaint::create([
            'ticket_number' => 'TKT-'.now()->format('Y').'-DEMO001',
            'flat_id' => $flats[0]->id,
            'resident_user_id' => $ramesh->id,
            'category' => 'plumbing',
            'subject' => 'Low water pressure in bathroom',
            'description' => 'The shower pressure has been low since yesterday. Please inspect the rooftop pipeline.',
            'priority' => 'high',
            'status' => 'in_progress',
            'assigned_to_user_id' => $staff->id,
            'resolution_notes' => 'Inspection is scheduled.',
            'society_id' => $society->id,
        ]);

        $complaint->logs()->create([
            'action_by_user_id' => $ramesh->id,
            'society_id' => $society->id,
            'previous_status' => null,
            'new_status' => 'submitted',
            'remarks' => 'Complaint filed by resident.',
        ]);
        $complaint->logs()->create([
            'action_by_user_id' => $admin->id,
            'society_id' => $society->id,
            'previous_status' => 'submitted',
            'new_status' => 'in_progress',
            'remarks' => 'Assigned to the maintenance team for inspection.',
        ]);
    }
}
