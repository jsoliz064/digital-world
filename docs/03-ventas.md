# 3. Ventas

## Para qué sirve

Registrar lo que se vende, cobrarlo, entregar un comprobante y dejar todo anotado: qué equipo salió, a quién, por cuánto, quién lo vendió y cómo se pagó.

---

## Cómo funciona hoy

Hay dos pantallas de venta:

- **Venta de productos**: se buscan los equipos por IMEI o desde el catálogo, se arma la lista, se ajusta precio y descuento por cada uno, y se registra. El equipo pasa a Vendido y se le puede dar garantía por una cantidad de meses. Se puede vender más de un equipo en la misma venta.
- **Venta de repuestos y accesorios**: igual, pero por cantidad, con mano de obra opcional para cuando la venta incluye un trabajo.

Ambas guardan el cliente como un **texto escrito a mano**, la sucursal, el vendedor, el subtotal, el descuento y el total.

El total se calcula en dólares y se convierte a bolivianos con el tipo de cambio de esa venta.

Cuando un teléfono se vende, el sistema permite cobrar aparte los repuestos que se le montaron en el taller, sin descontar stock de nuevo (ya se descontó al repararlo).

No hay comprobante impreso, no hay forma de registrar cómo se pagó, y no hay ventas a crédito.

---

## Qué cambia

### Una sola venta para todo

Las dos pantallas se unifican: **una venta es una sola orden**, con un solo número de nota, y cada línea es lo que se vendió:

| Línea | Cantidad |
|---|---|
| **Equipo** | Siempre 1 (es una ficha con IMEI) |
| **Repuesto** | La que se venda, descuenta stock de la sucursal |
| **Accesorio** | La que se venda, descuenta stock de la sucursal |

Un solo buscador encuentra los tres por **IMEI, UPC, SKU o nombre**; con la pistola lectora, un código exacto agrega la línea solo.

Los repuestos que se le montaron a un teléfono en el taller se cobran como **una línea más de la misma venta**, sin descontar stock de nuevo (ya se descontó al repararlo). La mano de obra, cuando la hay, va en la cabecera de la venta.

El cliente se elige de su ficha (o se crea en el momento); es opcional, porque la venta de mostrador sin ficha es lo normal.

La venta se hace **solo desde la pantalla de ventas**. Desde el cambio de estado de un equipo hay un botón «Vender» que abre la venta con ese equipo ya cargado.

### La moneda

La venta se registra en **bolivianos**. Si el cliente paga en dólares, se marca la venta como **transacción en USD**: se anota el tipo de cambio de ese momento y el método de pago. El sistema guarda las dos cifras para que la caja cuadre.

Así quedó: **cada fila del cobro puede ser en Bs o en USD**. En USD se escriben los dólares y el tipo de cambio, y el sistema muestra el equivalente en Bs, que es lo que descuenta del total. Se puede mezclar (parte en dólares, parte en bolivianos), y vale también para los cobros de una venta a crédito. El tipo de cambio que se propone es el último que se usó.

### Accesorios que se venden con el equipo

Al armar la venta, por cada teléfono se pueden agregar **accesorios que se venden junto con él**: una funda, un cargador, un vidrio. Se eligen del stock, descuentan cantidad y se suman al total de la venta.

Es distinto de los **accesorios de regalo** de la ficha del producto: aquellos no se cobran (van al costo del equipo), estos sí se cobran.

Así quedó: cada accesorio o repuesto de la venta tiene «Con el equipo», para elegir con qué teléfono se vende (si hay uno solo, ya viene puesto). En el detalle y en la nota aparece debajo de ese equipo.

```
Venta
  iPhone 13                        3.200 Bs
    + Cargador 20W                   180 Bs   -> baja stock
    + Audífonos                      120 Bs   -> baja stock
                                   ---------
  Total                            3.500 Bs
```

### El cobro

Se crea un **módulo de métodos de pago** para que el negocio administre los suyos: efectivo, QR, transferencia, tarjeta, o los que sumen después.

Y una venta puede cobrarse con **varios métodos a la vez**:

```
Total de la venta      3.500 Bs
  Efectivo             2.000 Bs
  QR                   1.500 Bs
                       ---------
  Cobrado              3.500 Bs   -> venta pagada
```

Si lo cobrado es menor que el total, la venta queda **a crédito**, con su saldo pendiente. Ver [Clientes y cobranzas](04-clientes-y-cobranzas.md).

En la pantalla de venta, el cobro empieza con una sola fila de Efectivo por el total, que lo sigue mientras no se toque: la venta de contado en efectivo no pide escribir nada. Con «Agregar método» se suman otros, y el sistema muestra lo cobrado y lo que queda a crédito.

### Permuta

Cuando el cliente entrega un equipo como parte de pago:

1. Se registra el equipo recibido: IMEI, modelo, grado, estado y el **valor que se le reconoce**.
2. Ese equipo **entra al inventario** como un producto más, con ese valor como costo, listo para revender.
3. El valor se descuenta del total, y se cobra la diferencia.

Así quedó: **la permuta es un pago más**. La venta sigue valiendo lo que vale (7.000) y el equipo recibido paga una parte (2.500); la ganancia y la comisión salen sobre los 7.000. El equipo recibido queda en Inventario, en la sucursal de la venta, con ese valor como costo y **fuera del catálogo** hasta que se le ponga precio. Su historial dice de qué venta vino. Si se anula la venta, el equipo recibido sale del inventario (se devuelve); si ya se vendió o se reparó, la venta no se puede anular.

```
Venta con permuta

  Vende    iPhone 15 · 256 GB          7.000 Bs
  Recibe   iPhone 11 · 64 GB · grado 2  -2.500 Bs
                                       ---------
  Diferencia a cobrar                   4.500 Bs

  Cobra    3.000 efectivo + 1.500 QR

  El iPhone 11 queda en Inventario con costo 2.500 Bs,
  listo para ponerle precio y venderlo.
```

### Reserva

Un cliente aparta un equipo dejando una seña:

1. Se elige el equipo y se registra el monto de la seña con su método de pago.
2. El equipo pasa a estado **Reserva** y **deja de estar disponible**: no aparece para vender ni en el catálogo público.
3. Cuando el cliente vuelve, se concreta la venta y **la seña se descuenta** del total.

```
Reserva
  iPhone 13                3.500 Bs
  Seña en efectivo           500 Bs   -> equipo bloqueado

Días después, al concretar
  Total                    3.500 Bs
  Seña ya pagada            -500 Bs
  A cobrar                 3.000 Bs
```

Así quedó:

- Se reserva desde el estado del equipo («Reservar») o desde la pantalla de **Reservas** («Nueva reserva», buscando el equipo por IMEI, también con el lector). Se elige el cliente (obligatorio), la seña y su método.
- **La reserva no vence**: queda hasta que se concreta o se cancela. La pantalla de reservas muestra cuántos días lleva cada una.
- **Concretar** abre la venta con el equipo, el cliente y la seña ya cargados; la seña entra como pago.
- **Cancelar** devuelve el equipo al inventario y obliga a elegir si la seña **se devuelve** o **la retiene el negocio**; queda registrado.
- «Reserva» ya no se elige a mano en el estado del equipo.
- Si se anula una venta que vino de una reserva, la reserva queda cancelada con la seña devuelta.

### Dar de baja

La baja de equipos y de unidades de repuestos o accesorios se hace desde el inventario, no desde la venta. El detalle está en [Inventario](02-inventario.md#dar-de-baja).

### La nota de venta

Se imprime un comprobante en **rollo térmico de 80mm**, con:

```
        [ LOGO ]
      NOTA DE COMPRA
        Nº 000142
  ------------------------------
  Local Centro
  Av. Siempre Viva 1234
  Tel. 3-3456789
  ------------------------------
  Fecha    24/09/2026    15:40
  Cliente  Juan Pérez
  Vendedor Carlos Ruiz
  ------------------------------
  iPhone 13 128GB Negro
  IMEI 358...4471
                      3.200,00
  Cargador 20W          180,00
  ------------------------------
  Subtotal            3.380,00
  Descuento             -80,00
  TOTAL               3.300,00

  Efectivo            2.000,00
  QR                  1.300,00
  ------------------------------
  Garantía: 3 meses
      ¡Gracias por su compra!
```

Así quedó: botón **«Imprimir nota»** en el detalle de la venta; se abre y se imprime sola, en 80 mm. Lleva lo básico, sin datos fiscales: es un comprobante interno. Los accesorios aparecen debajo del equipo con el que se vendieron, y los pagos muestran los dólares, la permuta y la seña.

> **Falta**: el logo definitivo en buena calidad.

### El escáner en la venta

Al cargar los ítems de la venta se puede **escanear el código de barras** en lugar de buscar por nombre: con la cámara del celular o con la pistola USB. Funciona igual para equipos, repuestos y accesorios.

Para un teléfono, **conviene escanear el IMEI de la caja**: lo agrega directo. El otro código de barras de la caja es del modelo y lo comparten todos los equipos iguales, así que muestra la lista para elegir cuál.

### La comisión del vendedor

Cada venta registra automáticamente la comisión de quien la hizo, calculada sobre la ganancia. Queda pendiente hasta que la venta esté cobrada por completo. Ver [Comisiones](05-comisiones.md).

---

## Cómo queda el trabajo diario

```
Venta completa, con todo junto

  Cliente    Juan Pérez  (se busca por CI o nombre; si es nuevo, se carga)

  Escanea el equipo                  iPhone 15 256GB     7.000 Bs
  Escanea un accesorio               Cargador 20W          180 Bs
  Recibe en permuta                  iPhone 11 grado 2  -2.500 Bs
  Descuento                                                -180 Bs
                                                        ---------
  Total a cobrar                                         4.500 Bs

  Cobra      Efectivo    2.000 Bs
             QR          1.500 Bs
             Pendiente   1.000 Bs   -> venta a crédito

  Garantía   3 meses
  Imprime la nota térmica

  Qué queda registrado:
    · El iPhone 15 pasa a "Venta a crédito"
    · El iPhone 11 entra a Inventario con costo 2.500
    · El cargador baja del stock
    · Juan Pérez queda con 1.000 Bs de deuda
    · La comisión de Carlos queda pendiente hasta cobrar esos 1.000
```

---

## Qué NO incluye

- **No es facturación.** No emite factura fiscal ni se conecta con impuestos. La nota de venta es un comprobante interno.
- **No hay devoluciones ni cambios.** Si hay que revertir una venta, se cancela y se vuelve a cargar.
- **No hay caja ni arqueo.** El sistema registra con qué método se cobró cada venta, pero no lleva apertura y cierre de caja ni cuadre de efectivo.
- **No hay reserva de accesorios**, solo de equipos.
- **La permuta no tasa el equipo.** El valor lo pone la persona; el sistema no sugiere precios.
- **Una sola nota por venta.** No se emiten notas parciales por cada pago.
- **No se envía la nota por WhatsApp ni por correo**; se imprime.

---

## Entregables

0. Venta unificada: equipos, repuestos y accesorios en la misma orden, con un solo buscador por IMEI, UPC, SKU o nombre.
1. Venta en bolivianos, con opción de marcarla como transacción en dólares (tipo de cambio y método de pago).
2. Módulo de métodos de pago, administrable por el negocio.
3. Cobro con varios métodos de pago en una misma venta.
4. Accesorios vendidos junto con el equipo, con descuento de stock.
5. Flujo de permuta: registro del equipo recibido, alta en inventario y descuento del total.
6. Flujo de reserva: seña, bloqueo del equipo y descuento al concretar.
7. Nota de venta en rollo térmico de 80mm.
8. Escáner de código de barras en la carga de la venta (cámara y pistola).
9. Baja de equipos y accesorios con motivo (desde el inventario).
10. Cálculo y registro de la comisión del vendedor en cada venta.
