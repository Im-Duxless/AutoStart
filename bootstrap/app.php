<?php

use App\Exceptions\BusinessException;
use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['role' => EnsureUserHasRole::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontReport(BusinessException::class);
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        $exceptions->render(fn (BusinessException $error) => response()->json(['message' => $error->getMessage()], 409));
        $exceptions->render(function (UniqueConstraintViolationException $error, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Запись с такими данными уже существует.'], 409);
            }
        });
        $exceptions->render(function (AuthenticationException $error, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Войдите в личный кабинет.'], 401);
            }
        });
        $exceptions->render(function (HttpExceptionInterface $error, Request $request) {
            $message = match ($error->getStatusCode()) {
                403 => 'У вас нет доступа к этой операции.',
                404 => 'Запись не найдена.',
                429 => 'Слишком много запросов. Повторите попытку позже.',
                default => null,
            };
            if ($request->is('api/*') && $message !== null) {
                return response()->json(['message' => $message], $error->getStatusCode(), $error->getHeaders());
            }
        });
    })->create();
