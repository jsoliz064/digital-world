# 10. Fuera de alcance

Este apartado dice, de frente, qué **no** va a estar. Es tan importante como el resto: lo que no está escrito acá ni en los demás documentos, no está incluido.

---

## Lo que se retira del sistema actual

### Bot de WhatsApp

Hoy el sistema manda un mensaje al administrador por WhatsApp cada vez que se registra una venta, una compra o una reparación. Para funcionar necesita un servicio aparte corriendo y escanear un código QR cada tanto.

**Se retira.** Con él se va la pantalla de "Bot QR" del menú.

### Enlace público del técnico

Hoy cada técnico tiene una dirección web con un código que le deja ver sus equipos asignados sin entrar al sistema.

**Se retira.** Quien necesite verlos, entra con su usuario.

### Estado "Tránsito"

El estado que marcaba un equipo viajando entre locales. **Se retira** de la lista de estados.

---

## Lo que no incluye el proyecto

### Datos

- **No hay migración de datos.** El sistema arranca vacío. No se importa inventario, ventas, clientes ni compras de ningún sistema anterior, ni de planillas.
- Se cargan solo los **catálogos base**: marcas, modelos y categorías más comunes.

### Facturación y contabilidad

- **No emite factura fiscal** ni se conecta con el servicio de impuestos.
- **No hay contabilidad**: ni libro diario, ni balance, ni estado de resultados.
- La nota de venta impresa es un **comprobante interno**.

### Caja

- **No hay apertura ni cierre de caja**, ni arqueo, ni control de efectivo en el cajón. El sistema registra con qué método se cobró cada venta, pero no lleva la caja.

### Aplicación móvil

- **No hay app para instalar.** El sistema se usa desde el navegador, y funciona en el celular. El lector de código de barras usa la cámara **desde el navegador**, no una app aparte.

### Integraciones

- **No se conecta** con tienda en línea, marketplace, pasarela de pagos, banco ni sistema de mensajería.
- **No hay envío automático** de avisos a clientes: ni recordatorios de deuda, ni avisos de equipo listo.

### Otros

- **No hay control de horarios, asistencia ni sueldos.** Solo comisiones.
- **No hay conteo físico de inventario** con ajuste de diferencias.
- **No hay órdenes de compra**: se registra lo que llegó.
- **No hay devoluciones ni cambios** de venta: se cancela y se vuelve a cargar.
- **No hay múltiples monedas más allá de lo descrito**: el sistema trabaja en bolivianos y solo la venta puede marcarse como cobrada en dólares.

---

## Requisitos de la instalación

No son trabajo de programación, pero sin ellos algo de lo pedido no funciona:

| | Por qué |
|---|---|
| **Servidor con HTTPS** (certificado) | Los navegadores **no dan acceso a la cámara** en sitios sin certificado. Sin esto, el lector de código de barras por celular no funciona |
| **Impresora térmica de 80mm** | Para la nota de venta |
| **Pistola lectora USB** (opcional) | Para escanear desde el mostrador |
| **Dominio propio** | Para el catálogo público y para acceder desde los celulares |

---

## Supuestos

Sobre estos supuestos se arma el alcance. Si alguno no es cierto, cambia el trabajo:

1. El negocio arranca de cero en el sistema: no hay datos históricos que traer.
2. Se trabaja con **un solo sistema**, no con varios locales con bases separadas.
3. Los precios se cargan a mano; no se traen de ninguna lista externa.
4. El personal ya sabe usar una computadora y un navegador.
5. La capacitación se acuerda aparte y no está incluida en las horas de programación.
