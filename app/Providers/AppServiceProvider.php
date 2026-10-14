<?php

namespace App\Providers;

use App\Models\Bill;
use App\Models\Complaint;
use App\Models\ComplaintLog;
use App\Models\Flat;
use App\Models\Notice;
use App\Models\Society;
use App\Models\Tenant;
use App\Models\User;
use App\Observers\ActivityObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach ([Bill::class, Complaint::class, ComplaintLog::class, Flat::class, Notice::class, Society::class, Tenant::class, User::class] as $model) {
            $model::observe(ActivityObserver::class);
        }
    }
}
