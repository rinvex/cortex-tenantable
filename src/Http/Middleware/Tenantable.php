<?php

declare(strict_types=1);

namespace Cortex\Tenants\Http\Middleware;

use Closure;

class Tenantable
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure                 $next
     *
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $subdomain = app('request.subdomain');
        $tenant = app('request.tenant');

        if ($subdomain && ! $tenant) {
            return $subdomain === 'www'
                ? intend(['url' => route('frontarea.home')])
                : intend([
                    'url' => route('frontarea.home'),
                    'with' => ['warning' => trans('cortex/foundation::messages.resource_not_found', ['resource' => trans('cortex/tenants::common.tenant'), 'identifier' => $subdomain])],
                ]);
        }

        // Scope bouncer
        (! $tenant || ! app()->bound(\Silber\Bouncer\Bouncer::class)) || app(\Silber\Bouncer\Bouncer::class)->scope()->to($tenant->getKey());

        return $next($request);
    }
}
