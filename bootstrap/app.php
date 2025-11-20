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

            // Xətanın status kodunu yoxlayın
            $statusCode = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;

            // Kritik xətaları (500 və yuxarı) Telegram-a göndərin
            if ($statusCode >= 500) {

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

                // Debug sinfiniz array-i qəbul edir və onu JSON formatında göndərir.
                $log->debug($errorInfo);
            }
        });
    })->create();
