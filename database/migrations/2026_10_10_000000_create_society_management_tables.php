<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flats', function (Blueprint $table) {
            $table->id();
            $table->string('wing', 10);
            $table->string('flat_number', 20);
            $table->unsignedInteger('floor')->default(1);
            $table->unsignedInteger('carpet_sqft')->default(950);
            $table->foreignId('owner_user_id')->constrained('users')->restrictOnDelete()->cascadeOnUpdate();
            $table->enum('occupancy_status', ['owner_occupied', 'tenant_occupied', 'vacant'])->default('owner_occupied');
            $table->string('parking_slot', 30)->nullable();
            $table->decimal('monthly_maintenance_amount', 10, 2)->default(3500);
            $table->timestamps();
            $table->unique(['wing', 'flat_number']);
            $table->index('occupancy_status');
        });

        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->string('bill_number', 50)->unique();
            $table->foreignId('flat_id')->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->date('bill_period');
            $table->decimal('base_maintenance', 10, 2)->default(3500);
            $table->decimal('utility_charges', 10, 2)->default(450);
            $table->decimal('sinking_fund', 10, 2)->default(250);
            $table->decimal('penalty_fine', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2);
            $table->date('due_date');
            $table->enum('payment_status', ['unpaid', 'paid', 'overdue', 'partially_paid'])->default('unpaid');
            $table->dateTime('paid_date')->nullable();
            $table->enum('payment_method', ['upi', 'netbanking', 'card', 'cheque', 'cash'])->nullable();
            $table->string('payment_transaction_id', 100)->nullable();
            $table->foreignId('generated_by_user_id')->constrained('users')->restrictOnDelete()->cascadeOnUpdate();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['flat_id', 'payment_status']);
            $table->index('bill_period');
            $table->unique(['flat_id', 'bill_period']);
        });

        Schema::create('notices', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->enum('category', ['water_supply', 'maintenance', 'meeting', 'security', 'event', 'general'])->default('general');
            $table->enum('priority', ['urgent', 'high', 'normal', 'low'])->default('normal');
            $table->text('content');
            $table->dateTime('scheduled_publish_at')->useCurrent();
            $table->dateTime('expires_at')->nullable();
            $table->boolean('is_published')->default(true);
            $table->boolean('pinned')->default(false);
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete()->cascadeOnUpdate();
            $table->timestamps();
            $table->index(['is_published', 'scheduled_publish_at']);
        });

        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number', 50)->unique();
            $table->foreignId('flat_id')->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('resident_user_id')->constrained('users')->restrictOnDelete()->cascadeOnUpdate();
            $table->enum('category', ['plumbing', 'electrical', 'lift', 'security', 'general']);
            $table->string('subject', 200);
            $table->text('description');
            $table->enum('priority', ['urgent', 'high', 'medium', 'low'])->default('medium');
            $table->enum('status', ['submitted', 'in_progress', 'resolved', 'closed'])->default('submitted')->index();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->text('resolution_notes')->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->timestamps();
            $table->index(['resident_user_id', 'created_at']);
        });

        Schema::create('complaint_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complaint_id')->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('action_by_user_id')->constrained('users')->restrictOnDelete()->cascadeOnUpdate();
            $table->enum('previous_status', ['submitted', 'in_progress', 'resolved', 'closed'])->nullable();
            $table->enum('new_status', ['submitted', 'in_progress', 'resolved', 'closed']);
            $table->text('remarks');
            $table->timestamp('created_at')->useCurrent();
            $table->index('complaint_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complaint_logs');
        Schema::dropIfExists('complaints');
        Schema::dropIfExists('notices');
        Schema::dropIfExists('bills');
        Schema::dropIfExists('flats');
    }
};
