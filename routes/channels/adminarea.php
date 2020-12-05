<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Access\Authorizable;

Broadcast::channel('adminarea-tenants-index', function (Authorizable $user) {
    return $user->can('list', app('rinvex.tenants.tenant'));
}, ['guards' => ['admin']]);
