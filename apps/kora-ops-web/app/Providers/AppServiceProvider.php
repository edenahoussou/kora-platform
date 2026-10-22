<?php

namespace App\Providers;

use App\Listeners\SendTwoFactorSecurityAlert;
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
use App\Modules\Fiscalization\Observers\FiscalInvoiceObserver;
use App\Modules\Fiscalization\Services\DatabaseFiscalSyncAttemptRecorder;
use App\Modules\Fiscalization\Services\DgiFiscalTestInvoiceGateway;
use App\Modules\Fiscalization\Services\FiscalSyncAttemptRecorder;
use App\Modules\Fiscalization\Services\FiscalTestInvoiceGateway;
use App\Modules\IdentityAccess\Contracts\PassengerIdentityResolver;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\IdentityAccess\Models\Role;
use App\Modules\IdentityAccess\Models\TerminalP501;
use App\Modules\IdentityAccess\Services\AuthIdentifierResolver;
use App\Modules\IdentityAccess\Services\PhonePassengerIdentityResolver;
use App\Modules\Monitoring\Data\MonitoringPermission;
use App\Modules\Notifications\Channels\OpsDatabaseChannel;
use App\Modules\Notifications\Channels\OpsFirebaseChannel;
use App\Modules\Notifications\Channels\OpsMailChannel;
use App\Modules\Notifications\Channels\PassengerDatabaseChannel;
use App\Modules\Notifications\Channels\PassengerFcmChannel;
use App\Modules\Notifications\Channels\SmsChannel;
use App\Modules\Notifications\Observers\PaymentRefundNotificationObserver;
use App\Modules\Payments\Models\PaymentRefund;
use App\Modules\Payments\Providers\FedaPayAdapter;
use App\Modules\Payments\Providers\KKiaPayAdapter;
use App\Modules\Payments\Providers\MtnMomoAdapter;
use App\Modules\Settings\Contracts\SettingsCoordinator;
use App\Modules\Settings\Services\DatabaseSettingsCoordinator;
use App\Modules\Settings\Settings\IntegrationsSettings;
use App\Modules\Support\Queue\IdempotentFailedJobProvider;
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
use Illuminate\Queue\Failed\DatabaseUuidFailedJobProvider;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Kkiapay\Kkiapay;
use Laravel\Fortify\Events\PasswordUpdatedViaController;
use Laravel\Fortify\Events\RecoveryCodesGenerated;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Opcodes\LogViewer\Facades\LogViewer;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\TikTok\TikTokExtendSocialite;
use Spatie\Health\Checks\Checks\CacheCheck;
use Spatie\Health\Checks\Checks\DatabaseCheck;
use Spatie\Health\Checks\Checks\DebugModeCheck;
use Spatie\Health\Checks\Checks\HorizonCheck;
use Spatie\Health\Checks\Checks\QueueCheck;
use Spatie\Health\Checks\Checks\RedisCheck;
use Spatie\Health\Checks\Checks\ScheduleCheck;
use Spatie\Health\Checks\Checks\UsedDiskSpaceCheck;
use Spatie\Health\Facades\Health;

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
        $this->app->bind(FiscalTestInvoiceGateway::class, DgiFiscalTestInvoiceGateway::class);
        $this->app->bind(SettingsCoordinator::class, DatabaseSettingsCoordinator::class);
        $this->registerIdempotentFailedJobProvider();
    }

    /**
     * failed_jobs.uuid est unique mais keyé sur l'uuid du payload : une
     * double livraison Redis du même job faisait échouer l'insert.
     */
    protected function registerIdempotentFailedJobProvider(): void
    {
        $this->app->extend('queue.failer', function ($failer, $app) {
            if (! $failer instanceof DatabaseUuidFailedJobProvider) {
                return $failer;
            }

            $config = $app['config']['queue.failed'];

            return new IdempotentFailedJobProvider($app['db'], $config['database'] ?? null, $config['table'] ?? 'failed_jobs');
        });
    }

    /**
     * Bind payment provider adapters with their configuration.
     */
    protected function registerPaymentProviders(): void
    {
        $this->app->bind(FedaPayAdapter::class, function (): FedaPayAdapter {
            $settings = app(IntegrationsSettings::class);

            return new FedaPayAdapter(
                apiKey: (string) ($settings->fedapaySecretKey ?? ''),
                environment: $settings->fedapayEnvironment,
                callbackUrl: $settings->fedapayCallbackUrl,
                webhookSecret: $settings->fedapayWebhookSecret,
                defaultPhoneCountry: $settings->fedapayDefaultPhoneCountry,
                mobileMoneyModes: [
                    'mtn_momo' => $settings->fedapayMtnMomoMode,
                    'moov_flooz' => $settings->fedapayMoovFloozMode,
                    'celtiis_cash' => $settings->fedapayCeltiisCashMode,
                    'mtn_momo_ci' => $settings->fedapayMtnMomoCiMode,
                    'moov_flooz_ci' => $settings->fedapayMoovFloozCiMode,
                    'wave_ci' => $settings->fedapayWaveCiMode,
                    'orange_money_ci' => $settings->fedapayOrangeMoneyCiMode,
                    'moov_flooz_tg' => $settings->fedapayMoovFloozTgMode,
                    'tmoney_tg' => $settings->fedapayTmoneyTgMode,
                ],
            );
        });

        $this->app->bind(Kkiapay::class, function (): Kkiapay {
            $settings = app(IntegrationsSettings::class);

            return new Kkiapay(
                (string) ($settings->kkiapayPublicKey ?? ''),
                (string) ($settings->kkiapayPrivateKey ?? ''),
                (string) ($settings->kkiapaySecretKey ?? ''),
                $settings->kkiapayEnvironment !== 'production',
            );
        });

        $this->app->bind(KKiaPayAdapter::class, function (): KKiaPayAdapter {
            $settings = app(IntegrationsSettings::class);
            $environment = $settings->kkiapayEnvironment;

            return new KKiaPayAdapter(
                sdk: $this->app->make(Kkiapay::class),
                publicKey: (string) ($settings->kkiapayPublicKey ?? ''),
                environment: $environment,
                callbackUrl: $settings->kkiapayCallbackUrl,
                webhookSecret: $settings->kkiapayWebhookSecret,
                countries: $settings->kkiapayCountries,
            );
        });

        $this->app->bind(MtnMomoAdapter::class, function (): MtnMomoAdapter {
            $settings = app(IntegrationsSettings::class);

            return new MtnMomoAdapter(
                subscriptionKey: (string) ($settings->mtnMomoSubscriptionKey ?? ''),
                userId: (string) ($settings->mtnMomoUserId ?? ''),
                apiKey: (string) ($settings->mtnMomoApiKey ?? ''),
                environment: $settings->mtnMomoEnvironment,
                targetEnvironment: $settings->mtnMomoTargetEnvironment,
                sandboxCurrency: $settings->mtnMomoSandboxCurrency,
                productionCurrency: $settings->mtnMomoProductionCurrency,
                callbackUrl: $settings->mtnMomoCallbackUrl,
                webhookSecret: $settings->mtnMomoWebhookSecret,
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Notification::extend('opsPush', fn (): OpsFirebaseChannel => app(OpsFirebaseChannel::class));
        Notification::extend('opsMail', fn (): OpsMailChannel => app(OpsMailChannel::class));
        Notification::extend('passengerDatabase', fn (): PassengerDatabaseChannel => app(PassengerDatabaseChannel::class));
        Notification::extend('passengerFcm', fn (): PassengerFcmChannel => app(PassengerFcmChannel::class));
        Notification::extend('opsDatabase', fn (): OpsDatabaseChannel => app(OpsDatabaseChannel::class));
        Notification::extend('sms', fn (): SmsChannel => app(SmsChannel::class));
        FiscalInvoice::observe(FiscalInvoiceObserver::class);
        PaymentRefund::observe(PaymentRefundNotificationObserver::class);
        $this->registerPolicies();
        $this->configureRateLimiting();
        $this->configureDefaults();
        $this->registerSocialiteProviders();
        $this->registerSecurityAlertListeners();
        $this->registerMonitoring();
    }

    /**
     * Health checks alimentant l'écran Monitoring et auth du log viewer
     * (API + interface package), tous deux gatés par monitoring.view.
     */
    protected function registerMonitoring(): void
    {
        Health::checks([
            DatabaseCheck::new(),
            CacheCheck::new(),
            RedisCheck::new(),
            UsedDiskSpaceCheck::new(),
            DebugModeCheck::new(),
            ScheduleCheck::new()->heartbeatMaxAgeInMinutes(5),
            QueueCheck::new(),
            HorizonCheck::new(),
        ]);

        LogViewer::auth(
            fn (Request $request): bool => $request->user()?->can(MonitoringPermission::View->value) ?? false,
        );
    }

    /**
     * Branche les alertes e-mail de sécurité sur les événements Fortify
     * (activation/désactivation 2FA, régénération des codes, mot de passe).
     */
    protected function registerSecurityAlertListeners(): void
    {
        Event::listen([
            TwoFactorAuthenticationConfirmed::class,
            TwoFactorAuthenticationDisabled::class,
            RecoveryCodesGenerated::class,
            PasswordUpdatedViaController::class,
        ], SendTwoFactorSecurityAlert::class);
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
        Gate::define('manage-fiscal-test-invoices', fn (User $user): bool => $user->hasRole('system_admin'));
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
            $request->ip().'|'.resolve(AuthIdentifierResolver::class)->normalizeForRateLimit(
                $request->input('identifier', $request->input('email')),
            ),
        ));

        RateLimiter::for('auth-login', fn (Request $request) => Limit::perMinute(5)->by(
            $request->ip().'|'.resolve(AuthIdentifierResolver::class)->normalizeForRateLimit(
                $request->input('identifier', $request->input('email')),
            ),
        ));

        RateLimiter::for('terminal-handshake', fn (Request $request) => Limit::perMinute(10)->by(
            $request->user()?->getAuthIdentifier().'|'.$request->ip(),
        ));

        // Un lot toutes les 10-15s est le débit GPS attendu (voir
        // docs/tracking-location-source.md) ; 30/min laisse de la marge pour
        // un rattrapage après coupure réseau sans ouvrir la porte à un flux
        // illimité.
        RateLimiter::for('tracking-batch', fn (Request $request) => Limit::perMinute(30)->by(
            $request->user()?->getAuthIdentifier().'|'.$request->ip(),
        ));

        RateLimiter::for('settings-probes', fn (Request $request) => Limit::perMinute(6)->by(
            $request->user()?->getAuthIdentifier().'|'.$request->ip(),
        ));
    }
}
