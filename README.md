# VADE RETRO — Tienda de medallas de San Benito

Prototipo de diseño navegable en HTML (Design Component). Bilingüe ES/EN, precios en USD, carrito persistente y link de pago externo configurable.

## Archivos

| Archivo | Qué es |
|---|---|
| `VADE RETRO Store.dc.html` | La tienda completa: home, tienda, nuestra historia, cómo comprar, FAQ, producto, carrito, checkout y confirmación. Abre directo en el navegador. |
| `assets/logo.png` | Logo maestro con fondo transparente (derivado del PNG original de la cliente). |
| `assets/medalla-*.webp` | Fotos de producto provistas por la cliente. |
| `image-slot.js` | Componente de slot de imagen: se puede arrastrar una foto encima para reemplazarla. |
| `dither.js` | Textura de dithering (tickets de "Cómo comprar"). |
| `velaris.js` | Fondo animado por shader (queda disponible, hoy sin uso). |
| `github.md` | Asociación con el repositorio del proyecto. |

## Correr localmente

No requiere build ni dependencias. Servir la carpeta con cualquier servidor estático:

```
npx serve .
```

y abrir `VADE RETRO Store.dc.html`. (Abrir el archivo con doble clic también funciona, pero un servidor evita restricciones del navegador con archivos locales.)

## Configuración de la tienda

Los valores editables están declarados como props del componente (panel de Tweaks en el editor) y se leen en la clase de lógica:

| Prop | Default | Qué hace |
|---|---|---|
| `paymentLink` | `""` | URL del link externo de pago. |
| `paymentLinkEnabled` | `false` | Habilita el botón "Ir al pago". Mientras esté en `false`, la confirmación muestra el aviso de link pendiente — nunca un link falso ni `#`. |
| `heroStyle` | `Azul profundo` | Variante de hero (`Azul profundo` / `Marfil`). |
| `defaultLang` | `es` | Idioma inicial (`es` / `en`). |

El link de pago **no está quemado en el código**: sale de estas props y, en el build final, debe salir de `vaderetro.store_settings`.

## Catálogo demo

Los seis productos demo viven en `Component.DATA` dentro de la clase de lógica (slug, precio, precio anterior, stock, material, categoría, insignia, nombre y descripciones ES/EN). No son datos definitivos: la cliente todavía debe entregar catálogo, fotos, precios, descripciones y textos.

En el build final los productos deben venir de Supabase, no de este archivo.

## Reglas de negocio ya implementadas

- Precios siempre en USD, formato `$29.99`.
- No se puede comprar un producto sin stock (sin botón de compra, cartel de agotado).
- La cantidad en el carrito nunca supera el stock disponible.
- El carrito persiste en `localStorage` (`vaderetro_cart`).
- El pedido se guarda en `localStorage` (`vaderetro_orders`) con número correlativo `VADE-000001`.
- El pedido nace con `status: pending` y `payment_status: pending`. Abrir el link de pago **no** lo marca como pagado.
- Validación de campos obligatorios y de formato de email en el checkout.

## Pendiente de la cliente

Textos definitivos, catálogo, fotografías, precios, descripciones y link externo de pago.

Cualquier consulta sobre este cliente se coordina con **PATRICIO**.
