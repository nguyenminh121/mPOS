<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePosModeMiddleware
{
    public function handle(Request $request, Closure $next, string $mode): Response
    {
        $normalized = strtolower(trim($mode));

        if (! in_array($normalized, ['retail', 'preorder', 'return'], true)) {
            abort(404);
        }

        if ($normalized === 'preorder' && ! $request->has('status')) {
            $request->merge(['status' => 'PREORDER']);
        }

        $request->attributes->set('pos_mode', strtoupper($normalized));

        return $next($request);
    }
}
