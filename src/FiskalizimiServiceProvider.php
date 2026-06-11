<?php

namespace Jointdots\FiskalizimiKs;

use Illuminate\Support\ServiceProvider;
use Jointdots\FiskalizimiKs\Dto\FiscalConfig;
use Jointdots\FiskalizimiKs\Engine\AtkClient;
use Jointdots\FiskalizimiKs\Engine\AtkClientInterface;
use Jointdots\FiskalizimiKs\Engine\CouponBuilder;
use Jointdots\FiskalizimiKs\Engine\QrGenerator;
use Jointdots\FiskalizimiKs\Engine\Signer;
use Jointdots\FiskalizimiKs\Engine\SignerInterface;

class FiskalizimiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/fiskalizimi.php', 'fiskalizimi');

        // Default single-installation binding. Multi-tenant hosts may override it
        // with a request-aware FiscalConfig resolver in their service provider.
        $this->app->singleton(FiscalConfig::class, function () {
            $b = config('fiskalizimi.business');
            return new FiscalConfig(
                businessId:           (int) $b['id'],
                applicationId:        (int) $b['application_id'],
                posId:                (int) $b['pos_id'],
                branchId:             (int) $b['branch_id'],
                location:             (string) $b['location'],
                privateKeyPath:       (string) $b['key_path'],
                privateKeyPassphrase: $b['key_passphrase'] ?: null,
                atkBaseUrl:           (string) config('fiskalizimi.atk.base_url'),
                atkCouponPath:        (string) config('fiskalizimi.atk.coupon_path'),
                atkTimeout:           (int) config('fiskalizimi.atk.timeout'),
            );
        });

        $this->app->singleton(AtkClientInterface::class, AtkClient::class);

        $this->app->singleton(SignerInterface::class, Signer::class);

        $this->app->singleton(FiskalizimiService::class, function ($app) {
            return new FiskalizimiService(
                new CouponBuilder(),
                $app->make(SignerInterface::class),
                new QrGenerator(),
                $app->make(AtkClientInterface::class),
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/fiskalizimi.php' => config_path('fiskalizimi.php'),
            ], 'fiskalizimi-config');

            $this->publishes([
                __DIR__ . '/../database/migrations/add_fiscal_package_columns.php.stub'
                    => database_path('migrations/' . date('Y_m_d_His') . '_add_fiscal_package_columns.php'),
            ], 'fiskalizimi-migrations');
        }

        if (config('fiskalizimi.api.enabled')) {
            $this->loadRoutesFrom(__DIR__ . '/Http/routes.php');
        }
    }
}
