<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Saca al usuario desactivado aunque tenga la sesion abierta o la
        // cookie de "recordarme": el login ya lo rechaza, pero eso no basta.
        $middleware->web(append: [
            \App\Http\Middleware\UsuarioActivo::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Token CSRF vencido en un formulario comun (el login, el logout): en
        // vez de la pagina "419 Page Expired", que solo se arreglaba con F5,
        // se vuelve a una pagina nueva con un aviso. Laravel ya convirtio la
        // TokenMismatchException en un HttpException 419 al llegar aqui.
        // Livewire y lo que espera JSON siguen con su manejo (app.js recarga).
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 419 || $request->hasHeader('X-Livewire') || $request->expectsJson()) {
                return null;
            }

            if ($request->routeIs('logout')) {
                return redirect()->route('login');
            }

            return redirect()->back(302, [], route('login'))
                ->withInput($request->except('password', 'password_confirmation', 'current_password', '_token'))
                ->with('status', 'La página estuvo abierta mucho tiempo y venció. Vuelve a intentarlo.');
        });
    })->create();
