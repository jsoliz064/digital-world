# 9. Reportes

## Para qué sirve

Contestar las preguntas del dueño: cuánto se vendió, cuánto se ganó, qué se vende más y cómo viene el mes comparado con el anterior.

---

## Cómo funciona hoy

Una pantalla con un rango de fechas y tres pestañas:

| Pestaña | Qué muestra |
|---|---|
| **Celulares** | Las ventas de equipos |
| **Repuestos** | Las ventas de piezas |
| **Accesorios** | Las ventas de accesorios |

En cada una:

- **Ingreso, costo y ganancia** del período, con el margen.
- **Comparación con el período anterior**: si subió o bajó, y cuánto.
- **Gráfico** por día, semana o mes.
- **Ranking** de lo más vendido.
- Un **resumen general** de todo el negocio, independiente de la pestaña, con la cantidad de operaciones y el ticket promedio.

Hay además un **tablero de inicio** con las ventas del día y del mes, y cuántos equipos hay en inventario y en reparación.

---

## Qué cambia

Todo lo anterior **se mantiene**, ahora en bolivianos y sobre la venta y la compra unificadas: cada pestaña lee las líneas de su tipo (equipo, repuesto o accesorio), y el descuento de una venta se reparte a prorrata entre todas sus líneas. Una venta con un equipo y un cargador cuenta como **una** operación.

Ya incluido en la etapa 2:

- Tarjeta de **pérdidas**: equipos dados de baja (a su costo) y unidades de repuestos o accesorios dadas de baja, en el resumen general y en cada pestaña.
- Los celulares se desglosan por **tipo de venta** (Venta, Oferta, Venta externa), con el tipo que tenía el equipo al venderse.
- Ya no se estima un 20 % de ganancia para los equipos sin costo cargado: cada venta guarda el costo real del equipo.

Y se agregan cuatro reportes.

### Por vendedor

Cuánto vendió cada persona y cuánto generó:

```
DEL 01/09/2026 AL 30/09/2026

  Vendedor        Ventas   Monto        Ganancia    Comisión
  Carlos Ruiz         12   38.400,00    7.200,00      216,00
  Ana Flores          18   52.100,00    9.850,00      295,50
  Pedro Vargas         7   19.300,00    3.100,00       93,00
                      --   ---------    ---------    --------
  TOTAL               37  109.800,00   20.150,00      604,50
```

Con el detalle de cada uno: qué vendió, a quién, y cuánta comisión le quedó pendiente, por pagar y pagada.

### De productos

Qué equipos se mueven y cuáles no:

- Los **más vendidos** por modelo y capacidad.
- **Cuánto tarda en venderse** cada modelo, desde que entra hasta que sale.
- Los equipos **parados**: los que llevan mucho tiempo en inventario sin venderse.
- **Ganancia por modelo**, para ver cuál conviene traer.

### De inventario

Cuánta plata hay parada y dónde:

- **Valor del inventario** al costo y al precio de venta, por sucursal.
- **Cuántos equipos hay por estado**: inventario, reparación, reservados, a crédito.
- **Por grado**, para ver la composición del stock.
- **Pérdidas**: lo dado de baja por daño o extravío en el período, con su costo.
- **Stock de repuestos y accesorios**, con lo que está por agotarse.

### Por cliente

Quiénes son los clientes y qué pasa con ellos:

- Los que **más compran**, por monto y por cantidad.
- **Deuda por cliente** y antigüedad de esa deuda.
- **Clientes nuevos** del período.
- Historial de compras de cada uno.

---

## Cómo queda el trabajo diario

```
Cierre de mes

  1. Reportes -> mes de septiembre
  2. Resumen general:     vendido, ganado, cuánto subió contra agosto
  3. Por vendedor:        quién vendió más y cuánto hay que pagarle
  4. De inventario:       cuánta plata está parada y en qué
  5. Por cliente:         cuánto falta cobrar y desde cuándo
  6. De productos:        qué modelos conviene reponer y cuáles no
```

---

## Qué NO incluye

- **No es contabilidad.** No hay libro diario, balance ni estado de resultados; son reportes de gestión.
- **No hay exportación a Excel** de los reportes nuevos, salvo que se pida.
- **No hay reportes armables por el usuario**: son los reportes definidos, no un constructor.
- **No hay envío automático** por correo ni por WhatsApp.
- **No hay proyecciones ni pronósticos**: se informa lo que pasó, no lo que va a pasar.
- **No hay comparación entre sucursales** más allá de filtrar por una.

---

## Entregables

1. Los reportes actuales pasados a bolivianos.
2. Reporte por vendedor, con ventas, ganancia y comisiones.
3. Reporte de productos: más vendidos, rotación, parados y ganancia por modelo.
4. Reporte de inventario: valor, composición por estado y grado, y pérdidas.
5. Reporte por cliente: los que más compran, deudas y clientes nuevos.

---

## Así quedó

En **Reportes** hay pestañas: General · Vendedores · Productos · Inventario · Clientes, cada una con su permiso (solo el Administrador).
- Todas tienen el **período** (el mes actual, con atajos de mes anterior y siguiente) y el filtro de **sucursal**.
- El reporte general también ganó el filtro de sucursal.

**Vendedores**
- Por vendedor: ventas, monto, ganancia, margen, ticket promedio y comisión de esas ventas, repartida en pendiente, por pagar y pagada. Con fila de total.
- Al tocar un vendedor se ven sus ventas del período (cliente, total, ganancia, comisión y estado), con enlace a su ficha.
- El monto incluye la mano de obra; la ganancia no, porque suma al total y al costo.

**Productos**
- **Equipos vendidos por modelo** (modelo y capacidad): vendidos, ingreso (con el descuento repartido), costo, ganancia, margen, **días en venderse** y **cuántos quedan hoy**.
- Se ordena por más vendidos, más ganancia, mejor margen o más lentos.
- Los días en venderse se cuentan desde la fecha de la compra hasta la venta. Si el equipo vino en permuta, desde su alta.
- **Equipos parados**: los disponibles que llevan 30, 60, 90 o 180 días o más sin venderse, con su costo, su precio y la plata parada en total.

**Inventario** (a hoy, salvo las pérdidas)
- **Valor por sucursal**:
  - equipos sin vender, al costo y a precio vendedor (el roto a su costo);
  - repuestos y accesorios, unidades por costo y por precio;
  - con total.
- **Equipos por estado** y **por grado**.
- **Pérdidas del período** por motivo, con su detalle. La devolución al proveedor no cuenta.
- **Por agotarse**: cada artículo y sucursal que llegó a su mínimo, con cuánto falta.

**Clientes**
- Los que **más compran** en el período (top 20, por monto o por cantidad de compras).
- La **deuda por cliente** a hoy, con su antigüedad en tramos de 0–30, 31–60, 61–90 y más de 90 días, contados desde la fecha de cada venta con saldo.
- Los **clientes nuevos** del período, con lo que compraron.
- Cuántas ventas se hicieron **sin ficha** (mostrador).
- Cada nombre lleva a la ficha del cliente, que es su historial de compras.

Sin exportación a Excel (decisión del usuario): se ven en pantalla y se pueden imprimir desde el navegador.
