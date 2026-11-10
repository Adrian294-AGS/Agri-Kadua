<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Role-based access (FR-01 / NFR-05). Usage: ->middleware('role:accounting_clerk,cooperative_manager')
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasRole(...$roles)) {
            abort(403, 'Your role does not have access to this function.');
        }

        return $next($request);
    }
}