# 5. Comisiones

> **Módulo nuevo.** Hoy no existe nada de esto en el sistema.

## Qué problema resuelve

Hoy no hay forma de saber cuánto le corresponde a cada vendedor ni a cada técnico. Se lleva aparte, en papel o en una planilla, con el riesgo de siempre: pagar dos veces, olvidarse de pagar, o discutir sobre cuánto era.

Este módulo calcula las comisiones solo, las acumula, y deja registrado qué se pagó, cuándo y por qué ventas.

---

## Qué se construye

Dos cálculos automáticos —uno para vendedores y otro para técnicos—, una vista por persona de lo que tiene ganado, y una pantalla para liquidar y dejar registrado el pago.

---

## Comisión del vendedor

### Cómo se calcula

Cada usuario tiene su **porcentaje** en su ficha. La comisión se calcula **sobre la ganancia** de la venta, no sobre el monto vendido:

```
Venta #142
  Precio de venta          4.500 Bs
  Costo del equipo         3.600 Bs
                           --------
  Ganancia                   900 Bs
  Comisión de Carlos (3%)     27 Bs
```

Se calcula sobre la ganancia para que vender barato deje de convenir: si fuera sobre el total, rebajar el precio no le costaría nada al vendedor y sí al negocio.

### Cuándo se gana

La comisión se genera con la venta, pero **queda pendiente hasta que la venta esté cobrada por completo**:

```
Venta a crédito de 4.500 Bs
  Cobro 1   2.000 Bs   -> comisión: pendiente
  Cobro 2   1.500 Bs   -> comisión: pendiente
  Cobro 3   1.000 Bs   -> venta pagada
                          comisión: GANADA, lista para liquidar
```

Así no se paga comisión por dinero que todavía no entró.

---

## Comisión del técnico

Por cada reparación, el técnico gana el **50% de la mano de obra cobrada**. Los repuestos los pone el negocio y no entran en el reparto:

```
Reparación de un iPhone 12
  Repuestos usados      120 Bs   (del negocio)
  Mano de obra cobrada  200 Bs
                        -------
  Técnico               100 Bs
  Negocio               100 Bs
```

El 50% queda cargado por defecto en la ficha del técnico y se puede cambiar si con alguien se arregla distinto.

---

## La liquidación

Una liquidación es el acto de pagar. Se elige a la persona y el período, el sistema muestra todo lo que tiene ganado y sin pagar, y al confirmar queda registrado el pago con su fecha y su detalle.

```
LIQUIDACIÓN — Carlos Ruiz
Del 01/09/2026 al 30/09/2026

  Venta   Fecha       Cliente         Ganancia   Comisión
  #142    24/09/26    Juan Pérez        900,00      27,00
  #139    19/09/26    María Quispe      640,00      19,20
  #135    11/09/26    Pedro Rojas     1.200,00      36,00
                                                  --------
  TOTAL A PAGAR                                     82,20 Bs

  [ Registrar pago ]
```

Después de confirmar, esas comisiones quedan marcadas como pagadas y no vuelven a aparecer en la siguiente liquidación.

Cada persona tiene siempre tres números a la vista:

| | |
|---|---|
| **Pendiente** | De ventas todavía no cobradas del todo |
| **Por pagar** | Ya ganado, esperando liquidación |
| **Pagado** | Lo que ya se le entregó, con el detalle |

---

## Cómo queda el trabajo diario

```
Fin de mes

  1. Se abre Comisiones y se elige el mes
  2. Sale la lista de vendedores y técnicos con lo que hay que pagarle a cada uno
        Carlos Ruiz (vendedor)     82,20 Bs
        Ana Flores  (vendedora)   145,60 Bs
        Luis Mamani (técnico)     450,00 Bs
  3. Se revisa el detalle de cada uno: qué ventas, qué reparaciones
  4. Se paga y se confirma en el sistema
  5. Queda el registro: quién cobró, cuánto, cuándo y por qué ventas
```

---

## Qué NO incluye

- **No es planilla de sueldos.** Solo comisiones; sueldos, bonos y descuentos se manejan fuera.
- **No calcula impuestos ni aportes** sobre lo pagado.
- **No genera recibo de pago impreso.** Queda el registro en el sistema.
- **No hay comisión por cobranza**, ni por meta cumplida, ni escalas por volumen. Es un porcentaje plano por persona.
- **No hay comisión sobre repuestos y accesorios**, salvo que se decida lo contrario: hoy se define sobre la venta de equipos.
- **Una comisión pagada no se revierte sola.** Si después se anula esa venta, hay que ajustarlo a mano.
- **No hay adelantos** a cuenta de comisiones futuras.

---

## Entregables

1. Cálculo automático de la comisión del vendedor en cada venta, sobre la ganancia.
2. Comisión pendiente hasta el cobro total de la venta, y liberación automática al saldar.
3. Cálculo de la comisión del técnico sobre la mano de obra de cada reparación.
4. Vista de comisiones por persona: pendiente, por pagar y pagado, con su detalle.
5. Pantalla de liquidación por persona y período, con registro del pago.
6. Reporte de comisiones del período, para todos.
