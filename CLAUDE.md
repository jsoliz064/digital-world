# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

Sistema de inventario y ventas de Digital World, una tienda de celulares, accesorios y repuestos (parte de una copia del sistema de la importadora YRB; los cambios están en `docs/`). Laravel 12 + Livewire 3 + Tailwind 3 + Alpine, MySQL. Todo el dominio, la interfaz y los comentarios están en español.

---

## ⚠️ Nunca ejecutes `php artisan test`

`tests/Pest.php` aplica `RefreshDatabase` a todo `tests/Feature`, y en `phpunit.xml` la línea de sqlite **está comentada**. La suite corre contra `DB_DATABASE` del `.env`, que es la base de trabajo **con los datos reales del negocio**: ejecutarla la vacía. Los tests que hay son los de Jetstream recién generados; nadie los mantiene.

La verificación en este repo se hace con **scripts de un solo uso en el scratchpad**, y es la convención de la casa:

```php
require 'D:/developer/laravel/digital-world/vendor/autoload.php';   // rutas ABSOLUTAS: el
$app = require 'D:/developer/laravel/digital-world/bootstrap/app.php'; // scratchpad está en otra unidad
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

DB::beginTransaction();
try {
    Auth::login(User::find(1));
    $c = Livewire::test(MiComponente::class)->set('campo', 1)->call('store');
    ok($condicion, 'lo que se comprueba');   // imprime OK/FALLA y acumula fallas
} finally {
    DB::rollBack();   // SIEMPRE: la base es la de producción del usuario
}
```

`Livewire::test()` es la herramienta principal: monta el componente de verdad, dispara los hooks `updatedX` y renderiza el blade, así que sirve tanto para la lógica como para comprobar el HTML (`$c->html()`).

Si una comprobación falla, sospecha primero de la expectativa del script. En la práctica casi siempre el componente tenía razón.

## Comandos

```bash
composer dev          # serve + pail + vite, todo junto
npm run dev           # solo Vite (necesario: sin public/build toda ruta da 500 por @vite)
npm run build
php artisan migrate --force
php artisan tinker --execute='...'    # consultas sueltas contra la base real
php artisan productos:auditar         # detector de deriva, SOLO LECTURA (ver abajo)
php -l archivo.php                    # lint tras editar
```

Docker (`docker-compose.yml`) es el despliegue: un solo contenedor `digital-world` (php-fpm). No hay worker de colas: el bot de WhatsApp, que era su único uso, se retiró (docs/10), y `QUEUE_CONNECTION=sync`. En local se trabaja contra MySQL directo. El `README.md` solo documenta el arranque con Docker.

---

## Arquitectura

### El dominio en una frase

Un **cliente** es una ficha (nombre obligatorio, CI único cuando está, teléfono y correo). Un **producto** es un teléfono concreto con IMEI único y un `estado` (`Inventario`, `Reparacion`, `Vendido`, `Fuera`, `Roto`, `Transito`); un **repuesto** es stock por cantidad, y la columna `repuestos.tipo` lo parte en pieza de reparación o **accesorio**. Ese par —pieza única contra stock fungible— explica casi todas las asimetrías del código.

```
Compra ──> Producto (IMEI, estado) ──> ProductoReparacion ──> ProductoReparacionRepuesto ──> baja de stock
                │                                                         │
                │                                                         └─> RepuestosDeReparacionService
                └──> Venta ──> VentaProducto                                  (cobra esos repuestos con el
                                  teléfono, SIN volver a descontar stock)

CompraRepuesto ────────┐                        ┌──> VentaRepuesto ──> VentaRepuestoDetalle
RepuestoTransferencia ─┼──> repuestos_sucursales┤
                       │    (la unica verdad)   └──> ProductoReparacionRepuesto
                       │
                       └──> repuestos.cantidad = SUM(subtabla), cacheado y $guarded
```

Todo lo que le pasa a un teléfono, a un repuesto, a un cliente, a una venta o a un usuario queda en **la bitácora** (`bitacoras`), con su autor y su antes/después. La escribe un Observer, no cada pantalla — ver la sección de abajo.

### El stock de repuestos: `StockRepuestoService` es el único camino

`repuestos_sucursales` (repuesto_id, sucursal_id, cantidad) es **la única verdad** del inventario. `repuestos.cantidad` es un **total cacheado**: la suma de esa subtabla, y está en `$guarded` porque solo lo escribe `recalcularTotales()`. Si un stock "no se guarda", ese `$guarded` es el primer sitio donde mirar — descarta en silencio, como la trampa de `$fillable`.

**Nadie escribe stock fuera de [StockRepuestoService](app/Services/StockRepuestoService.php).** Antes se movía con `increment`/`decrement` directos desde **dieciséis sitios** en ocho componentes, cada uno con su idiom; ahora son dieciséis llamadas a cuatro métodos. Reglas del servicio, todas con su motivo en el docblock:

- **No hay `mover($delta)` con signo.** `ingresar` / `retirar` / `ajustarEntrada` / `ajustarSalida`, siempre con cantidades positivas: la dirección vive en el nombre del método. El fallo recurrente del módulo era un signo copiado del módulo de al lado — compras calcula `nuevo − original` y ventas `original − nuevo`, **las dos correctas**, y por eso se copiaban mal.
- **El `WHERE cantidad >= ?` de `retirar()` ES la validación de stock**, y es atómica. No la saques a un `SELECT` previo: entre un select y un update cabe otra venta. Antes no había ninguna validación y vender 50 de algo con 3 dejaba el contador en −47.
- **El corte en `$cantidad === 0`** es lo que permite reguardar una venta vieja sin tocarle la cantidad.
- `recalcularTotales()` se llama **una vez al final** de cada guardado, dentro de la transacción del llamador. Es un `SUM`, idempotente y auto-sanante; si lo olvidas, el total queda atrás hasta la siguiente operación del artículo.
- Ningún método abre transacción: asumen la del llamador, para que una venta fallida revierta el stock **y** el documento.

**La sucursal de un movimiento se congela en la línea**, no se deduce del documento: `ventas_repuestos_detalles.sucursal_id`, `compras_repuestos.sucursal_id` y `productos_reparaciones_repuestos.sucursal_id`. Para reparaciones es obligatorio y no opcional: la pieza sale de una sucursal, al terminar la reparación el equipo se muda al Almacén, y quitar la línea después tiene que devolver el stock **a la sucursal original**.

`app/Models/RepuestoMovimiento.php` es un **modelo virtual sin tabla**: un `UNION ALL` de cuatro fuentes en **cinco ramas** (una transferencia son dos: Salida en el origen y Entrada en el destino, neta cero), de solo lectura, con los hooks `saving`/`deleting` lanzando excepción. Las ramas se emparejan **por posición** y son trece columnas: si agregas una, va en las cinco y en el mismo orden. Su docblock explica por qué `$table` y el alias del `fromSub` deben ser la misma cadena (rappasoft compone los SELECT con `getTable()`) — no lo toques sin leerlo.

Un tipo de movimiento nuevo necesita su case en `RepuestoMovimientoTipo`: el filtro de la tabla hace `array_intersect(..., ::values())` como whitelist, y lo que no esté en el enum **se filtra fuera en silencio**.

### El estado de un producto: `EstadoProductoService` es el único camino

`productos.estado` lo escribe **solo** [EstadoProductoService](app/Services/EstadoProductoService.php). Antes se cambiaba desde **diez sitios** en ocho componentes y dos de ellos no dejaban fila de historial, así que la promesa de arriba era falsa: el auditor encontró **6 de 93 productos** con su última fila en desacuerdo con su estado real.

Su método es `cambiar($productoId, $esperado, $destino, $descripcion, $enlaces, $exigirPermiso)`, y hace cuatro cosas en orden:

1. **Relee el producto con `lockForUpdate()`.**
2. **Exige que siga en `$esperado`** — el estado con el que se abrió la pantalla, no el que haya ahora. Si no, `ValidationException` con un mensaje que **nombra el estado que encontró**. Esto es lo que vuelve inocuo reintentar.
3. Comprueba el permiso `producto.estado.<destino>`, pero **solo si `$exigirPermiso`**: se pasa `true` únicamente donde el usuario *elige* el estado de un select ya filtrado por permisos (el modal individual). En el resto el cambio es consecuencia de otra operación — vender, terminar una reparación — que tiene su propio permiso, y exigirlo además dejaría sin vender a quien puede vender.
4. Hace `$producto->anotar($destino->value, $descripcion, $enlaces)` y luego el `update()` de `estado`, **desde la misma variable `$destino`**: así es estructuralmente imposible que la bitácora y el estado se contradigan, y el Observer escribe **una** fila con el evento, la frase y el diff.

**Por qué una lectura bloqueada y no un `UPDATE ... WHERE estado = ?`.** El idiom atómico de `StockRepuestoService::retirar()` — condición en el `WHERE`, mirar las filas afectadas — aquí **se rompe en silencio**: Laravel no activa `PDO::MYSQL_ATTR_FOUND_ROWS`, así que MySQL devuelve filas *cambiadas*, no *encontradas*. Un `SET estado='Fuera' WHERE estado='Fuera'` —reabrir un producto en Fuera para corregirle la descripción— devuelve **0** y fingiría un conflicto inexistente. En `retirar()` funciona porque `cantidad - n` siempre cambia el valor.

Anular la línea de venta de un teléfono va por [AnulacionVentaService](app/Services/AnulacionVentaService.php), también único camino: descobra los repuestos **antes** de tocar la venta, borra la cabecera si era el último producto (una venta sin líneas es basura que se lee como "no se guardó") y devuelve el equipo con su precondición.

`disponible_catalogo` **no se toca al vender**. Es una casilla que marca el operador a mano en el modal del lote; el catálogo ya excluye `Vendido` con un `whereNotIn` y su condición es `disponible_catalogo = 1 OR estado IN (Inventario, Oferta)`, así que ponerla en `false` no cambiaba nada de lo que se ve y en cambio borraba para siempre la elección del operador, sin guardar el valor anterior en ninguna parte.

### La bitácora: un Observer escribe, los servicios declaran la intención

`bitacoras` es **una** tabla polimórfica (`auditable_type` / `auditable_id`) para todo: el historial del teléfono, la pestaña «Cambios» del repuesto y el historial de cada usuario son tres filtros de la misma tabla. Sustituye a `productos_historiales`, que se escribía a mano desde **diecinueve** sitios, solo sabía de cambios de estado —editar el precio, el IMEI o la sucursal de un teléfono no dejaba nada— y además se **editaba** y se **borraba**. Sus 112 filas se copiaron con su fecha original; la tabla vieja queda intacta y **nadie la escribe ya**.

Un modelo entra con `use Auditable;` (hoy: `Producto`, `Repuesto`, `Venta`, `VentaRepuesto`, `CompraRepuesto`, `Cliente`, `User`). Desde ahí [BitacoraObserver](app/Observers/BitacoraObserver.php) es **el único escritor** de los cambios de modelo, y hay dos vías para dar contexto:

```php
$producto->anotar('Vendido', "Vendido. Venta #65, Daniel, $ 915.40", ['venta_id' => 65]);
$producto->update(['estado' => 'Vendido']);
// -> UNA fila: evento Vendido + la frase + {"estado": ["Inventario", "Vendido"]}

Bitacora::registrar($producto, 'garantia', 'Producto en reparacion por garantia...', [...]);
// -> un hecho suelto, para lo que NO es un save() del modelo
```

Sin `anotar()`, el Observer escribe `editado` con el diff a secas. Reglas, todas con su motivo en el código:

- **`anotar()` va ANTES del save, nunca después.** El Observer lo consume en ese save. Si el save no cambia ninguna columna —reabrir un `Fuera` para corregir la nota— Eloquent no dispara `updated`, y por eso el Observer escucha también `saved`: lo anotado se registra igual, porque **la nota es el hecho**.
- **La bitácora es inmutable**: `updating`/`deleting` lanzan excepción. Si algo se deshizo, se registra el hecho contrario (`cobro` → `cobro-anulado`); antes `cancelarCobros()` borraba las filas como si el cobro nunca hubiera existido.
- **`cambios` es siempre `[antes, después]`**: crear es `[null, valor]` y borrar `[valor, null]`. Lo pinta un solo componente, `x-bitacora-cambios`, en las tres pantallas.
- **Un par equivalente no es un cambio**: `0`/`false` o `"100.00"`/`100` se descartan. Medido: sin ese filtro, abrir el modal de un teléfono y guardar sin tocar nada dejaba una fila `disponible_catalogo: 0 → false`.
- **El SQL crudo es invisible para el Observer.** `StockRepuestoService` mueve stock con `DB::statement`/`DB::update`, así que un ajuste de stock hay que registrarlo a mano con `registrar()` (lo hace `RepuestoStockSucursalTrait`). Un `update()` del query builder tampoco dispara nada.
- **Nunca registra** `updated_at`, contraseñas, tokens 2FA, `clave_idempotencia` ni `repuestos.cantidad` (el total cacheado: inundaría el historial con el eco de cada venta). Un modelo amplía la lista con `$auditarExcluye`.
- **`auditable_id` no tiene FK, a propósito**: borrar el sujeto no se lleva su historia. Las seis FK de contexto (`venta_id`, `producto_reparacion_id`...) van en `set null`.

**El `evento` tiene dos familias que no deben mezclarse.** Un estado de `ProductoEstado` (`Vendido`, `Reparacion`...) cuando el hecho **movió** el estado del teléfono; un [BitacoraEvento](app/Enums/BitacoraEvento.php) (`creado`, `editado`, `garantia`, `cobro`...) cuando no. Escribir un estado que no es el real es justo lo que contradecía al auditor: `ProductoReparacionClienteModal` anotaba `'Reparacion'` sobre teléfonos vendidos que entraban por garantía. Y **cuidado con la colación**: `utf8mb4_unicode_ci` no distingue mayúsculas, así que un evento `'reparacion'` sería **igual** a `'Reparacion'` en un `WHERE`.

La bitácora registra **lo que una persona hizo**; `RepuestoMovimiento` sigue contestando **a dónde fue el stock**. No compiten, y por eso el historial del repuesto tiene dos pestañas.

### Sucursales y usuarios: se desactivan, no se borran

Casi todas las FK hacia `sucursales` y hacia `users` son `nullOnDelete`: borrar una sucursal o un vendedor **no falla**, deja sus ventas sin sucursal o sin vendedor en silencio. Por eso los dos modales de eliminar cuentan los movimientos antes (`Sucursal::cantidadMovimientos()`, `User::cantidadMovimientos()`) y, si hay alguno, la única salida es **desactivar** (docs/01).

- **Sucursal inactiva**: deja de ofrecerse al **cargar** algo — los selects de alta usan `Sucursal::activas()` y su regla es `exists:sucursales,id,activa,1`, porque esconder la opción no protege nada —. Los **filtros** de tablas y reportes siguen con todas: sus ventas viejas no desaparecen. Un select de **edición** usa `Sucursal::paraSelect($actualId)` (activas + la del registro) para no perder la sucursal de algo viejo.
- **El Almacén** (`Sucursal::ALMACEN`) es obligatorio: el código lo busca **por nombre** (la reparación terminada se muda ahí). Lo siembra `SucursalSeeder`, y la pantalla no deja eliminarlo, desactivarlo ni renombrarlo, también en el servidor.
- **Usuario inactivo**: no entra (`Fortify::authenticateUsing` en `FortifyServiceProvider`, que corre antes del paso 2FA) y pierde la sesión abierta (`UsuarioActivo`, en el grupo `web` de `bootstrap/app.php`; sin él, desactivar a alguien no lo sacaba hasta que caducara su sesión o su cookie de "recordarme"). `User::desactivar()` además borra sus filas de `sessions`. Desactivar tiene permiso propio, `user.desactivar`, y nadie puede desactivarse a sí mismo.
- **No hay registro público ni autoborrado de cuenta** (comentados en `config/fortify.php` y `config/jetstream.php`): los usuarios los da de alta el administrador, y borrarse desde el perfil saltaba la regla de arriba.
- **No hay usuarios por sucursal**: todos ven y operan todo.

### Idempotencia: reintentar un guardado no crea un segundo documento

El fallo: se registra una venta, el servidor hace COMMIT, y la respuesta no llega —se cortó la red, o el celular perdió señal a mitad del guardado—. El usuario no ve nada y reintenta. Antes pasaba una de dos, las dos malas: o se creaba una **segunda venta del mismo teléfono**, o el reintento moría con *"el producto ya no está disponible"*, un mensaje que habla del producto y no del guardado. En los dos casos el usuario concluye que la orden no se guardó.

Dos capas, con el reparto que ya documentaba `RepuestosDeReparacionService` para `vrd_reparacion_repuesto_unique` (*"quien impide de verdad el doble cobro es el índice"*):

| Capa | Para qué | Quién |
|---|---|---|
| **Precondición** | que no se escriba dos veces | el índice `UNIQUE` y el `SELECT ... FOR UPDATE` |
| **Comodidad** | que el reintento vea «ya se guardó: #124» | un `SELECT` por la clave, antes de abrir la transacción |

[GuardadoIdempotenteTrait](app/Traits/GuardadoIdempotenteTrait.php) lo implementa: `#[Locked] public string $claveIdempotencia` sembrada con `nuevaClaveIdempotencia()` **en `mount()` o al abrir el modal, nunca en `render()`** (ahí cambiaría entre el intento y el reintento, que es justo lo que hay que evitar), y luego `yaGuardado()` antes de la transacción y `esClaveDuplicada()` en el `catch (QueryException)`. Es correcto en los dos órdenes: si la otra petición commitea, InnoDB nos hace esperar en el índice, nos rechaza con 1062, nuestra transacción ya revirtió entera y releer encuentra la suya.

Lo usan `VentaCreate`, `CompraCreateModal`, `VentaRepuestoCreate`, `CompraRepuestoCreate` y la rama Vendido de `ProductoEstadoModal`. Dos casos van por **clave natural** en lugar de sintética, porque no crean cabecera: `VentaEdit` por el par `(venta_id, producto_id)` y el alta de teléfonos por el IMEI.

### El cliente: una ficha y un texto congelado, con una regla de lectura

`ventas.cliente_id` y `ventas_repuestos.cliente_id` enlazan con la ficha. **Y las dos columnas de texto `cliente` siguen ahí**, a propósito: son el archivo de lo que se escribió en el momento de la operación, igual que `ventas_repuestos_detalles.tipo`. Se escriben en el `create()` con el nombre que tenía la ficha y **nunca se actualizan**.

Eso deja dos fuentes para el mismo dato, así que la regla no puede quedar al criterio de cada blade:

> **La ficha es lo que se muestra y por lo que se navega; el texto es el archivo.**

Y vive en **un solo sitio**: `Venta::nombreCliente()` y `VentaRepuesto::nombreCliente()`, que hacen `$this->fichaCliente?->nombre ?? $this->cliente`. Los siete puntos que pintan un cliente llaman a ese método; ninguno lee la columna a pelo. Corregirle el nombre a una ficha se ve en **todas** sus ventas; las órdenes anteriores al módulo, que tienen texto pero no ficha, siguen mostrando lo que decían.

- **La relación se llama `fichaCliente()` y NO `cliente()`**, y no es estilo: `cliente` es una columna de esas tablas, Eloquent resuelve primero los atributos, y una relación con ese nombre quedaría **inalcanzable** — `$venta->cliente` seguiría devolviendo el string.
- **La búsqueda de las tablas mira las dos columnas** (`searchable()` con callback). Solo la de la ficha dejaría de encontrar las ventas viejas.
- **El historial del cliente va estrictamente por `cliente_id`**: es lo único que sigue a la persona.
- `RepuestosDeReparacionService` **copia `cliente_id`** además del nombre al crear la venta de repuestos enlazada; sin eso los repuestos cobrados con un teléfono quedarían sin dueño.

El cliente es **opcional** en las cinco puertas de venta: la venta de mostrador sin ficha es el caso normal y obligarlo solo produce fichas basura. Lo obligatorio es el `nombre` **dentro** de la ficha.

Elegir el cliente es [ClienteBuscadorTrait](app/Traits/ClienteBuscadorTrait.php) + [x-cliente-picker](resources/views/components/cliente-picker.blade.php), con los tres caminos juntos: teclear, mirar el catálogo, o **crear al vuelo**. El alta despacha **`clienteCreado` y no `refreshClienteTable`**: ese evento lo despachan además los modales de editar y eliminar **sin argumento**, así que un oyente que espere el id reventaría en cuanto coincidieran en pantalla. (`RepuestoCreateModal` sí usa ese piggyback; es frágil y no se copió.)

`app/Models/ClienteOrden.php` es el **segundo modelo virtual sin tabla**: un `UNION ALL` de `ventas` y `ventas_repuestos` para el historial. Mismas reglas que `RepuestoMovimiento` —diez columnas por posición en las dos ramas, alias del `fromSub` igual a `$table`, `$incrementing = false` por los `wire:key`—. Su rama de repuestos lleva **`whereNull('venta_id')`**: una venta de repuestos enlazada **no es una orden aparte** (lo decidió `ReporteIndex`), pero su **dinero sí** cuenta, y va en la columna `total_repuestos` dentro de la fila del teléfono.

### El dinero

Todo se calcula en **USD**; cada cabecera guarda su propio `tipo_cambio` y el Bs se deriva. En ventas de repuestos la regla es `total_bs = round(total * tipo_cambio, 2) + ajuste_bs`: teclear el Bs define el **ajuste**, nunca la tasa (ver `VentaRepuestoTotalBsTrait`). Antes se despejaba la tasa y quedaban órdenes con tipos de cambio inventados.

`mano_obra` suma al total **y** al costo, para que se cancele en `ganancia = total - costo_total` y las cuatro fórmulas de `ReporteIndex` sigan cuadrando sin tocarlas. Los repuestos cobrados con un teléfono se escriben con **costo 0** por la misma razón.

`ReporteIndex` nunca lee `total_bs`: trabaja sobre los detalles en USD. Antes de tocar dinero, comprueba qué consultas de ese archivo miran la columna que vas a cambiar.

---

## Convenciones de Livewire (importantes)

**No existe `#[Layout]` en este proyecto.** Una pantalla completa es siempre el mismo sándwich:

```
routes/web.php ──> Controller ──> resources/views/app/<modulo>/<accion>.blade.php
                                     <x-main-layout> @livewire('modulo.componente') </x-main-layout>
```

**Cada módulo es una tríada** `XIndex` (la página y los botones) + `XTable` (datatable de rappasoft) + `X/Modals/*`. Los modales se declaran **una vez** al final del blade del índice y se abren por evento:

```php
$this->dispatch('openProductoEstadoModal', $id);   // el modal lo recibe con #[On('openProductoEstadoModal')]
$this->dispatch('refreshProductoTable');           // tras guardar
$this->dispatch('filtersUpdated', [...]);          // Index -> Table
```

**`wire:key` debe llevar el índice** cuando el `wire:model` se enlaza por posición (`detalles.{{ $index }}.precio`). `wire:model` registra su enlace al iniciarse y **no lo desmonta** al cambiar el atributo, así que reutilizar la fila deja vivo el enlace anterior y dos inputs acaban escribiendo en el mismo sitio. Convención: `wire:key="detalle-{{ $id }}-{{ $index }}"`.

**Livewire rehidrata los modelos por id, sin relaciones.** Los `load()` y los catálogos van en `render()`, nunca en `mount()` ni en `openModal()`. Un `if ($this->openModal)` alrededor deja el componente cerrado en 0 consultas.

**Paginación manual** (`->paginate($n, ['*'], 'page', $this->pagina)`) en los modales selectores: `WithPagination` reescribiría el query string de la pantalla de fondo.

**rappasoft/laravel-livewire-tables** solo hace SELECT de los campos declarados como columna: para leer otra columna dentro de un `->format()` hace falta `setAdditionalSelects(['tabla.columna'])`. Las columnas con punto (`venta.id`, `user.name`) unen la relación solas, sin tocar el builder.

---

## Trampas conocidas

- **`$fillable` gana a `$guarded`.** Varios modelos (`VentaRepuesto`, `Venta`) declaran los dos. Una columna que falte en `$fillable` la descarta `create()` **en silencio**; ya pasó con `mano_obra`, y volvería a pasar con `clave_idempotencia`: sin esa línea la clave entra como NULL, el índice único admite todos los NULL que quieras y **toda la idempotencia queda inerte sin un solo error que lo delate**.
- **Los índices únicos son la garantía de verdad, no la regla `unique:`.** Una regla de validación valida con un `SELECT` previo: dos pestañas a la vez la pasan las dos. Los de dominio son `productos.imei`, `ventas_productos.producto_id` (un teléfono no puede estar en dos ventas a la vez; cancelar **borra** la fila, así que revender sigue funcionando), `clave_idempotencia` en las cuatro tablas de documento, `vrd_reparacion_repuesto_unique` y `repuestos_sucursales_unico`.
- **`php artisan productos:auditar`** es el detector de deriva, de solo lectura: vendidos sin venta, ventas vacías, teléfonos en dos ventas, IMEI repetidos, historial en desacuerdo con el estado y el descuadre de `repuestos.cantidad`. El del historial lee la **bitácora**, y solo las filas cuyo evento es un estado: un `editado` o una `garantia` no dicen en qué estado quedó el teléfono. Es el equivalente del banner «el balance calculado no coincide» del historial de repuestos. Hoy reporta **6 historiales desalineados**, que son filas viejas anteriores a `EstadoProductoService` y **no se reescriben a propósito**: son historia. Lo que importa es que ese número no suba.
- **`Venta::cliente` es una columna, no la relación.** La ficha es `fichaCliente()` y lo que se pinta sale de `nombreCliente()`. Una relación llamada `cliente()` queda tapada por el atributo y no hay forma de llegar a ella.
- **Dentro de una etiqueta `<x-…>` solo valen `@class` y `@style`.** Cualquier otra directiva —`@disabled`, `@checked`, `@readonly`— impide que `ComponentTagCompiler` compile el componente, y al navegador le llega un `<x-checkbox>` **literal**, que no es nada: ni input, ni `wire:model`, ni nada que clicar. **No salta ningún error**: la página se dibuja entera y el control simplemente no está. Así estuvo el selector de repuestos desde `e1d911c`, sin poder marcar ni una fila en las cuatro pantallas que lo abren. En un componente va `:disabled="$expr"` (atributo enlazado; `ComponentAttributeBag` descarta `false` y `null`); `@disabled(...)` solo sobre HTML plano —`<button>`, `<select>`, `<input>`—. Para comprobarlo: ningún archivo de `storage/framework/views` debe contener la cadena `<x-`.
- **El Observer no ve lo que no pasa por Eloquent.** `DB::table()->update()`, `DB::statement()` y el `update()` del query builder cambian datos sin dejar fila en la bitácora. Si es un hecho que importa, se registra a mano: así lo hace `RepuestoTipoCambioMasivoModal`, que cambia la tasa de todo el catálogo en un solo `UPDATE` e inserta una fila por artículo que de verdad cambió.
- **Un modal que regenera campos deja ese cambio en la bitácora.** `CompraLoteProductoEditModal` reescribe la `descripcion` desde los demás campos ante **cualquier** cambio; cambiar solo el precio deja `precio_cliente` **y** `descripcion`. No es ruido: la base cambió de verdad. Hasta la bitácora era invisible.
- **Tailwind no tiene safelist.** Una clase compuesta (`'bg-' . $color`) nunca se genera. Clases literales en cada rama del ternario, o `style` inline (`Repuesto::getDivColor()`, `Tecnicos::getDivColor()`).
- **`@can` en el blade solo esconde el botón.** La ruta necesita su `->middleware('can:...')`, y el componente su `abort_unless()`: son las dos capas que protegen de verdad. **Toda ruta de `routes/web.php` lleva hoy su `can:`** con el mismo permiso que el enlace que la abre, y las de `{id}` su `whereNumber('id')`. Hasta hace poco la mayoría no lo llevaba, y `/roles` dejaba a cualquiera con sesión editarse sus propios permisos. Las únicas abiertas son `/` y `/dashboard` (la portada tras el login, que ya saluda a quien no tiene `dashboard.index`), y el catálogo público. El enlace público del técnico se retiró (docs/10). Permisos de spatie, nombrados `modulo.accion` (`venta.create`, `producto.estado-masivo`, `repuesto.historial`).
- **MySQL corta los identificadores a 64 caracteres** y su DDL **no es transaccional**: una migración que falle a medias deja las columnas creadas y no queda registrada. Nombra explícitamente los índices y claves foráneas largos.
- **El selector de estado del producto filtra por permiso** (`ProductoEstado::toSelectArrayPermission()`, permisos `producto.estado.<estado>` en minúscula). Un estado nuevo necesita su permiso o desaparece del formulario.
- **Los modales de crear/editar suelen ser gemelos literales.** Cuando la lógica compartida crezca, el sitio es `app/Traits/` (`VentaCarritoTrait`, `RepuestoAccesorioTrait`, `VentaRepuestoTotalBsTrait`), no una copia más.
- **`inventario/repuestos` e `inventario/accesorios` son dos pantallas de la misma tabla.** El tipo llega por **parámetro de montaje** con `#[Locked]`, nunca por evento: `RepuestoIndex::mount()` despacha `filtersUpdated` cuando `RepuestoTable` todavía no existe, así que ese primer evento no lo recibe nadie y el primer render mostraría todo el catálogo. Y el filtro usa un `where` estricto, no `scopeDeTipo()`, que trata el vacío como "sin filtro". Los `@can` salen de `RepuestoTipo::permiso()` (`repuesto.*` / `accesorio.*`); `repuesto.historial`, `repuesto.tipo-cambio-masivo` y `repuesto.transferir` quedan **compartidos** a propósito.
- Los `tipo` de `ventas_repuestos_detalles` y `compras_repuestos_detalles` están **congelados a propósito**: guardan lo que el artículo era en el momento de la operación, para que reclasificarlo no reescriba un periodo cerrado.

## Estilo

Los comentarios explican **por qué**, no qué, y muy a menudo citan el fallo concreto que los motivó ("antes era un `where('imei', ...)` a secas, así que cambiar el origen colaba un producto que no pertenecía"). Escríbelos en español, sin tildes dentro del código PHP —el repo lo hace así— pero **con tildes en todo el texto que ve el usuario**.
