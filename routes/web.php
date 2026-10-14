<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SocietyRegistrationController;
use App\Http\Controllers\SuperAdminController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login.store');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1')->name('register.store');
    Route::get('/register-society', [SocietyRegistrationController::class, 'create'])->name('society-register');
    Route::post('/register-society', [SocietyRegistrationController::class, 'store'])->middleware('throttle:5,1')->name('society-register.store');
    Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'showResetPassword'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1')->name('password.update');
});

Route::middleware(['auth', 'society.initialize'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::post('/tenant', [DashboardController::class, 'storeTenant'])->name('tenant.store');
    Route::delete('/tenant', [DashboardController::class, 'destroyTenant'])->name('tenant.destroy');
    Route::get('/tenant-agreements/{tenant}', [DashboardController::class, 'downloadTenantAgreement'])
        ->name('tenant-agreements.download');
    Route::get('/bills/{bill}/download', [DashboardController::class, 'downloadBill'])->name('bills.download');
    Route::get('/bills/{bill}/payment-proof', [DashboardController::class, 'downloadPaymentProof'])->name('bills.payment-proof');
    Route::post('/complaints', [DashboardController::class, 'storeComplaint'])->name('complaints.store');
    Route::post('/bills/{bill}/pay', [DashboardController::class, 'payBill'])->middleware('society.context')->name('bills.pay');

    Route::middleware('role:super_admin')->group(function () {
        Route::patch('/super-admin/societies/{society}/status', [SuperAdminController::class, 'updateSocietyStatus'])->name('super-admin.societies.status');
        Route::post('/super-admin/societies/{society}/workspace', [SuperAdminController::class, 'switchSociety'])->name('super-admin.societies.switch');
        Route::delete('/super-admin/workspace', [SuperAdminController::class, 'leaveSociety'])->name('super-admin.societies.leave');
    });

    Route::middleware(['role:admin,staff,super_admin', 'society.context'])->group(function () {
        Route::patch('/complaints/{complaint}', [DashboardController::class, 'updateComplaint'])->name('complaints.update');
    });

    Route::middleware(['role:admin,super_admin', 'society.context'])->group(function () {
        Route::post('/bills/{bill}/review-payment', [DashboardController::class, 'reviewBillPayment'])->name('bills.review-payment');
        Route::post('/residents', [DashboardController::class, 'storeResident'])->name('residents.store');
        Route::post('/residents/import', [DashboardController::class, 'importResidents'])->name('residents.import');
        Route::get('/residents/import-template', [DashboardController::class, 'residentImportTemplate'])->name('residents.import-template');
        Route::get('/residents/import-sample', [DashboardController::class, 'residentImportSample'])->name('residents.import-sample');
        Route::post('/bills/import', [DashboardController::class, 'importBills'])->name('bills.import');
        Route::get('/bills/import-template', [DashboardController::class, 'billImportTemplate'])->name('bills.import-template');
        Route::get('/bills/import-sample', [DashboardController::class, 'billImportSample'])->name('bills.import-sample');
        Route::post('/bills/single', [DashboardController::class, 'generateSingleBill'])->name('bills.generate-single');
        Route::post('/notices', [DashboardController::class, 'storeNotice'])->name('notices.store');
        Route::post('/bills/generate', [DashboardController::class, 'generateBills'])->name('bills.generate');
    });
});
