<?php

namespace App\Providers;

use App\Modules\Boarding\Models\BoardingEvent;
use App\Modules\Boarding\Models\SeatReleaseEvent;
use App\Modules\Booking\Models\Reservation;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Cash\Models\CashSession;
use App\Modules\Departures\Models\Departure;
use App\Modules\DynamicPricing\Models\Fare;
use App\Modules\Fiscalization\Models\FiscalInvoice;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\IdentityAccess\Models\TerminalP501;
use App\Modules\Ticketing\Models\Ticket;
use App\Policies\AgencyPolicy;
use App\Policies\BoardingEventPolicy;
use App\Policies\BusPolicy;
use App\Policies\CashSessionPolicy;
use App\Policies\DeparturePolicy;
use App\Policies\FarePolicy;
use App\Policies\FiscalInvoicePolicy;
use App\Policies\LinePolicy;
use App\Policies\ReservationPolicy;
use App\Policies\SeatReleaseEventPolicy;
use App\Policies\StopPolicy;
use App\Policies\TerminalP501Policy;
use App\Policies\TicketPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
        $this->registerPolicies();
        $this->configureRateLimiting();
        $this->configureDefaults();
    }

    /**
     * Register centralized authorization policies for KORA BUS modules.
     */
    protected function registerPolicies(): void
    {
        Gate::policy(Agency::class, AgencyPolicy::class);
        Gate::policy(BoardingEvent::class, BoardingEventPolicy::class);
        Gate::policy(Bus::class, BusPolicy::class);
        Gate::policy(CashSession::class, CashSessionPolicy::class);
        Gate::policy(Departure::class, DeparturePolicy::class);
        Gate::policy(Fare::class, FarePolicy::class);
        Gate::policy(FiscalInvoice::class, FiscalInvoicePolicy::class);
        Gate::policy(Line::class, LinePolicy::class);
        Gate::policy(Reservation::class, ReservationPolicy::class);
        Gate::policy(SeatReleaseEvent::class, SeatReleaseEventPolicy::class);
        Gate::policy(Stop::class, StopPolicy::class);
        Gate::policy(TerminalP501::class, TerminalP501Policy::class);
        Gate::policy(Ticket::class, TicketPolicy::class);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        RateLimiter::for('bus-search', fn (Request $request): Limit => Limit::perMinute(120)
            ->by($request->ip()));

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(function (): Password {
            $rule = Password::min(8)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised(3);

            return app()->isProduction() ? $rule->min(12) : $rule;
        });
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('auth-otp', fn (Request $request) => Limit::perMinute(3)->by(
            $request->ip().'|'.strtolower((string) $request->input('email')),
        ));

        RateLimiter::for('auth-login', fn (Request $request) => Limit::perMinute(5)->by(
            $request->ip().'|'.strtolower((string) $request->input('email')),
        ));
    }
}
