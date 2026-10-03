<?php

use App\Http\Controllers\CatalogoController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\CompraController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProductoCategoriaController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\ProductoMarcaController;
use App\Http\Controllers\ProductoModeloController;
use App\Http\Controllers\ProveedorController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\RepuestoController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SucursalController;
use App\Http\Controllers\TecnicosController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VentaController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    // Sin can: a proposito: es la pagina de inicio tras el login
    // (fortify.home), y la vista ya cambia el panel por un saludo a quien no
    // tiene dashboard.index. Cerrarla dejaria a un vendedor en un 403 al entrar.
    Route::get('/', [DashboardController::class, 'index'])->name('welcome');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Antes sin can:, como roles: cualquier usuario autenticado entraba
    // escribiendo la URL. En roles era peor, porque desde ahi se edita que
    // permisos tiene cada rol, incluido el propio.
    Route::get('users', [UserController::class, 'index'])
        ->middleware('can:user.index')
        ->name('users');

    // Con can: y whereNumber como los historiales hermanos (clientes, repuestos):
    // el @can del boton solo lo esconde, y {id} capturaria cualquier ruta
    // literal que se agregue manana bajo el prefijo.
    Route::get('users/{id}/historial', [UserController::class, 'historial'])
        ->whereNumber('id')
        ->middleware('can:user.historial')
        ->name('users.historial');

    Route::get('roles', [RoleController::class, 'index'])
        ->middleware('can:rol.index')
        ->name('roles');

    // Desde aqui y hasta el final del grupo, cada ruta lleva el mismo permiso
    // que el @can del enlace o boton que la abre. Antes ninguna lo tenia: el
    // @can solo escondia el enlace y cualquier usuario con sesion entraba
    // escribiendo la URL.
    Route::get('proveedores', [ProveedorController::class, 'index'])
        ->middleware('can:proveedor.index')
        ->name('proveedores');

    Route::get('sucursales', [SucursalController::class, 'index'])
        ->middleware('can:sucursal.index')
        ->name('sucursales');

    Route::group(['prefix' => 'clientes'], function () {
        // Con can: desde el principio: el @can del blade solo esconde el enlace
        // del menu, no protege la URL.
        Route::get('/', [ClienteController::class, 'index'])
            ->middleware('can:cliente.index')
            ->name('clientes');

        // whereNumber por el mismo motivo que en el historial de repuestos: si
        // manana se agrega una ruta literal bajo este prefijo, {id} la capturaria.
        Route::get('{id}/historial', [ClienteController::class, 'historial'])
            ->whereNumber('id')
            ->middleware('can:cliente.historial')
            ->name('clientes.historial');
    });

    Route::group(['prefix' => 'tecnicos'], function () {
        Route::get('/', [TecnicosController::class, 'index'])
            ->middleware('can:tecnico.index')
            ->name('tecnicos');
        Route::get('{id}/productos', [TecnicosController::class, 'tecnicosProductos'])
            ->whereNumber('id')
            ->middleware('can:tecnico.productos')
            ->name('tecnicos.productos');
    });

    // Antes el grupo entero iba sin un solo can:, a diferencia de sus hermanos
    // de clientes y repuestos: el @can del menu solo escondia el enlace.
    Route::group(['prefix' => 'productos'], function () {
        Route::get('/', [ProductoController::class, 'index'])
            ->middleware('can:producto.index')
            ->name('productos');
        Route::get('marcas', [ProductoMarcaController::class, 'index'])
            ->middleware('can:producto-marca.index')
            ->name('productos.marcas');
        Route::get('categorias', [ProductoCategoriaController::class, 'index'])
            ->middleware('can:producto-categoria.index')
            ->name('productos.categorias');
        Route::get('modelos', [ProductoModeloController::class, 'index'])
            ->middleware('can:producto-modelo.index')
            ->name('productos.modelos');
        // whereNumber: {id} capturaria cualquier ruta literal que se agregue
        // despues bajo el prefijo.
        Route::get('{id}/historial', [ProductoController::class, 'productoHistorial'])
            ->whereNumber('id')
            ->middleware('can:producto.historial')
            ->name('productos.historial');
    });

    // Repuestos y accesorios son la misma tabla (`repuestos`, columna `tipo`) y
    // dos pantallas distintas. Los NOMBRES de ruta se conservan tal cual
    // ('repuestos', 'repuestos.categorias', 'repuestos.historial'): las URLs
    // cambian de /repuestos a /inventario/repuestos y nada del repo enlaza por
    // URL, solo por nombre.
    Route::group(['prefix' => 'inventario'], function () {
        Route::group(['prefix' => 'repuestos'], function () {
            // Antes esta ruta NO tenia can: ninguno, asi que cualquier usuario
            // autenticado entraba escribiendola. Sin cerrarlo, el permiso
            // separado de accesorios seria decorativo.
            Route::get('/', [RepuestoController::class, 'index'])
                ->middleware('can:repuesto.index')
                ->name('repuestos');
            Route::get('categorias', [RepuestoController::class, 'categorias'])
                ->middleware('can:repuesto-categoria.index')
                ->name('repuestos.categorias');
            // DEBE ir después de 'categorias': {id} capturaría el literal "categorias".
            // whereNumber() lo blinda igualmente. El middleware can: es necesario porque
            // el @can del blade solo oculta el botón, no protege la URL.
            //
            // El historial es UNO para los dos tipos, por eso se queda bajo
            // 'repuestos' y con el permiso compartido repuesto.historial.
            Route::get('{id}/historial', [RepuestoController::class, 'historial'])
                ->whereNumber('id')
                ->middleware('can:repuesto.historial')
                ->name('repuestos.historial');
        });

        Route::get('accesorios', [RepuestoController::class, 'accesorios'])
            ->middleware('can:accesorio.index')
            ->name('accesorios');
    });

    Route::group(['prefix' => 'compras'], function () {
        Route::get('/', [CompraController::class, 'index'])
            ->middleware('can:compra.index')
            ->name('compras');
        Route::get('{id}/productos', [CompraController::class, 'show'])
            ->whereNumber('id')
            ->middleware('can:compra.productos')
            ->name('compras.productos');

        Route::group(['prefix' => 'repuestos'], function () {
            Route::get('/', [CompraController::class, 'repuestoIndex'])
                ->middleware('can:compra.repuesto.index')
                ->name('compras.repuestos');
            Route::get('/crear', [CompraController::class, 'repuestoCreate'])
                ->middleware('can:compra.repuesto.create')
                ->name('compras.repuestos.crear');
            Route::get('{id}/editar', [CompraController::class, 'repuestoEditar'])
                ->whereNumber('id')
                ->middleware('can:compra.repuesto.edit')
                ->name('compras.repuestos.editar');
        });
    });

    Route::group(['prefix' => 'ventas'], function () {
        Route::get('/', [VentaController::class, 'index'])
            ->middleware('can:venta.index')
            ->name('ventas');

        // El @can del blade solo oculta el boton, no protege la URL: sin este
        // middleware cualquier usuario autenticado entraba escribiendola.
        Route::get('/crear', [VentaController::class, 'create'])
            ->middleware('can:venta.create')
            ->name('ventas.crear');
        Route::get('{id}/editar', [VentaController::class, 'editar'])
            ->whereNumber('id')
            ->middleware('can:venta.create')
            ->name('ventas.editar');

        Route::get('{id}/detalles', [VentaController::class, 'detalles'])
            ->whereNumber('id')
            ->middleware('can:venta.detalle')
            ->name('ventas.detalles');

        Route::group(['prefix' => 'repuestos'], function () {
            Route::get('/', [VentaController::class, 'repuestoIndex'])
                ->middleware('can:venta.repuesto.index')
                ->name('ventas.repuestos');
            Route::get('/crear', [VentaController::class, 'repuestoCreate'])
                ->middleware('can:venta.repuesto.create')
                ->name('ventas.repuestos.crear');
            Route::get('{id}/editar', [VentaController::class, 'repuestoEditar'])
                ->whereNumber('id')
                ->middleware('can:venta.repuesto.edit')
                ->name('ventas.repuestos.editar');
        });
    });

    // El @can del blade solo oculta el enlace del menu; sin este middleware
    // cualquier usuario autenticado entraba escribiendo /reporte.
    Route::get('reporte', [ReporteController::class, 'index'])
        ->middleware('can:reporte.index')
        ->name('reporte');
});

Route::get('catalogo', [CatalogoController::class, 'index'])->name('catalogo');
