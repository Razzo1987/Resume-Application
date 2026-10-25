<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\ThemeServiceProvider;

return [
    AppServiceProvider::class,
    ThemeServiceProvider::class,
    AdminPanelProvider::class,
];
