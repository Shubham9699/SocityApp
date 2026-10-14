<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('societies', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('slug', 180)->unique();
            $table->string('address')->nullable();
            $table->enum('status', ['pending', 'active', 'suspended'])->default('pending')->index();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->timestamps();
        });

        $now = now();
        $legacySocietyId = DB::table('societies')->insertGetId([
            'name' => 'Lotus Orchid',
            'slug' => 'lotus-orchid',
            'address' => null,
            'status' => 'active',
            'approved_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach (['users', 'flats', 'bills', 'notices', 'complaints', 'tenants', 'complaint_logs'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('society_id')->nullable()->constrained('societies')->restrictOnDelete();
            });
            DB::table($tableName)->whereNull('society_id')->update(['society_id' => $legacySocietyId]);
        }

        Schema::table('flats', function (Blueprint $table) {
            $table->dropUnique('flats_wing_flat_number_unique');
            $table->unique(['society_id', 'wing', 'flat_number']);
        });

        Schema::table('bills', function (Blueprint $table) {
            $table->dropUnique('bills_bill_number_unique');
            $table->dropUnique('bills_flat_id_bill_period_unique');
            $table->unique(['society_id', 'bill_number']);
            $table->unique(['society_id', 'flat_id', 'bill_period']);
        });

        Schema::table('complaints', function (Blueprint $table) {
            $table->dropUnique('complaints_ticket_number_unique');
            $table->unique(['society_id', 'ticket_number']);
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->nullable()->constrained('societies')->nullOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 30);
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('description', 500);
            $table->json('changed_fields')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
            $table->index(['society_id', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');

        Schema::table('complaints', function (Blueprint $table) {
            $table->dropUnique('complaints_society_id_ticket_number_unique');
            $table->unique('ticket_number');
        });
        Schema::table('bills', function (Blueprint $table) {
            $table->dropUnique('bills_society_id_bill_number_unique');
            $table->dropUnique('bills_society_id_flat_id_bill_period_unique');
            $table->unique('bill_number');
            $table->unique(['flat_id', 'bill_period']);
        });
        Schema::table('flats', function (Blueprint $table) {
            $table->dropUnique('flats_society_id_wing_flat_number_unique');
            $table->unique(['wing', 'flat_number']);
        });

        foreach (['complaint_logs', 'tenants', 'complaints', 'notices', 'bills', 'flats', 'users'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('society_id');
            });
        }

        Schema::dropIfExists('societies');
    }
};
