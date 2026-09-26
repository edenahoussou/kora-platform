<?php

namespace App\Modules\Settings\Middleware;

use App\Modules\Settings\Settings\GeneralSettings;
use Closure;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

final class ApplyKoraSettings
{
    public function __construct(private readonly Application $application) {}

    public function handle(Request $request, Closure $next): Response
    {
        try {
            if (! Schema::hasTable(config('settings.repositories.database.table') ?? 'settings')) {
                return $next($request);
            }

            $settings = app(GeneralSettings::class);

            // app.timezone est exposé pour les conversions d'affichage
            // (setTimezone(config('app.timezone'))), mais on ne touche pas à
            // date_default_timezone_set : le process reste en UTC pour que
            // les timestamps écrits par la console, la queue et les tests
            // soient relus sans décalage.
            config([
                'app.name' => $settings->displayName,
                'app.locale' => $settings->locale,
                'app.fallback_locale' => $settings->fallbackLocale,
                'app.timezone' => $settings->timezone,
            ]);

            App::setLocale($settings->locale);
        } catch (QueryException $exception) {
            // A first deploy can receive traffic while migrations are pending.
            // The normal Laravel configuration remains the safe fallback then.
            if ($this->application->isProduction()) {
                report($exception);
            }
        }

        return $next($request);
    }
}
