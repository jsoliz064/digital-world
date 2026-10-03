# 11. Preguntas abiertas

Lo que falta decidir antes de poder cotizar con precisión. Cada una está en el documento marcada como "falta definir"; acá están todas juntas para contestarlas de una sentada.

Las que están marcadas con **⚠** cambian el precio o el plazo de manera sensible.

---

## Inventario

**1. ¿Qué describe cada grado?**
Se acordó A+, 1, 2, 3. Falta la definición de cada uno (rayas, golpes, batería, si fue abierto) para que todos carguen igual y el cliente entienda qué compra.

**2. ⚠ En repuestos, "código" y "costo": ¿son lo mismo o dos datos distintos?**
`notes.md` dice que el costo pase a llamarse código *solo a nivel de front*. Eso se documentó como cambio de rótulo. Pero si el negocio necesita **guardar el costo real y además un código interno**, son dos campos distintos y cambian los reportes de ganancia de repuestos.

---

## Ventas

**3. ⚠ La reserva, ¿vence?**
Si un cliente aparta un equipo y no vuelve: ¿a los cuántos días se libera? ¿La seña se le devuelve, se pierde, o le queda a favor para otra compra?

**4. ⚠ La venta a crédito, ¿tiene plan de cuotas?**
Se documentó como cobro a cuenta libre: el cliente paga cuando puede y el saldo baja. Si hace falta **cuotas con fechas, intereses o recargo por mora**, es bastante más trabajo.

**5. ¿Qué métodos de pago van a estar al arrancar?**
Efectivo, QR, transferencia, tarjeta. ¿Alguno más? ¿Los administra el cliente desde el sistema o quedan fijos?

**6. La nota de venta, ¿qué debe llevar?**
¿Datos fiscales, NIT, alguna leyenda legal? ¿Condiciones de garantía impresas? Hace falta el **logo definitivo** en buena calidad.

**7. En una venta con permuta, ¿la comisión se calcula sobre qué?**
Si se vende en 7.000 y se reciben 2.500 en equipo, ¿la ganancia del vendedor sale sobre los 7.000 o sobre los 4.500 cobrados?

---

## Clientes y cobranzas

**8. ¿Se puede vender a crédito a alguien que ya debe?**
Se documentó que el sistema avisa pero no bloquea. ¿Debería bloquear a partir de cierto monto o cierta antigüedad?

**9. ¿Quién puede registrar un cobro?**
¿Cualquier vendedor, o solo un encargado?

---

## Comisiones

**10. ¿Quién autoriza y marca el pago de una liquidación?**
¿Un permiso aparte para eso, o lo hace cualquiera con acceso al módulo?

**11. ¿Hay comisión por vender repuestos y accesorios?**
Se documentó la comisión sobre la venta de equipos. ¿La venta de accesorios también comisiona?

**12. En una reparación por garantía, donde no se le cobra al cliente, ¿el técnico igual cobra su 50%?**
Si no se cobró mano de obra, no hay de dónde sacar el 50%. Hay que decidir si el negocio lo cubre igual o si esa reparación no comisiona.

---

## Compras

**13. El reemplazo que manda el proveedor, ¿cómo entra?**
¿Como un equipo más de la misma compra, sin cambiar el costo total? ¿O como una compra aparte?

**14. ¿El reclamo puede cerrarse con dinero en vez de con un equipo?**
Si el proveedor descuenta en lugar de reponer, ¿hay que registrar esa nota de crédito y bajar el costo de la compra?

---

## Operación

**15. ⚠ ¿Cuántas sucursales al arrancar?**
Y sobre todo: ¿un usuario ve **solo su sucursal** o ve todas? Hoy el sistema deja ver todo. Si hace falta que cada local vea solo lo suyo, eso toca todas las pantallas y todos los reportes.

**15b. ¿Qué datos necesita una sucursal además del nombre?**
Se propuso dirección y teléfono, para que salgan en la nota de venta impresa. ¿Hace falta algo más: encargado, horario, un código corto para numerar las ventas por local?

**16. ¿Qué necesita ver el reporte por cliente?**
Se propuso: los que más compran, deuda y clientes nuevos. ¿Sirve así o falta algo puntual?

**17. ¿Quién carga los datos iniciales?**
Sucursales, usuarios, técnicos, proveedores, métodos de pago y catálogos. ¿Lo hace el cliente o entra en el trabajo?
