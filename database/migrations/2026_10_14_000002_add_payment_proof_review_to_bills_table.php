<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->string('payment_proof_path')->nullable()->after('payment_transaction_id');
            $table->dateTime('payment_submitted_at')->nullable()->after('payment_proof_path');
            $table->text('payment_review_note')->nullable()->after('payment_submitted_at');
        });
    }

    public function down(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->dropColumn(['payment_proof_path', 'payment_submitted_at', 'payment_review_note']);
        });
    }
};
