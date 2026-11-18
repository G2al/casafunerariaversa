<?php

namespace App\Providers;

use App\Livewire\ServiceOrderPanel;
use App\Models\CompanySettings;
use App\Models\FuneralCase;
use App\Models\FuneralStaffMember;
use App\Models\FuneralVehicle;
use App\Models\ManifestRecord;
use App\Models\ManifestTemplate;
use App\Models\Room;
use App\Models\ServiceOrder;
use App\Models\Symbol;
use App\Models\SymbolFolder;
use App\Models\User;
use App\Policies\AdminPolicy;
use App\Policies\FuneralCasePolicy;
use App\Policies\ManifestRecordPolicy;
use App\Policies\ServiceOrderPolicy;
use Filament\Forms\Components\DateTimePicker;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

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
        foreach ([Room::class, SymbolFolder::class, Symbol::class, User::class, CompanySettings::class, ManifestTemplate::class, FuneralStaffMember::class, FuneralVehicle::class] as $model) {
            Gate::policy($model, AdminPolicy::class);
        }
        Gate::policy(ManifestRecord::class, ManifestRecordPolicy::class);
        Gate::policy(FuneralCase::class, FuneralCasePolicy::class);
        Gate::policy(ServiceOrder::class, ServiceOrderPolicy::class);
        Gate::before(function ($user, $ability, $arguments) {
            if (! $user->isServiceOrderOperator()) {
                return null;
            }
            $subject = $arguments[0] ?? null;

            return ($subject instanceof ServiceOrder || $subject === ServiceOrder::class) ? null : false;
        });
        Livewire::component('service-order-panel', ServiceOrderPanel::class);
        Carbon::setLocale('it');
        DateTimePicker::configureUsing(fn ($field) => $field->native(true)->extraInputAttributes(['lang' => 'it']), isImportant: true);
    }
}
