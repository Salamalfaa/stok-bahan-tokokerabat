<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->is_active) {
            return redirect()->route('account.inactive');
        }

        abort_unless($user !== null && in_array($user->role, $roles, true), Response::HTTP_FORBIDDEN);

        return $next($request);
    }
}
