# Resumen

## Qué es el sistema

Un sistema de inventario y ventas para un negocio de teléfonos. Maneja tres cosas a la vez, y entender cómo se distinguen explica casi todo lo demás:

- **Los equipos**: cada teléfono es una unidad con su IMEI, su costo, su estado y su historia. No hay "5 iPhone 13": hay cinco equipos, cada uno con su número y su precio.
- **Los repuestos y accesorios**: esto sí es stock por cantidad. Pantallas, baterías, fundas, cargadores.
- **El taller**: los equipos entran a reparación, consumen repuestos y salen listos para vender, con su costo de reparación ya sumado.

Todo lo que se compra, se repara y se vende queda registrado, y los reportes muestran cuánto se vendió y cuánto se ganó.

## El sistema hoy, módulo por módulo

| Módulo | Qué hace | En el negocio nuevo |
|---|---|---|
| **Usuarios** | Quién entra al sistema | Cambia: se le agrega su % de comisión |
| **Roles** | Qué puede ver y hacer cada usuario | Se mantiene |
| **Proveedores** | A quién se le compra | Se mantiene |
| **Técnicos** | Quién repara | Cambia: se le agrega su comisión |
| **Sucursales** | En qué local está cada cosa | Cambia: **hoy no tiene pantalla**, se construye |
| **Productos** | El inventario de equipos | Cambia bastante |
| **Repuestos y accesorios** | El stock de piezas | Cambia |
| **Marcas, categorías, modelos** | Los catálogos del inventario | Se mantienen |
| **Compras de productos** | El ingreso de equipos | Cambia: estados, reclamos y crédito |
| **Compras de repuestos** | El ingreso de piezas | Cambia: en Bs y con escáner |
| **Ventas de productos** | La venta de equipos | Cambia mucho |
| **Ventas de repuestos** | La venta de piezas y accesorios | Cambia: en Bs y con escáner |
| **Reportes** | Cuánto se vendió y se ganó | Cambia: se agregan cuatro reportes |
| **Catálogo público** | La vitrina sin contraseña | Se mantiene |
| **Clientes** | — | **Nuevo** |
| **Cobranzas y pagos** | — | **Nuevo** |
| **Comisiones** | — | **Nuevo** |
| **Bot de WhatsApp** | Avisaba cada operación al administrador | **Se retira** |
| **Enlace público del técnico** | El técnico veía sus equipos sin entrar al sistema | **Se retira** |

## Lo más importante de todo el cambio

**Tres de los pedidos no son campos nuevos, son módulos enteros:**

1. **Clientes.** Hoy el cliente es apenas un nombre escrito a mano en cada venta: no hay ficha, no hay historial, no hay forma de saber qué debe. Todo lo que se pidió sobre deudas, cobros y reportes por cliente necesita crear ese módulo desde cero.
2. **Cobranzas y pagos.** Las ventas a crédito, las compras a crédito, los métodos de pago y los pagos parciales son un mismo mecanismo que hoy no existe en ninguna forma.
3. **Comisiones.** Calcular, acumular, liquidar y pagar comisiones de vendedores y técnicos.

Y dentro de Ventas hay dos flujos nuevos que no son "un campo más": **la permuta** (recibir un equipo como parte de pago) y **la reserva** (apartar un equipo con una seña).

## Huecos del sistema actual que conviene tapar

Al revisar el sistema pieza por pieza aparecieron cosas que el pedido no menciona, porque nadie las nota hasta que las necesita. Se incluyen en el alcance:

| Hueco | Por qué importa |
|---|---|
| **Las sucursales no tienen pantalla.** Están cargadas directamente en la base de datos y solo guardan el nombre | Abrir un local nuevo, renombrarlo o cerrarlo hoy exige llamar a quien tenga acceso técnico. Y sin dirección ni teléfono, la nota de venta impresa no puede mostrar de qué local salió |

## La moneda

Hoy el sistema calcula todo en dólares y convierte a bolivianos con un tipo de cambio que se carga en cada operación. En el negocio nuevo se da vuelta:

> **Todo el inventario se maneja en bolivianos.** Costos, precios, compras y reportes: todo en Bs, sin tipo de cambio de por medio.
>
> **La venta puede marcarse como realizada en dólares.** En ese caso se registra el tipo de cambio de ese momento y el método de pago usado.

Esto simplifica el día a día —nadie tiene que pensar en conversiones para cargar un producto— y deja el dólar donde realmente aparece: en el mostrador, cuando un cliente paga en dólares.

## Decisiones ya tomadas

Estas son las respuestas que ya se dieron a las dudas del pedido original. **Conviene leerlas una por una y confirmar o corregir**, porque de acá sale el presupuesto.

| # | Tema | Decisión |
|---|---|---|
| 1 | Moneda | Todo el inventario en Bs; la venta puede marcarse en USD con su tipo de cambio y método de pago |
| 2 | Datos iniciales | El sistema arranca vacío, solo con los catálogos base cargados |
| 3 | Sucursales | Se mantienen |
| 4 | Lotes de compra | Se mantienen |
| 5 | Bot de WhatsApp | Se retira |
| 6 | Enlace público del técnico | Se retira |
| 7 | Comisión del vendedor | Un % sobre la **ganancia** de la venta (precio menos costo) |
| 8 | Cuándo se gana esa comisión | Cuando la venta queda **cobrada por completo** |
| 9 | Comisión del técnico | **50% de la mano de obra cobrada**; los repuestos corren por cuenta del negocio |
| 10 | Estados del equipo | Se quita **Tránsito**; se agregan **Reserva** y **Venta a crédito** |
| 11 | Dar de baja | Se cubre con los estados que ya existen: **Roto** (se dañó) y **Fuera** (se perdió) |
| 12 | Reserva | Seña a cuenta; el equipo queda bloqueado y al vender la seña se descuenta del total |
| 13 | Accesorios de regalo | Se eligen del stock, lo descuentan, y su costo se suma al costo del equipo |
| 14 | Permuta | El equipo recibido entra al inventario como producto nuevo, con el valor que se le reconoció |
| 15 | Tipo de venta | Venta / Oferta / **Venta externa** (vendida fuera del local, para separarla en reportes) |
| 16 | Grado del equipo | Lista cerrada: **A+, 1, 2, 3** |
| 17 | Nota de venta | Impresa en **rollo térmico de 80mm** |
| 18 | Lector de código de barras | **Cámara del celular y pistola USB**, las dos sobre el mismo campo |
| 19 | Clientes | Ficha completa: datos, compras, reservas, deuda, pagos y garantías |
| 20 | Compra reclamada | El equipo fallado sale del inventario vendible hasta que el proveedor lo reponga |

## Cómo arranca el negocio nuevo

El sistema se instala vacío. Antes de la primera venta hay que cargar, en este orden:

1. **Sucursales** (los locales)
2. **Roles y usuarios** (quién entra y qué puede hacer, con su % de comisión)
3. **Técnicos** (quién repara)
4. **Proveedores**
5. **Métodos de pago** (efectivo, QR, transferencia, tarjeta)
6. **Marcas, categorías y modelos** de teléfonos — vienen precargados los más comunes
7. **Categorías de repuestos y accesorios**
8. La primera **compra**, que es la que mete los equipos al inventario

No se traen datos de ningún sistema anterior: no hay inventario viejo, ni ventas viejas, ni clientes viejos que importar.
