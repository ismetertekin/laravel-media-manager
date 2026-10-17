<?php

namespace Yazilim360\MediaManager\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Optional Gate check (`media-manager.authorize`) on top of the route middleware.
 */
class AuthorizeMediaManager
{
    public function handle(Request $request, Closure $next): Response
    {
        $ability = config('media-manager.authorize');

        if (is_string($ability) && $ability !== '') {
            abort_unless(Gate::allows($ability), 403);
        }

        return $next($request);
    }
}
