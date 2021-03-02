<?php

declare(strict_types=1);

namespace Cortex\Tenants\Providers;

use Illuminate\Routing\Router;
use Cortex\Tenants\Models\Tenant;
use Illuminate\Support\ServiceProvider;
use Rinvex\Support\Traits\ConsoleTools;
use Illuminate\Contracts\Events\Dispatcher;
use Cortex\Tenants\Http\Middleware\Tenantable;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Routing\Middleware\SubstituteBindings;

class TenantsServiceProvider extends ServiceProvider
{
    use ConsoleTools;

    /**
     * Register any application services.
     *
     * This service provider is a great spot to register your various container
     * bindings with the application. As you can see, we are registering our
     * "Registrar" implementation here. You can add your own bindings too!
     *
     * @return void
     */
    public function register(): void
    {
        // Bind eloquent models to IoC container
        $this->app['config']['rinvex.tenants.models.tenant'] === Tenant::class
        || $this->app->alias('rinvex.tenants.tenant', Tenant::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(Router $router, Dispatcher $dispatcher): void
    {
        // Bind route models and constrains
        $router->pattern('tenant', '[a-zA-Z0-9-_]+');
        $router->model('tenant', config('rinvex.tenants.models.tenant'));

        // Map relations
        Relation::morphMap([
            'tenant' => config('rinvex.tenants.models.tenant'),
        ]);

        // Inject tenantable middleware
        // before route bindings substitution
        $this->app->booted(function () {
            $router = $this->app['router'];

            $pointer = array_search(SubstituteBindings::class, $router->middlewarePriority);
            $before = array_slice($router->middlewarePriority, 0, $pointer);
            $after = array_slice($router->middlewarePriority, $pointer);

            $router->middlewarePriority = array_merge($before, [Tenantable::class], $after);
            $router->pushMiddlewareToGroup('web', Tenantable::class);
        });
    }
}
