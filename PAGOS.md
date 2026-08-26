# Cobro con Square — puesta en marcha

Documento interno. **No va en el ZIP** que se sube a Hostinger.

---

## Qué se agregó

Tres archivos en `pago/`:

| Archivo | Qué hace |
|---|---|
| `crear.php` | Recibe el pedido, recalcula los precios, le pide a Square el enlace de cobro y lo devuelve |
| `config.php` | Guarda el token. **Es el único que hay que editar** |
| `.htaccess` | Impide que el servidor muestre `config.php` como texto si PHP llegara a apagarse |

El botón **Confirmar pedido** del checkout ahora llama a `crear.php` y manda al comprador a la página de pago de Square.

---

## Antes de empezar

**1. Rotar el token de producción.** El que mandó Square por mail pasó por cuatro casillas en texto plano. Panel de Square → *Developer → Applications → Credentials → Rotate*.

**2. Confirmar que el plan de Hostinger ejecuta PHP.** De Premium para arriba viene incluido. Si el plan es el más básico, esto no funciona y hay que subir de plan.

---

## Pasos

### 1. Subir los archivos

Que en el servidor quede:

```
public_html/index.html
public_html/assets/...
public_html/pago/crear.php
public_html/pago/config.php
public_html/pago/.htaccess
```

Ojo: `.htaccess` empieza con punto, así que el Administrador de archivos de Hostinger puede ocultarlo. Hay que activar *Mostrar archivos ocultos*.

### 2. Pegar el token

Editar `public_html/pago/config.php` **en el servidor** — no en la computadora, no por mail, no por chat.

Hay dos líneas que tocar:

```php
const AMBIENTE = 'sandbox';   // o 'produccion'
const TOKEN = '';             // pegar acá, entre las comillas
```

El token de sandbox y el de producción son distintos. Tiene que corresponder al ambiente elegido.

### 3. Probar en sandbox primero

Con `AMBIENTE = 'sandbox'`, hacer una compra completa en el sitio. No mueve plata real.

Square da tarjetas de prueba en su documentación; la habitual es **4111 1111 1111 1111**, cualquier fecha futura y cualquier CVV.

Si el pedido llega al panel de Square en modo sandbox, funciona.

### 4. Pasar a producción

Cambiar `AMBIENTE` a `'produccion'` y reemplazar el token por el de producción. Hacer **una compra real de prueba** con una tarjeta propia, verificar que entra, y devolverla desde el panel.

---

## Si algo falla

El sitio muestra el mensaje de error tal como lo devuelve Square, así que suele decir qué corregir.

| Mensaje | Causa |
|---|---|
| *Falta cargar el token* | `config.php` sigue con las comillas vacías |
| *El cobro no está disponible en esta dirección* | Se está probando desde Vercel, que no ejecuta PHP. Solo funciona en `vaderetro.org` |
| Error que menciona `version` | Cambiar `SQUARE_VERSION` en `config.php` por la fecha que indique el panel de Square |
| *No se pudo contactar a Square* | El hosting bloquea salidas HTTPS. Hay que pedirle a Hostinger que las habilite |

---

## Importante para el mantenimiento

**El envío se cobra una sola vez por pedido**: el de la pieza más cara del carrito. Va todo en un paquete, así que se cobra un envío, no uno por unidad.

**Los precios y esa regla están en dos lugares** y tienen que coincidir:

- `index.html` → `Component.DATA` (lo que ve el comprador)
- `pago/crear.php` → `$CATALOGO` (lo que se cobra)

Están duplicados a propósito: si el servidor confiara en el precio que manda el navegador, cualquiera podría editarlo y pagar un dólar. **Si cambia un precio, hay que cambiarlo en los dos.**

---

## Lo que esto no incluye

- **Control de stock.** Dos personas pueden comprar la misma pieza única.
- **Panel de pedidos propio.** Los pedidos se ven en Square, no en el sitio.
- **Aviso por mail a la clienta.** Lo cubre la notificación de Square.
