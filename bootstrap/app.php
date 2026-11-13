
<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Percayai reverse proxy dari EthioDeploy
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
        ]);

        // Pelanggan yang belum masuk diarahkan ke login pelanggan, staf ke login staf
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('akun', 'akun/*')
            ? route('portal.login')
            : route('login'));

        // Yang sudah masuk tidak perlu melihat halaman login lagi
        $middleware->redirectUsersTo(fn (Request $request) => $request->is('akun', 'akun/*')
            ? route('portal.home')
            : route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
