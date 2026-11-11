<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\OccupancyServiceProvider;

return [
    AppServiceProvider::class,
    OccupancyServiceProvider::class,
    AdminPanelProvider::class,
];
