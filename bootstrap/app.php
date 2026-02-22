<?php

use App\Http\Middleware\ExternalApi;
use App\Services\DebugWithTelegramService;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api/v1'
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'external.api' => ExternalApi::class,
            'locale' => \App\Http\Middleware\Locale::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            '/v1/google/subscriptions/webhook' // <-- exclude this route
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->report(function (Throwable $e) {

            $statusCode = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;

            if ($statusCode >= 500) {
                try {
                    $log = new DebugWithTelegramService();

                    $errorInfo = [
                        'type' => get_class($e),
                        'message' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'status' => $statusCode,
                        'url' => request()->fullUrl(),
                        'method' => request()->method(),
                    ];

                    $log->debug($errorInfo);
                } catch (\Throwable $telegramError) {
                    error_log('Exception handler Telegram error: ' . $telegramError->getMessage());
                }
            }

            return false; // allow default Laravel logging to continue
        });
    })->create();
