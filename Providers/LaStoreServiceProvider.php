<?php

namespace Modules\LaStore\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Modules\LaStore\Console\AdoptCommand;
use Modules\LaStore\Console\ResetInstallationCommand;
use Modules\LaStore\Console\SyncCommand;

class LaStoreServiceProvider extends ServiceProvider
{
    const MODULE_NAME = 'LaStore';
    const MODULE_ALIAS = 'lastore';

    public function boot()
    {
        $this->loadViews();
        $this->loadRoutes();
        $this->loadMigrations();
        $this->loadTranslations();
        $this->registerCommands();
        $this->registerHooks();
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../Config/config.php', self::MODULE_ALIAS);
    }

    protected function loadViews()
    {
        $this->loadViewsFrom(__DIR__.'/../Resources/views', self::MODULE_ALIAS);
    }

    protected function loadRoutes()
    {
        // Ein einzelnes require ohne umschliessende Route::group(), weil eine
        // reine Namespace-Gruppe RouteGroup::formatPrefix() unter PHP 8.1+
        // zerlegt - dieselbe Stelle wie in Bexio, Products und Hr.
        require __DIR__.'/../Http/routes.php';
    }

    protected function loadMigrations()
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
    }

    protected function loadTranslations()
    {
        $this->app['translator']->addJsonPath(__DIR__.'/../Resources/lang');
    }

    protected function registerCommands()
    {
        $this->commands([
            SyncCommand::class,
            ResetInstallationCommand::class,
            AdoptCommand::class,
        ]);
    }

    protected function registerHooks()
    {
        \Eventy::addFilter('schedule', function ($schedule) {
            $schedule->command(SyncCommand::class)->dailyAt('05:30')->withoutOverlapping();

            return $schedule;
        });

        \Eventy::addAction('menu.append', function () {
            $active = Str::startsWith(\Route::currentRouteName() ?? '', 'lastore.') ? 'active' : '';
            echo '<li class="'.$active.'" data-menu-key="lastore_store"><a href="'.route('lastore.index').'">'
                .'<i class="glyphicon glyphicon-shopping-cart"></i> '.__('Store').'</a></li>';
        }, 24);
    }
}
