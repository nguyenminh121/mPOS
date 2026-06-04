<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function (Response $response, \Throwable $exception, Request $request): Response {
            $statusCode = $response->getStatusCode();

            $supportedStatuses = [401, 403, 404, 419, 429, 500, 503];
            $isSupportedStatus = in_array($statusCode, $supportedStatuses, true);
            $isInertiaRequest = (bool) $request->header('X-Inertia');
            $wantsHtml = $request->acceptsHtml() && ! $request->expectsJson();

            if ($isSupportedStatus && ($isInertiaRequest || $wantsHtml)) {
                return Inertia::render('errors/HttpError', [
                    'status' => $statusCode,
                ])->toResponse($request)->setStatusCode($statusCode);
            }

            return $response;
        });
    })->create();
