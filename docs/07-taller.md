# 7. Taller y reparaciones

## Para qué sirve

Llevar los equipos que necesitan arreglo: quién los repara, qué repuestos se usaron, cuánto costó y cuánto se cobró.

---

## Cómo funciona hoy

Un equipo del inventario se manda a reparación y pasa al estado **Reparación**. La reparación registra:

- **Quién la hace**: el técnico asignado.
- **Qué se le puso**: los repuestos usados, que **se descuentan del stock** en ese momento.
- **Cuánto costó**: el costo de los repuestos más lo que cobra el técnico.
- **Qué tipo es**:

| Tipo | Cuándo |
|---|---|
| **Normal** | Un equipo del inventario que hay que arreglar antes de venderlo |
| **Garantía** | Un equipo ya vendido que vuelve dentro del plazo de garantía |
| **Externo** | El equipo de un cliente que trae a arreglar, no es del negocio |

- **Fechas** de entrega al técnico y de recogida.
- Si los repuestos los puso el técnico o el negocio, y cuáles hay que devolverle.
- Si ya se le pagó al técnico.
- Si el trabajo tiene garantía del técnico.

Cuando la reparación termina, **su costo se suma al costo del equipo**, así que la ganancia de la venta ya lo tiene descontado.

Hay un detalle importante ya resuelto: cuando un equipo reparado se vende, se pueden **cobrar al cliente los repuestos que se le montaron**, sin descontar stock otra vez (ya se descontó al repararlo).

Las **garantías** se dan en la venta: se eligen los meses y el sistema calcula hasta cuándo vale. Un equipo con garantía vigente que vuelve se atiende como reparación por garantía.

---

## Qué cambia

### La comisión del técnico

Es el único cambio de fondo del módulo. Por cada reparación, el técnico gana el **50% de la mano de obra cobrada**:

```
Reparación
  Repuestos usados      120 Bs   (del negocio, no entran al reparto)
  Mano de obra cobrada  200 Bs
                        -------
  Técnico               100 Bs
  Negocio               100 Bs
```

Eso se acumula en su ficha y se paga por liquidación. Ver [Comisiones](05-comisiones.md).

Así quedó: la comisión se gana al terminar la reparación, con el % de la ficha del técnico sobre la mano de obra. Reemplaza al viejo «Pagado» de la reparación, que pagaba la mano de obra entera sin dejar registro. Un técnico con reparaciones no se elimina.

### Todo en bolivianos

Los costos de reparación se cargan y se muestran en Bs.

### Se retira el enlace público del técnico

Hoy cada técnico tiene una dirección web con un código, que le permite ver sus equipos sin entrar al sistema. Se retira. Quien necesite verlos entra con su usuario.

---

## Cómo queda el trabajo diario

```
Un equipo entra al taller

  1. Del inventario, se manda a reparación
       Equipo    iPhone 12 · IMEI ...3301
       Técnico   Luis Mamani
       Falla     "No carga"
     El equipo pasa a Reparación y sale del stock vendible

  2. Luis cambia el pin de carga
       Repuesto  Pin de carga iPhone 12   -> baja del stock,   120 Bs
       Mano obra                                               200 Bs

  3. Se cierra la reparación
       Costo de la reparación                                  320 Bs
       Se suma al costo del equipo:  2.400 -> 2.720 Bs
       El equipo vuelve a Inventario

       Comisión de Luis: 100 Bs  (50% de la mano de obra)
```

```
Un equipo vuelve por garantía

  1. Se busca al cliente, se ve que la garantía está vigente
  2. Entra como reparación por garantía, sin cobrarle nada
  3. Los repuestos que se usen se descuentan igual del stock
     y quedan como costo del negocio
```

---

## Qué NO incluye

- **No hay orden de trabajo impresa** ni comprobante de recepción para el cliente.
- **No hay aviso al cliente** de que su equipo está listo.
- **No hay control de tiempos**: no mide cuánto tardó cada reparación ni avisa de atrasos.
- **No hay repuestos reservados** para una reparación pendiente: se descuentan cuando se usan.
- **No hay diagnóstico previo con presupuesto** que el cliente apruebe antes de reparar.
- **La reparación por garantía no comisiona** (decisión del usuario: no se le cobró nada al cliente). La normal y la externa sí.

---

## Entregables

1. Comisión del técnico: 50% de la mano de obra, calculada en cada reparación.
2. Costos del taller en bolivianos.
3. Retiro del enlace público del técnico.
4. Vista de reparaciones y comisiones en la ficha del técnico.
