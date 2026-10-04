# 2. Inventario

## Para qué sirve

Saber qué hay, dónde está, cuánto costó y en cuánto se vende. Es el corazón del sistema: todo lo demás entra o sale de acá.

---

## Productos (los equipos)

### Cómo funciona hoy

Cada teléfono es una ficha única identificada por su **IMEI**. No se manejan cantidades: si entran cinco iPhone 13, son cinco fichas distintas, cada una con su costo, su precio y su historia.

La ficha guarda:

- **Qué es**: modelo, almacenamiento, versión, color, porcentaje de batería, descripción y observaciones.
- **Cuánto costó**: costo de la unidad, costo de envío, costo de reparación (que se va sumando solo cuando pasa por el taller) y el costo total.
- **En cuánto se vende**: precio para cliente y precio para vendedor, cada uno con su precio anterior guardado para ver el cambio.
- **Dónde y cómo está**: sucursal, estado, tipo de venta, grado, si sale en el catálogo público, si es de venta rápida, si tiene garantía activa.
- **De dónde vino**: la compra por la que ingresó.
- **Fotos** del equipo.

Cada equipo lleva su **historial**: cada vez que cambia de estado se anota qué pasó, cuándo y quién lo hizo. Eso permite abrir un equipo vendido y ver todo su recorrido: cuándo entró, qué reparaciones tuvo, con qué repuestos y a quién se vendió.

Hay también cambios masivos: seleccionar varios equipos y cambiarles el estado o el tipo de venta de una sola vez.

### El grado del equipo

Hoy el grado es un campo de texto libre, y por eso está cargado con cualquier cosa: se ven equipos con grado `1`, `B`, `A`, `10` y `AB`. No sirve para filtrar ni para comparar.

**Pasa a ser una lista cerrada de cuatro opciones:**

| Grado | |
|---|---|
| **A+** | Como nuevo |
| **1** | |
| **2** | |
| **3** | |

Al cargar un equipo se elige de una lista, no se escribe. Con eso se puede filtrar el inventario por grado y el catálogo puede mostrarlo de forma pareja.

> **Falta definir**: qué describe exactamente cada grado (rayas, golpes, batería). Conviene dejarlo escrito para que todos carguen igual.

### Tipo de venta

Hoy son tres: Venta, Oferta y Súper Oferta.

**Pasan a ser:** Venta, Oferta y **Venta externa**.

"Venta externa" marca lo que se vendió fuera del local —por redes, a domicilio, en otro punto— para poder separarlo en los reportes. No cambia cómo se calcula ni la ganancia ni la comisión: es una clasificación.

### Los estados del equipo

Hoy un equipo puede estar en: Inventario, Reparación, Vendido, Roto, Fuera o Tránsito.

| Estado | Qué significa | En el negocio nuevo |
|---|---|---|
| **Inventario** | Está para vender | Se mantiene |
| **Reparación** | Está en el taller | Se mantiene |
| **Vendido** | Se vendió y se cobró | Se mantiene |
| **Roto** | Está roto | Se mantiene. **No es una baja**: puede repararse o darse de baja aparte |
| **Fuera** | Salió del local (lo tiene alguien, está en otro punto) | Se mantiene. **No es una baja** |
| **Tránsito** | Viajando entre locales | **Se retira** |
| **Reserva** | Apartado con una seña | **Nuevo** |
| **Venta a crédito** | Vendido pero todavía no cobrado del todo | **Nuevo** |

**Reserva** y **Venta a crédito** no son etiquetas sueltas: detrás de cada una hay dinero registrado. Están explicadas en [Ventas](03-ventas.md) y [Clientes y cobranzas](04-clientes-y-cobranzas.md).

**Vendido** y **Venta a crédito** solo los pone una venta: no se pueden elegir a mano en el cambio de estado. La **oferta** tampoco es un estado: es el tipo de venta, y un equipo en oferta está en Inventario como cualquier otro.

### Dar de baja

La baja **no es un estado**: es una marca aparte que **archiva** el equipo. Se elige el motivo (daño, pérdida, robo, defecto de fábrica u otro) y se puede dejar una nota.

Un equipo dado de baja deja de aparecer en el listado de productos, en las ventas y en el catálogo público, pero no se borra: queda su ficha y su historia, y aparece en el reporte de pérdidas a su costo. El listado tiene un filtro para verlos, y la baja se puede revertir si fue un error.

No se puede dar de baja un equipo vendido, a crédito, reservado o en reparación: primero se anula la venta, se libera la reserva o se termina la reparación.

Para los repuestos y accesorios, que se manejan por cantidad, la baja descuenta las unidades de una sucursal dejando registrado el motivo y el costo.

### Accesorios de regalo

Hoy la ficha tiene un **costo de envío**, un monto suelto que se suma al costo del equipo. Se usó poco: en 4 equipos de 93.

**Ese campo se reemplaza por los accesorios de regalo.** En lugar de escribir un monto, se eligen del stock los accesorios que se entregan junto con el teléfono:

```
iPhone 13 — IMEI 358...4471
  Costo del equipo                      2.400 Bs
  Accesorios de regalo:
    Funda silicona      (stock 12 -> 11)   35 Bs
    Vidrio templado     (stock  8 ->  7)   20 Bs
                                         --------
  Costo de regalos                         55 Bs
  Costo total del equipo                2.455 Bs
```

Tres cosas pasan al mismo tiempo: **baja el stock** de esos accesorios, **sube el costo** del equipo, y queda **el detalle a la vista** de qué se regaló. Así el inventario de accesorios cuadra y la ganancia del equipo es la real.

### Código de barras (UPC)

Se agrega el campo **UPC** a la ficha del equipo, y se puede buscar por él.

El código se puede cargar y buscar de dos formas, sobre el mismo campo:

- **Con la cámara del celular**, abriendo el lector desde la pantalla.
- **Con una pistola USB**, de las que se conectan a la computadora del mostrador y escriben el código como si fuera un teclado.

> La lectura por cámara exige que el sistema esté publicado con **HTTPS**: los navegadores no dan acceso a la cámara en sitios sin certificado. Es un requisito de la instalación, no del programa.

Cómo funciona:

- Cada campo donde se escribe un código tiene al lado un **botón de cámara**. Al leer, la cámara escribe el código en el campo como si fuera la pistola: las dos hacen exactamente lo mismo.
- En los buscadores, **un código que coincide con un solo artículo lo agrega directo** y deja el campo listo para el siguiente. Si coincide con varios, o con ninguno, queda la lista para elegir.
- **El código de barras de la caja de un teléfono es del modelo, no del equipo**: cinco iPhone 13 de 128 GB negros traen el mismo. Por eso escanear ese código muestra la lista, y escanear el **IMEI** (que también viene en la caja como código de barras) agrega el equipo directo.
- La cámara tiene **linterna** (en los celulares que la permiten) y una casilla **«Seguir escaneando»** para leer varios códigos seguidos sin cerrarla.
- Está en la venta y la compra, en la carga de equipos, en las fichas de repuestos y accesorios, en la transferencia entre sucursales, en el cambio de estado masivo, en los regalos, en los repuestos de las reparaciones y en la búsqueda de las tablas de productos, repuestos, accesorios y lotes de compra.

### Código interno (SKU)

Para lo que no trae código de barras, cada equipo, repuesto o accesorio puede llevar un **SKU**: un código libre que asigna el negocio, opcional y sin repetirse. Se busca desde la misma caja que el IMEI y el UPC, también con la pistola lectora.

### Todo en bolivianos

Hoy los costos y precios del inventario se cargan en dólares y el sistema convierte con un tipo de cambio.

**En el negocio nuevo el inventario se carga y se muestra directamente en bolivianos.** Desaparece el tipo de cambio de la ficha del producto, de la compra y del repuesto. El dólar solo aparece en la venta, cuando el cliente paga en dólares.

### El producto, campo por campo

| Hoy | En el negocio nuevo |
|---|---|
| IMEI | Igual |
| — | **UPC** (código de barras) |
| — | **SKU** (código interno, opcional) |
| Modelo, almacenamiento, versión, color, batería | Igual |
| Descripción y observaciones | Igual |
| Grado (texto libre) | **Lista: A+, 1, 2, 3** |
| Tipo de venta: Venta / Oferta / Súper Oferta | **Venta / Oferta / Venta externa** |
| Estado (6 opciones, con Tránsito) | **7 opciones: sin Tránsito, con Reserva y Venta a crédito** |
| — | **Baja** con motivo, aparte del estado |
| Costo de unidad (USD) | **En Bs** |
| Costo de envío (USD) | **Reemplazado por accesorios de regalo, en Bs** |
| Costo de reparación (USD) | **En Bs** |
| Costo total (USD) | **En Bs** |
| Precio cliente y precio vendedor (USD) | **En Bs** |
| Sucursal, compra de origen, fotos | Igual |
| Disponible en catálogo, venta rápida, garantía | Igual |
| Historial de movimientos | Igual |

---

## Repuestos y accesorios

### Cómo funciona hoy

Una sola pantalla maneja las dos cosas, separadas por un tipo:

- **Repuestos**: las piezas que usa el taller (pantallas, baterías, flex). Se manejan por cantidad, tienen fabricante, modelo al que aplican, categoría y color.
- **Accesorios**: lo que se vende al cliente (fundas, cargadores, cables). Como no son piezas de un modelo concreto, su ficha es más corta: solo nombre, costo y precio.

De cada uno se sabe cuánto hay, cuánto costó y en cuánto se vende. El stock se mueve solo: baja cuando se vende o cuando se monta en una reparación, y sube cuando se compra.

Hay también un historial por repuesto, que muestra todas sus entradas y salidas: de qué compra vino, en qué venta salió y en qué reparación se usó.

### Qué cambia

**Repuestos y accesorios pasan a ser dos listas separadas**, cada una con su pantalla, su historial y sus permisos:

- **Repuestos**: igual que hoy (fabricante, modelo al que aplican, categoría de repuesto, color).
- **Accesorios**: con su **propia categoría** (fundas, cargadores, cables...), **marca** y los **modelos compatibles**, que pueden ser varios. Los compatibles son los que el sistema ofrece primero al elegir los regalos de un equipo.

El stock por sucursal, las transferencias entre sucursales, la baja de unidades y el historial de entradas y salidas funcionan igual para los dos.

**Todo en bolivianos.** Igual que los equipos: se carga el costo y el precio en Bs, sin tipo de cambio.

**El costo se rotula «Costo (código)».** El dato sigue siendo el costo y se usa igual para calcular la ganancia; el rótulo recuerda que el personal lo usa como código interno.

**Se agregan el UPC y el SKU**, con el mismo lector por cámara y por pistola.

**Stock mínimo por sucursal** (etapa 8):
- En la ficha del artículo, cada sucursal tiene su cantidad **y su mínimo**. La tienda puede pedir 5 fundas y el Almacén 30.
- Una sucursal está **por agotarse** cuando su cantidad llega al mínimo o baja de él.
- El listado de repuestos o accesorios pinta en rojo la cantidad y marca la sucursal.
- Las tarjetas y el filtro «Por agotarse» lo usan, y el reporte de inventario lo lista.
- Mínimo 0 = sin mínimo: no avisa nunca.
- Reemplaza al umbral fijo de 9 unidades sobre el total, que no avisaba si una tienda se quedaba sin nada mientras el Almacén tenía.

---

## Los catálogos

**Marcas**, **categorías de productos**, **modelos** (cada uno con sus capacidades de almacenamiento), **categorías de repuestos** y **categorías de accesorios** (estas últimas, nuevas y separadas).

Se mantienen tal como están. Son los listados que alimentan los desplegables al cargar un equipo o una pieza, y se administran desde sus propias pantallas.

El sistema nuevo arranca con los modelos y marcas más comunes ya cargados, para no empezar de cero.

---

## Cómo queda el trabajo diario

```
Llega un equipo de la compra y se lo carga

  Escanea el código de barras de la caja     -> UPC 019...2284
  IMEI          358...4471   (también se escanea)
  Modelo        iPhone 13 · 128 GB · Negro
  Batería       92%
  Grado         A+
  Tipo          Venta
  Costo         2.400 Bs
  Precio        3.200 Bs
  Sucursal      Local Centro
  Regalos       Funda (35) + Vidrio (20)   -> baja stock, costo pasa a 2.455

  Queda en Inventario y sale en el catálogo público.
```

```
Se cae un equipo del mostrador

  Estado        Inventario -> Roto           (sigue en el listado)
  Si no vale la pena repararlo:
  Dar de baja   Motivo: Daño
                Nota: "Se rompió la pantalla y la placa"

  Sale del listado y de la venta. Queda su ficha, su costo y su
  historia, y aparece en el reporte de pérdidas.
```

---

## Qué NO incluye

- **No hay conteo físico ni toma de inventario.** El sistema no tiene una pantalla para "contar lo que hay y ajustar diferencias".
- **No hay alertas automáticas de stock bajo.** El listado marca en rojo los repuestos con poca cantidad, pero no avisa por su cuenta.
- **No hay lotes ni vencimientos** en repuestos.
- **El UPC no se genera ni se imprime.** El sistema lee y guarda códigos existentes; no imprime etiquetas.
- **El cambio de grado no recalcula precios.** Poner un equipo en grado 3 no le baja el precio solo.
- **Los accesorios de regalo se descuentan del stock general**, no de un stock separado de promociones.

---

## Entregables

1. Conversión de todo el inventario a bolivianos: productos, repuestos y accesorios, y las pantallas donde se cargan.
2. Grado como lista cerrada (A+, 1, 2, 3), con filtro en el listado.
3. Tipo de venta: reemplazo de Súper Oferta por Venta externa.
4. Estados nuevos (Reserva, Venta a crédito) y retiro de Tránsito.
5. Baja de equipos con motivo (archiva el equipo, aparte del estado), y baja de repuestos y accesorios por cantidad.
6. Campo UPC en equipos y en repuestos y accesorios, con búsqueda.
7. Lector de código de barras por cámara del celular.
8. Compatibilidad con pistola lectora USB.
9. Accesorios de regalo: selección desde el stock, descuento de cantidad, suma al costo del equipo y detalle a la vista.
10. Rótulo «Costo (código)» en repuestos y accesorios.
11. SKU opcional en equipos, repuestos y accesorios, con búsqueda junto al IMEI y el UPC.
12. Repuestos y accesorios en listas separadas, con categorías de accesorio, marca y modelos compatibles.
