# 6. Compras

## Para qué sirve

Meter mercadería al sistema: los equipos que se compran para revender y los repuestos y accesorios que consume el taller y el mostrador.

---

## Cómo funciona hoy

Hay dos pantallas:

**Compra de productos.** Se registra la fecha, el proveedor y los equipos que llegaron. Cada equipo se carga con su IMEI, su modelo y su costo, y queda en Inventario listo para venderse. La compra guarda el costo total y la cantidad.

Existe además la **carga por lote**: cuando llegan veinte equipos del mismo modelo, se cargan de corrido sin repetir los datos comunes, y después se editan uno por uno los que tengan algo distinto.

**Compra de repuestos.** Se registran las piezas y accesorios que llegaron con su cantidad y su costo, y el stock sube automáticamente.

Las dos se cargan hoy en dólares, con un tipo de cambio por compra.

No hay estado de compra, no hay forma de reclamar al proveedor, y no hay registro de lo que se le debe: toda compra se asume pagada.

---

## Qué cambia

### Todo en bolivianos

La compra se registra en Bs, sin tipo de cambio de por medio, igual que el resto del inventario.

### Estado de la compra

Cada compra pasa a tener un estado, para saber en qué anda:

| Estado | Qué significa |
|---|---|
| **Recibida** | Llegó y está todo bien |
| **Con reclamo** | Llegó con equipos fallados, se le reclamó al proveedor |
| **Resuelta** | El proveedor repuso o descontó, y el tema está cerrado |

### Reclamos al proveedor

Cuando llega mercadería fallada:

1. Se abre la compra y se **marcan los equipos que están mal**, con el motivo de cada uno.
2. Esos equipos **salen del inventario vendible**: quedan "en reclamo" y no se pueden vender ni mandar a reparar, para que nadie venda algo que se va a devolver.
3. Queda registrado el reclamo contra el proveedor, con fecha.
4. Cuando el proveedor repone, se **registra el equipo de reemplazo** —con su IMEI nuevo— y el fallado se cierra.

```
Compra #12 — Proveedor Importaciones del Sur
Estado: Con reclamo

  20 equipos recibidos
     18 en Inventario
      2 EN RECLAMO:
        IMEI ...4522   "No enciende"
        IMEI ...9910   "Pantalla con manchas"

  Al reponer el proveedor:
     entra IMEI ...7781 como reemplazo
     los dos fallados quedan cerrados
     la compra pasa a Resuelta
```

Desde la ficha del proveedor se ve qué reclamos hay abiertos con él.

> **Falta definir**: si el reemplazo entra como parte de la misma compra (sin cambiar el costo total) o si se carga como una compra aparte.

### Cuentas por pagar

Una compra puede quedar **a crédito**: se registra cuánto se pagó al recibirla y cuánto queda debiendo al proveedor.

Después se van registrando los pagos, con su fecha y su método, hasta saldar:

```
Compra #12                        48.000,00 Bs
  Pagado al recibir               20.000,00
                                  ----------
  Saldo                           28.000,00

  05/10  Transferencia            15.000,00  -> saldo 13.000,00
  20/10  Efectivo                 13.000,00  -> PAGADA
```

Y una pantalla de **cuentas por pagar** que muestra todo lo que se le debe a cada proveedor, ordenado por antigüedad.

Es el mismo mecanismo que las cobranzas de clientes, pero del otro lado del mostrador.

### El escáner al comprar

Al cargar los equipos de una compra se puede **escanear el código de barras** en lugar de teclearlo, con la cámara del celular o con la pistola USB. Vale igual para repuestos y accesorios.

---

## Cómo queda el trabajo diario

```
Llega una compra de 20 equipos

  1. Se registra la compra: proveedor, fecha, 48.000 Bs
  2. Se cargan los equipos por lote, escaneando IMEI y código de barras
  3. Se paga 20.000 al recibir  -> quedan 28.000 en cuentas por pagar
  4. Al revisar, dos equipos vienen fallados:
       se los marca en reclamo y salen del inventario vendible
       la compra queda "Con reclamo"
  5. Se le avisa al proveedor
  6. Dos semanas después manda un reemplazo:
       se carga el equipo nuevo, se cierra uno de los reclamos
  7. Los pagos se van registrando hasta saldar la compra
```

---

## Qué NO incluye

- **No hay orden de compra.** El sistema registra lo que ya llegó, no lo que se pidió.
- **No hay recepción parcial.** La compra se carga con lo que llegó; si llega el resto después, es otra compra.
- **No hay costos de importación repartidos.** No calcula flete, aduana o impuestos prorrateados entre los equipos; el costo se carga ya armado.
- **No hay comparación de precios entre proveedores.**
- **El reclamo no genera documento** para mandar al proveedor; queda como registro interno.
- **No hay devolución con nota de crédito**: el reclamo se cierra con reposición, no con dinero. *(Pendiente de confirmar.)*
- **No se importan compras** anteriores.

---

## Entregables

1. Compras en bolivianos, quitando el tipo de cambio de la carga.
2. Estado de la compra (Recibida, Con reclamo, Resuelta).
3. Marcado de equipos fallados con motivo, y su salida del inventario vendible.
4. Registro del reemplazo del proveedor y cierre del reclamo.
5. Reclamos abiertos visibles en la ficha del proveedor.
6. Compra a crédito: saldo pendiente con el proveedor.
7. Registro de pagos a proveedores, con método y fecha.
8. Pantalla de cuentas por pagar, con totales por proveedor y antigüedad.
9. Escáner de código de barras en la carga de compras.
