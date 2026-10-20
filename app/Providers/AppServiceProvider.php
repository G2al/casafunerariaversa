<?php

namespace App\Providers;

use App\Models\CompanySettings;
use App\Models\FuneralCase;
use App\Models\FuneralStaffMember;
use App\Models\FuneralVehicle;
use App\Models\ManifestTemplate;
use App\Models\User;
use App\Policies\AdminPolicy;
use App\Policies\FuneralCasePolicy;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
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
        foreach ([User::class, CompanySettings::class, ManifestTemplate::class, FuneralStaffMember::class, FuneralVehicle::class] as $model) {
            Gate::policy($model, AdminPolicy::class);
        }
        Gate::policy(FuneralCase::class, FuneralCasePolicy::class);
        Carbon::setLocale('it');
    }
}
