# 1. Administración

## Para qué sirve

Definir quién trabaja en el sistema, qué puede hacer cada uno, dónde trabaja y a quién se le compra.

---

## Usuarios

### Cómo funciona hoy

Cada persona que entra al sistema tiene su usuario con nombre, correo y contraseña, y uno o varios roles que definen qué pantallas ve. Desde la pantalla de Usuarios se crean, se editan y se eliminan.

### Qué cambia

Se le agrega a cada usuario su **porcentaje de comisión por ventas**. Es un dato de su ficha: se carga al crearlo y se puede cambiar cuando se quiera.

A partir de ese porcentaje, cada venta que esa persona registre genera una comisión, que se calcula **sobre la ganancia de la venta** y se le acredita cuando esa venta queda **cobrada por completo**. El detalle está en [Comisiones](05-comisiones.md).

Desde la ficha del usuario se podrá ver, además, todo lo que ha vendido y cuánta comisión tiene ganada, pendiente y pagada.

---

## Roles

### Cómo funciona hoy

El sistema tiene permisos muy finos: no es "vendedor ve ventas", es que cada botón tiene su permiso propio. Hay alrededor de setenta: crear producto, editar producto, eliminar producto, cambiar de sucursal, ver el historial, cambiar estados masivamente, ver reportes, y así con cada acción de cada pantalla.

Un rol es una combinación de esos permisos, y desde la pantalla de Roles se arma y se modifica sin tocar el sistema.

Incluso los estados del equipo respetan permisos: se puede armar un rol que pueda pasar equipos a reparación pero no marcarlos como vendidos.

### Qué cambia

Se mantiene tal cual. Se le agregan los permisos de lo nuevo: clientes, cobros, métodos de pago, comisiones, reclamos a proveedores y los reportes nuevos.

---

## Proveedores

### Cómo funciona hoy

Un listado simple de nombres. Cada compra de equipos se registra contra un proveedor.

### Qué cambia

Se mantiene. Con las compras a crédito y los reclamos, la ficha del proveedor pasa a mostrar **cuánto se le debe** y **qué reclamos hay abiertos con él**. Ver [Compras](06-compras.md).

---

## Técnicos

### Cómo funciona hoy

Un listado de técnicos, cada uno con su nombre y un color que lo identifica de un vistazo en las tablas. A cada reparación se le asigna un técnico.

Hoy cada técnico tiene además un enlace público con un código: entrando por esa dirección ve los equipos que tiene asignados sin necesidad de usuario ni contraseña.

### Qué cambia

**Se agrega su comisión.** Por cada reparación, el técnico gana el **50% de la mano de obra cobrada**. Los repuestos que se usan corren por cuenta del negocio y no entran en el reparto:

```
Reparación de un iPhone 12
  Repuestos usados     120 Bs   (los pone el negocio)
  Mano de obra         200 Bs
                       -------
  Para el técnico      100 Bs
  Para el negocio      100 Bs
```

El porcentaje queda como un dato de la ficha del técnico, con 50% cargado por defecto, por si alguna vez hace falta un arreglo distinto con alguien.

Desde la ficha del técnico se ve qué reparaciones hizo, cuánto lleva ganado, qué está pendiente de pago y qué ya se le pagó.

**Se retira el enlace público del técnico.** Quien necesite ver los equipos, entra al sistema con su usuario.

---

## Sucursales

### Cómo funciona hoy

Los locales del negocio. Cada equipo está en una sucursal, cada venta y cada compra se hacen desde una sucursal, y hay una pantalla para transferir equipos de un local a otro eligiendo origen y destino.

**Pero no hay pantalla para administrar las sucursales.** Hoy no se pueden crear, renombrar ni dar de baja desde el sistema: están cargadas directamente en la base de datos, y para agregar un local hay que pedírselo a quien tenga acceso técnico. De cada sucursal solo se guarda el nombre.

### Qué cambia

**Se construye la pantalla de sucursales**, igual que la de proveedores o la de marcas: crear, editar y dar de baja desde el sistema, sin depender de nadie.

Y la ficha crece, porque el nombre solo no alcanza:

| Dato | Para qué |
|---|---|
| Nombre | Identificarla |
| Dirección | Sale impresa en la nota de venta |
| Teléfono | Sale impresa en la nota de venta |
| Activa / inactiva | Cerrar un local sin perder su historial de ventas |

Una sucursal con movimientos **no se elimina**: se marca como inactiva. Deja de aparecer al cargar productos o ventas, pero sus ventas viejas siguen en los reportes.

---

## Cómo queda el trabajo diario

```
Alta de un vendedor nuevo

  Usuario:     Carlos Ruiz
  Correo:      carlos@negocio.com
  Rol:         Vendedor
  Comisión:    3%
  Sucursal:    Local Centro

  Con eso, cada venta que Carlos registre:
    Vende un equipo en    3.000 Bs
    El equipo costó       2.400 Bs
    Ganancia                600 Bs
    Comisión de Carlos       18 Bs   -> queda pendiente
                                        hasta que la venta se cobre entera
```

---

## Qué NO incluye

- **No hay traspaso de inventario entre sucursales con aprobación.** La transferencia de equipos entre locales es directa, como hoy: se eligen los equipos y se mueven.
- **No hay control de horarios ni asistencia.** El sistema no registra entradas, salidas ni turnos.
- **No hay sueldos ni planilla.** Solo comisiones; el sueldo fijo se maneja fuera.
- **No hay aprobación por niveles.** Si un rol tiene el permiso, la acción se hace; no hay "lo cargo yo y lo autoriza mi jefe".
- **Un usuario no se elimina si ya vendió.** Se desactiva, para no perder la historia de sus ventas.

---

## Entregables

1. **Pantalla de sucursales**: crear, editar y desactivar, con nombre, dirección y teléfono. Hoy no existe.
2. Campo de porcentaje de comisión en la ficha del usuario, y su vista de comisiones ganadas, pendientes y pagadas.
3. Campo de porcentaje de comisión en la ficha del técnico (50% por defecto), y su vista de reparaciones y comisiones.
4. Permisos nuevos para sucursales, clientes, cobros, métodos de pago, comisiones, reclamos y reportes nuevos.
5. Retiro del enlace público del técnico.
6. Datos de deuda y reclamos en la ficha del proveedor.
