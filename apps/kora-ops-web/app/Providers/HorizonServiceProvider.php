<?php

namespace App\Providers;

use App\Models\User;
use App\Modules\Monitoring\Data\MonitoringPermission;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
    }

    /**
     * Horizon partage la permission de l'écran de supervision KORA OPS.
     */
    protected function gate(): void
    {
        Gate::define(
            'viewHorizon',
            fn (?User $user = null): bool => $user?->can(MonitoringPermission::View->value) ?? false,
        );
    }
}
