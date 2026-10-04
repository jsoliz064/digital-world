# 4. Clientes y cobranzas

> **Módulo nuevo.** Hoy no existe nada de esto en el sistema.

## Qué problema resuelve

Hoy el cliente es apenas **un nombre escrito a mano en cada venta**. Si Juan Pérez vuelve, nadie sabe qué compró antes, si tiene garantía vigente o si quedó debiendo. Si se vende a crédito, no hay dónde anotar los pagos ni forma de saber cuánto falta cobrar.

Este módulo crea la ficha del cliente y, sobre ella, todo el manejo de deudas y cobros.

---

## Qué se construye

### La ficha del cliente

| Dato | |
|---|---|
| Nombre completo | |
| CI o NIT | Para identificarlo sin equivocarse |
| Teléfono | |
| Dirección | |
| Observaciones | Notas internas |

Y desde su ficha se ve todo lo suyo:

```
Juan Pérez — CI 8123456
Tel. 700-12345

  DEUDA PENDIENTE                    1.000,00 Bs

  Compras (4)
    24/09/2026  iPhone 15 256GB      4.500,00   debe 1.000,00
    02/07/2026  Cargador 20W           180,00   pagado
    15/03/2026  iPhone 11 128GB      3.100,00   pagado
    15/03/2026  Funda + vidrio          55,00   pagado

  Reservas (1)
    20/09/2026  iPhone 14  ·  seña 500,00

  Garantías vigentes (1)
    iPhone 15 256GB  ·  vence 24/12/2026

  Pagos recibidos (6)
```

### Venta a crédito

Una venta queda a crédito cuando lo cobrado es menor que el total. El saldo pendiente queda atado al cliente y el equipo pasa al estado **Venta a crédito**: ya salió del inventario —el cliente se lo llevó— pero la venta todavía no está cerrada.

### Cobros

Desde la ficha del cliente, o desde una pantalla de cobranzas, se registran los pagos:

```
Cobro a Juan Pérez

  Deuda total                        1.000,00 Bs

  Venta #142 (24/09/2026)            1.000,00 pendiente
    Cobra   600,00 en efectivo
                                     ---------
    Saldo                              400,00

  Estado de la venta: sigue a crédito
```

Cada pago guarda **cuánto, cuándo, con qué método y quién lo recibió**. Cuando el saldo llega a cero:

- La venta pasa a **pagada** y el equipo, a **Vendido**.
- La **comisión del vendedor se libera** y pasa de pendiente a ganada.

### Pantalla de cobranzas

Un listado de todo lo que está por cobrar, para trabajarlo todos los días:

```
POR COBRAR                          Total: 8.450,00 Bs

  Cliente          Venta   Fecha        Total     Saldo
  Juan Pérez        #142   24/09/26   4.500,00  1.000,00
  María Quispe      #138   18/09/26   2.900,00  2.900,00
  Luis Mamani       #131   05/09/26   6.200,00  4.550,00
```

Ordenable por antigüedad y por monto, y filtrable por cliente y por vendedor.

### Cómo quedó construido

- **El cobro se registra en la misma venta**, con uno o varios métodos de pago (Efectivo, QR, Transferencia, Tarjeta, o los que el negocio agregue desde **Métodos de pago**). Si se cobra todo, la venta queda pagada; si queda saldo, queda **a crédito** y el cliente con ficha pasa a ser obligatorio.
- **Al elegir el cliente en la venta se ve lo que ya debe.** El sistema avisa, no bloquea.
- **Un cobro posterior** se hace desde la ficha del cliente, desde la pantalla de cobranzas o desde el detalle de la venta. Se escribe el monto recibido y el sistema lo reparte de la venta más antigua a la más nueva; cada venta se puede corregir a mano.
- **Si el celular pierde la señal y se reintenta**, el cobro no se registra dos veces.
- **Anular un pago** (solo el Administrador) devuelve ese saldo a la venta: si estaba pagada, vuelve a crédito.
- **Anular una venta con pagos** anula también los pagos: se entiende que el dinero se devolvió. Queda registrado en la bitácora.
- **No se puede bajar el total de una venta** (editándola o anulando una línea) por debajo de lo ya cobrado: primero hay que anular un pago.
- Las **reservas** todavía no aparecen en la ficha: la reserva con seña llega en la etapa de ventas.

---

## Cómo queda el trabajo diario

```
Un cliente entra a pagar una cuota

  1. Se lo busca por nombre o CI
  2. Se ve que debe 1.000 Bs de la venta #142
  3. Trae 600 en efectivo -> se registra el cobro
  4. Queda debiendo 400, y así aparece en la pantalla de cobranzas

  Una semana después trae los 400:
     · La venta #142 queda pagada
     · El iPhone 15 pasa de "Venta a crédito" a "Vendido"
     · La comisión de Carlos por esa venta se libera para pago
```

```
Un cliente vuelve por garantía

  1. Se lo busca por CI
  2. En su ficha aparece el iPhone 15 con garantía hasta el 24/12
  3. Se manda el equipo al taller como reparación por garantía,
     sin cobrarle nada
```

---

## Qué NO incluye

- **No hay plan de cuotas.** La venta a crédito se cobra a cuenta, con pagos libres cuando el cliente puede. No hay cuotas con fechas, ni intereses, ni recargo por mora. *(Pendiente de confirmar: ver [preguntas abiertas](11-preguntas-abiertas.md).)*
- **No hay aviso automático al cliente.** El sistema no manda recordatorios por WhatsApp ni por mensaje; muestra la lista para que alguien la trabaje.
- **No hay límite de crédito ni bloqueo.** El sistema no impide venderle a crédito a alguien que ya debe; avisa mostrando su deuda.
- **No hay estado de cuenta impreso** por cliente.
- **No se importan clientes** de ningún sistema anterior.
- **Los datos del cliente no se validan contra ningún registro oficial.**

---

## Entregables

1. Módulo de clientes: alta, edición, búsqueda por nombre y CI.
2. Ficha del cliente con compras, reservas, deuda, pagos y garantías.
3. Venta a crédito: saldo pendiente atado al cliente y al equipo.
4. Registro de cobros con método de pago, con saldo que se va descontando.
5. Pantalla de cobranzas con lo pendiente, filtros y totales.
6. Cierre automático de la venta al llegar a saldo cero, con liberación de la comisión.
7. Reemplazo del cliente-texto por el cliente-ficha en las dos pantallas de venta.
