# Documentación del sistema — negocio nuevo

Este juego de documentos describe **qué hace hoy el sistema, qué va a cambiar y cómo queda el trabajo diario** en el negocio nuevo. Está escrito para leerlo con el cliente y confirmarlo punto por punto, antes de programar nada.

Está basado en el sistema que hoy funciona en importadora YRB. El negocio nuevo parte de una copia de ese sistema, con la base de datos vacía y los cambios que se detallan aquí.

## Cómo leer esto

Empiece por el **resumen**. Ahí está, en dos páginas, qué módulos se mantienen, cuáles cambian, cuáles son nuevos y cuáles se retiran, más el cuadro de decisiones ya tomadas para que las confirme o las corrija.

Después, un archivo por módulo. Todos están armados igual:

1. **Para qué sirve**
2. **Cómo funciona hoy**
3. **Qué cambia**
4. **Cómo queda el trabajo diario** — el caso contado con números, que es lo que conviene leer en voz alta
5. **Qué NO incluye** — los límites, dichos de frente
6. **Entregables** — las piezas que se van a construir

| Archivo | Contenido |
|---|---|
| [00-resumen.md](00-resumen.md) | El sistema en dos páginas y las decisiones tomadas |
| [01-administracion.md](01-administracion.md) | Usuarios, roles, proveedores, técnicos, sucursales |
| [02-inventario.md](02-inventario.md) | Productos, repuestos y accesorios, catálogos |
| [03-ventas.md](03-ventas.md) | Venta, accesorios de regalo, permuta, reserva, cobros, nota impresa |
| [04-clientes-y-cobranzas.md](04-clientes-y-cobranzas.md) | Módulo nuevo: clientes, ventas a crédito y cobros |
| [05-comisiones.md](05-comisiones.md) | Módulo nuevo: comisiones de vendedores y técnicos |
| [06-compras.md](06-compras.md) | Compras, reclamos al proveedor, cuentas por pagar |
| [07-taller.md](07-taller.md) | Reparaciones, garantías, trabajo externo |
| [08-catalogo.md](08-catalogo.md) | Catálogo público |
| [09-reportes.md](09-reportes.md) | Reportes actuales y los nuevos |
| [10-fuera-de-alcance.md](10-fuera-de-alcance.md) | Lo que se retira y lo que no está incluido |
| [11-preguntas-abiertas.md](11-preguntas-abiertas.md) | Lo que falta decidir antes de cotizar |

## Dónde quedó cada pedido

Cada línea del pedido original (`notes.md`) tiene su lugar en la documentación. Esta tabla es para verificar que no se perdió nada:

| Pedido | Dónde está |
|---|---|
| Usuarios: % de comisión por ventas | [01](01-administracion.md#usuarios) · [05](05-comisiones.md) |
| Roles: se mantiene | [01](01-administracion.md#roles) |
| Técnicos: comisión 50% y 50% | [01](01-administracion.md#técnicos) · [05](05-comisiones.md) · [07](07-taller.md) |
| Proveedores: se mantiene | [01](01-administracion.md#proveedores) |
| Marcas, categorías y modelos: se mantienen | [02](02-inventario.md#los-catálogos) |
| Categorías de repuestos y accesorios: se mantiene | [02](02-inventario.md#los-catálogos) |
| Productos: costo de envío → accesorios de regalo, con detalle | [02](02-inventario.md#accesorios-de-regalo) · [03](03-ventas.md) |
| Condición: grado A+, 1, 2, 3 | [02](02-inventario.md#el-grado-del-equipo) |
| Tipo de venta: venta, oferta, venta externa | [02](02-inventario.md#tipo-de-venta) |
| Estados: quitar tránsito, agregar crédito y reserva | [02](02-inventario.md#los-estados-del-equipo) · [03](03-ventas.md) |
| Todo en bolivianos | [00](00-resumen.md#la-moneda) · [02](02-inventario.md) |
| UPC y lector de código de barras | [02](02-inventario.md#código-de-barras-upc) |
| Repuestos: en Bs, costo → código, UPC | [02](02-inventario.md#repuestos-y-accesorios) |
| Venta: accesorios asociados al teléfono | [03](03-ventas.md#accesorios-que-se-venden-con-el-equipo) |
| Dar de baja productos y accesorios | [02](02-inventario.md#dar-de-baja) |
| Venta por permuta | [03](03-ventas.md#permuta) |
| Imprimir nota de venta | [03](03-ventas.md#la-nota-de-venta) |
| Comisión por ventas, registro y pago | [05](05-comisiones.md) |
| Venta a crédito, pagos y deudas por cliente | [04](04-clientes-y-cobranzas.md) |
| Métodos de pago y pago múltiple | [03](03-ventas.md#el-cobro) · [04](04-clientes-y-cobranzas.md) |
| Escanear UPC al vender | [03](03-ventas.md#el-escáner-en-la-venta) |
| Compras: estado y reclamo al proveedor | [06](06-compras.md#reclamos-al-proveedor) |
| Cuentas por pagar y compras a crédito | [06](06-compras.md#cuentas-por-pagar) |
| Escáner al comprar | [06](06-compras.md) |
| Catálogo público: se mantiene | [08](08-catalogo.md) |
| Reportes: se mantiene | [09](09-reportes.md) |
| Reportes por vendedores | [09](09-reportes.md#por-vendedor) |
| Reportes de productos | [09](09-reportes.md#de-productos) |
| Reportes de inventario | [09](09-reportes.md#de-inventario) |
| Reportes de clientes | [09](09-reportes.md#por-cliente) |

## Qué sigue

Una vez revisado y confirmado este documento, se arma la **propuesta de alcance, tiempo y presupuesto**. Las listas de "Entregables" de cada módulo pasan a ser las partidas que se cotizan, y los "Qué NO incluye" pasan a ser las exclusiones del contrato.
