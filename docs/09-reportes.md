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

Todo lo anterior **se mantiene**, ahora en bolivianos. Y se agregan cuatro reportes.

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
