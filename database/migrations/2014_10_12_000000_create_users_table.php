<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('full_name', 100);
            $table->string('email', 150)->unique();
            $table->string('phone', 20);
            $table->string('password');
            $table->enum('role', ['admin', 'resident', 'staff', 'super_admin'])->default('resident')->index();
            $table->string('emergency_contact', 20)->nullable();
            $table->string('avatar_url')->nullable();
            $table->string('status')->default('active')->index();
            $table->dateTime('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
