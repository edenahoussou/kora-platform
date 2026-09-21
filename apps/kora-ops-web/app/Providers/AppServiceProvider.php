<?php

namespace App\Providers;

use App\Models\User;
use App\Modules\AssistedBooking\Models\AssistedBooking;
use App\Modules\Boarding\Models\BoardingEvent;
use App\Modules\Boarding\Models\SeatReleaseEvent;
use App\Modules\Booking\Models\Reservation;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Cash\Models\CashSession;
use App\Modules\CompanyAccounts\Models\CompanyAccount;
use App\Modules\Departures\Models\Departure;
use App\Modules\DynamicPricing\Models\Fare;
use App\Modules\Expenses\Models\Expense;
use App\Modules\Fiscalization\Models\FiscalInvoice;
use App\Modules\Fiscalization\Services\DatabaseFiscalSyncAttemptRecorder;
use App\Modules\Fiscalization\Services\FiscalSyncAttemptRecorder;
use App\Modules\IdentityAccess\Contracts\PassengerIdentityResolver;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\IdentityAccess\Models\Role;
use App\Modules\IdentityAccess\Models\TerminalP501;
use App\Modules\IdentityAccess\Services\PhonePassengerIdentityResolver;
use App\Modules\Payments\Providers\FedaPayAdapter;
use App\Modules\Payments\Providers\MtnMomoAdapter;
use App\Modules\Ticketing\Models\Ticket;
use App\Policies\AgencyPolicy;
use App\Policies\AssistedBookingPolicy;
use App\Policies\BoardingEventPolicy;
use App\Policies\BusPolicy;
use App\Policies\CashSessionPolicy;
use App\Policies\CompanyAccountPolicy;
use App\Policies\DeparturePolicy;
use App\Policies\ExpensePolicy;
use App\Policies\FarePolicy;
use App\Policies\FiscalInvoicePolicy;
use App\Policies\LinePolicy;
use App\Policies\ReservationPolicy;
use App\Policies\RolePolicy;
use App\Policies\SeatReleaseEventPolicy;
use App\Policies\StopPolicy;
use App\Policies\TerminalP501Policy;
use App\Policies\TicketPolicy;
use App\Policies\UserPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\TikTok\TikTokExtendSocialite;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->registerPaymentProviders();
        $this->app->bind(PassengerIdentityResolver::class, PhonePassengerIdentityResolver::class);
        $this->app->bind(FiscalSyncAttemptRecorder::class, DatabaseFiscalSyncAttemptRecorder::class);
    }

    /**
     * Bind payment provider adapters with their configuration.
     */
    protected function registerPaymentProviders(): void
    {
        $this->app->bind(FedaPayAdapter::class, function (): FedaPayAdapter {
            $config = config('payments.providers.fedapay', []);

            return new FedaPayAdapter(
                apiKey: (string) ($config['secret_key'] ?? ''),
                environment: (string) ($config['environment'] ?? 'sandbox'),
                callbackUrl: $config['callback_url'] ?? null,
                webhookSecret: $config['webhook_secret'] ?? null,
                defaultPhoneCountry: (string) ($config['default_phone_country'] ?? 'bj'),
                mobileMoneyModes: (array) ($config['mobile_money_modes'] ?? []),
            );
        });

        $this->app->bind(MtnMomoAdapter::class, function (): MtnMomoAdapter {
            $config = config('payments.providers.mtn_momo', []);

            return new MtnMomoAdapter(
                subscriptionKey: (string) ($config['subscription_key'] ?? ''),
                userId: (string) ($config['user_id'] ?? ''),
                apiKey: (string) ($config['api_key'] ?? ''),
                environment: (string) ($config['environment'] ?? 'sandbox'),
                targetEnvironment: (string) ($config['target_environment'] ?? 'mtnbenin'),
                sandboxCurrency: (string) ($config['sandbox_currency'] ?? 'EUR'),
                productionCurrency: (string) ($config['production_currency'] ?? 'XOF'),
                callbackUrl: $config['callback_url'] ?? null,
                webhookSecret: $config['webhook_secret'] ?? null,
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
        $this->configureRateLimiting();
        $this->configureDefaults();
        $this->registerSocialiteProviders();
    }

    /**
     * Enregistre les providers OAuth tiers (TikTok) via SocialiteProviders.
     * Google et Facebook sont nativement supportés par Laravel Socialite.
     */
    protected function registerSocialiteProviders(): void
    {
        Event::listen(SocialiteWasCalled::class, TikTokExtendSocialite::class);
    }

    /**
     * Register centralized authorization policies for KORA BUS modules.
     */
    protected function registerPolicies(): void
    {
        Gate::policy(Agency::class, AgencyPolicy::class);
        Gate::policy(AssistedBooking::class, AssistedBookingPolicy::class);
        Gate::policy(BoardingEvent::class, BoardingEventPolicy::class);
        Gate::policy(Bus::class, BusPolicy::class);
        Gate::policy(CashSession::class, CashSessionPolicy::class);
        Gate::policy(Departure::class, DeparturePolicy::class);
        Gate::policy(Fare::class, FarePolicy::class);
        Gate::policy(FiscalInvoice::class, FiscalInvoicePolicy::class);
        Gate::policy(Expense::class, ExpensePolicy::class);
        Gate::policy(Line::class, LinePolicy::class);
        Gate::policy(Reservation::class, ReservationPolicy::class);
        Gate::policy(CompanyAccount::class, CompanyAccountPolicy::class);
        Gate::policy(SeatReleaseEvent::class, SeatReleaseEventPolicy::class);
        Gate::policy(Stop::class, StopPolicy::class);
        Gate::policy(TerminalP501::class, TerminalP501Policy::class);
        Gate::policy(Ticket::class, TicketPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        RateLimiter::for('bus-search', fn (Request $request): Limit => Limit::perMinute(120)
            ->by($request->ip()));

        RateLimiter::for('bus-itinerary-search', fn (Request $request): Limit => Limit::perMinute(30)
            ->by($request->ip()));

        RateLimiter::for('bus-itinerary-actions', fn (Request $request): Limit => Limit::perMinute(20)
            ->by($request->user()?->getAuthIdentifier().'|'.$request->ip()));

        RateLimiter::for('payment-verify', fn (Request $request): Limit => Limit::perMinute(6)
            ->by($request->user()?->getAuthIdentifier().'|'.$request->route('payment')));

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

        RateLimiter::for('terminal-handshake', fn (Request $request) => Limit::perMinute(10)->by(
            $request->user()?->getAuthIdentifier().'|'.$request->ip(),
        ));
    }
}
