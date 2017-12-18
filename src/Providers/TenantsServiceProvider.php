<?php

declare(strict_types=1);

namespace Cortex\Tenants\Providers;

use Illuminate\Routing\Router;
use Rinvex\Menus\Facades\Menu;
use Cortex\Tenants\Models\Tenant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use Rinvex\Menus\Factories\MenuFactory;
use Rinvex\Tenants\Contracts\TenantContract;
use Cortex\Tenants\Http\Middleware\Tenantable;
use Cortex\Tenants\Console\Commands\SeedCommand;
use Cortex\Tenants\Console\Commands\InstallCommand;
use Cortex\Tenants\Console\Commands\MigrateCommand;
use Cortex\Tenants\Console\Commands\PublishCommand;
use Cortex\Tenants\Console\Commands\RollbackCommand;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Cortex\Tenants\Overrides\Illuminate\Auth\EloquentUserProvider;

class TenantsServiceProvider extends ServiceProvider
{
    /**
     * The commands to be registered.
     *
     * @var array
     */
    protected $commands = [
        SeedCommand::class => 'command.cortex.tenants.seed',
        InstallCommand::class => 'command.cortex.tenants.install',
        MigrateCommand::class => 'command.cortex.tenants.migrate',
        PublishCommand::class => 'command.cortex.tenants.publish',
        RollbackCommand::class => 'command.cortex.tenants.rollback',
    ];

    /**
     * Register any application services.
     *
     * This service provider is a great spot to register your various container
     * bindings with the application. As you can see, we are registering our
     * "Registrar" implementation here. You can add your own bindings too!
     *
     * @return void
     */
    public function register()
    {
        // Merge config
        $this->mergeConfigFrom(realpath(__DIR__.'/../../config/config.php'), 'cortex.tenants');

        // Register console commands
        ! $this->app->runningInConsole() || $this->registerCommands();
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(Router $router)
    {
        // Bind route models and constrains
        $router->pattern('tenant', '[a-z0-9-]+');
        $router->model('tenant', TenantContract::class);

        // Map relations
        Relation::morphMap([
            'tenant' => config('rinvex.tenants.models.tenant'),
        ]);

        // Load resources
        require __DIR__.'/../../routes/breadcrumbs.php';
        $this->loadRoutesFrom(__DIR__.'/../../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'cortex/tenants');
        $this->loadTranslationsFrom(__DIR__.'/../../resources/lang', 'cortex/tenants');
        ! $this->app->runningInConsole() || $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->app->afterResolving('blade.compiler', function () {
            require __DIR__.'/../../routes/menus.php';
        });

        // Publish Resources
        ! $this->app->runningInConsole() || $this->publishResources();

        // Inject tenantable middleware before route bindings substitution
        $pointer = array_search(SubstituteBindings::class, $router->middlewarePriority);
        $before = array_slice($router->middlewarePriority, 0, $pointer);
        $after = array_slice($router->middlewarePriority, $pointer);

        $router->middlewarePriority = array_merge($before, [Tenantable::class], $after);
        $router->pushMiddlewareToGroup('web', Tenantable::class);

        // Override EloquentUserProvider to remove tenantable
        // global scope when retrieving authenticated user instance
        Auth::provider('eloquent', function ($app, array $config) {
            return new EloquentUserProvider($app['hash'], $config['model']);
        });

        // Override fort controllers
        $this->app->singleton(\Cortex\Fort\Http\Controllers\Frontarea\RegistrationController::class, \Cortex\Tenants\Http\Controllers\Frontarea\RegistrationController::class);

        // Register attributes entities
        app('rinvex.attributes.entities')->push(Tenant::class);

        // Register menus
        $this->registerMenus();
    }

    /**
     * Register menus.
     *
     * @return void
     */
    protected function registerMenus()
    {
        Menu::make('tenantarea.topbar', function (MenuFactory $menu) {
        });
        Menu::make('managerarea.topbar', function (MenuFactory $menu) {
        });
        Menu::make('managerarea.sidebar', function (MenuFactory $menu) {
        });
    }

    /**
     * Publish resources.
     *
     * @return void
     */
    protected function publishResources()
    {
        $this->publishes([realpath(__DIR__.'/../../config/config.php') => config_path('cortex.tenants.php')], 'cortex-tenants-config');
        $this->publishes([realpath(__DIR__.'/../../resources/lang') => resource_path('lang/vendor/cortex/tenants')], 'cortex-tenants-lang');
        $this->publishes([realpath(__DIR__.'/../../resources/views') => resource_path('views/vendor/cortex/tenants')], 'cortex-tenants-views');
    }

    /**
     * Register console commands.
     *
     * @return void
     */
    protected function registerCommands()
    {
        // Register artisan commands
        foreach ($this->commands as $key => $value) {
            $this->app->singleton($value, function ($app) use ($key) {
                return new $key();
            });
        }

        $this->commands(array_values($this->commands));
    }
}
