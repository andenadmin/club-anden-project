<?php

namespace App\Providers;

use App\Services\Meta\WhatsAppClient;
use App\Services\Meta\WhatsAppClientFactory;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(WhatsAppClient::class, function () {
            $cfg = config('services.whatsapp');
            return new WhatsAppClient(
                phoneNumberId: $cfg['phone_number_id'] ?? '',
                accessToken:   $cfg['access_token']    ?? '',
                apiVersion:    $cfg['api_version']     ?? 'v21.0',
                appSecret:     $cfg['app_secret']      ?? '',
            );
        });

        $this->app->singleton(WhatsAppClientFactory::class, fn () => new WhatsAppClientFactory());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
