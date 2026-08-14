# VADE RETRO — Especificación para desarrollo

Este documento acompaña al prototipo `VADE RETRO Store.dc.html`. El prototipo define diseño, copy y comportamiento; esto define la implementación.

Stack acordado: **Next.js + TypeScript + TailwindCSS + Supabase**.

## Identidad visual (Manual de Marca v1.0)

| Color | Hex |
|---|---|
| Azul Protector | `#0F2349` |
| Dorado Sagrado | `#CF9D59` |
| Marfil Sereno | `#F9F0ED` |
| Bordó Devocional | `#571E0B` |
| Carbón | `#1C1C24` |

Tipografía: **Cormorant Garamond** (títulos y mensajes de marca), **Montserrat** (textos informativos y digitales). No usar otras familias.

Logo: composición cerrada. No reconstruir con tipografías, no separar cadena/argolla/dije, no recolorear, no rotar ni deformar. Mínimo digital **240 px de ancho**; sobre fondos oscuros va siempre sobre un campo Marfil. En el prototipo el logo del header está a 186 px para permitir un header bajo: para cumplir el manual hace falta pedir a la cliente un **lockup horizontal aprobado**.

Frase oficial: *VADE RETRO, PROTEGIENDO TU FAMILIA Y TU HOGAR CONTRA DEMONIOS.*

## Variables de entorno (`.env.example`)

```
NEXT_PUBLIC_SUPABASE_URL=
NEXT_PUBLIC_SUPABASE_ANON_KEY=
SUPABASE_SERVICE_ROLE_KEY=
```

El link de pago NO va en el `.env`: se administra en `vaderetro.store_settings`.

## Base de datos — schema `vaderetro`

```sql
create schema if not exists vaderetro;
create extension if not exists pgcrypto;

-- CATEGORÍAS ---------------------------------------------------------------
create table vaderetro.categories (
  id          uuid primary key default gen_random_uuid(),
  name        text not null,
  slug        text not null unique,
  description text,
  image_url   text,
  is_active   boolean not null default true,
  sort_order  integer not null default 0,
  created_at  timestamptz not null default now(),
  updated_at  timestamptz not null default now()
);

-- PRODUCTOS ----------------------------------------------------------------
create table vaderetro.products (
  id          uuid primary key default gen_random_uuid(),
  category_id uuid not null references vaderetro.categories(id),
  name        text not null,
  slug        text not null unique,
  description text,
  price       numeric(14,2) not null,
  stock       integer not null default 0,
  image_url   text,
  is_featured boolean not null default false,
  is_active   boolean not null default true,
  sort_order  integer not null default 0,
  created_at  timestamptz not null default now(),
  updated_at  timestamptz not null default now(),
  constraint products_price_nonneg check (price >= 0),
  constraint products_stock_nonneg check (stock >= 0)
);

-- CLIENTES -----------------------------------------------------------------
create table vaderetro.customers (
  id         uuid primary key default gen_random_uuid(),
  name       text not null,
  phone      text not null,
  email      text,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);
create index customers_phone_idx on vaderetro.customers (phone);
create index customers_email_idx on vaderetro.customers (email);

-- PEDIDOS ------------------------------------------------------------------
create table vaderetro.orders (
  id             uuid primary key default gen_random_uuid(),
  order_number   text not null unique,
  customer_id    uuid not null references vaderetro.customers(id),
  status         text not null default 'pending',
  payment_status text not null default 'pending',
  payment_method text,
  subtotal       numeric(14,2) not null default 0,
  discount       numeric(14,2) not null default 0,
  total          numeric(14,2) not null default 0,
  notes          text,
  completed_at   timestamptz,
  created_at     timestamptz not null default now(),
  updated_at     timestamptz not null default now(),
  constraint orders_status_valid check (status in
    ('pending','payment_review','paid','processing','completed','cancelled','rejected')),
  constraint orders_payment_status_valid check (payment_status in
    ('pending','under_review','approved','rejected','refunded')),
  constraint orders_amounts_nonneg check (subtotal >= 0 and discount >= 0 and total >= 0),
  constraint orders_total_matches check (total = subtotal - discount)
);

-- ÍTEMS DEL PEDIDO (copia histórica de nombre, precio y cantidad) ----------
create table vaderetro.order_items (
  id           uuid primary key default gen_random_uuid(),
  order_id     uuid not null references vaderetro.orders(id) on delete cascade,
  product_id   uuid references vaderetro.products(id),
  product_name text not null,
  quantity     integer not null default 1,
  unit_price   numeric(14,2) not null,
  subtotal     numeric(14,2) not null,
  created_at   timestamptz not null default now(),
  constraint order_items_qty_pos check (quantity > 0),
  constraint order_items_price_nonneg check (unit_price >= 0),
  constraint order_items_subtotal_matches check (subtotal = quantity * unit_price)
);

-- PAGOS --------------------------------------------------------------------
create table vaderetro.payments (
  id                    uuid primary key default gen_random_uuid(),
  order_id              uuid not null references vaderetro.orders(id) on delete cascade,
  method                text not null,
  amount                numeric(14,2) not null,
  status                text not null default 'pending',
  transaction_reference text,
  payment_link          text,
  paid_at               timestamptz,
  reviewed_at           timestamptz,
  reviewed_by           uuid references auth.users(id),
  created_at            timestamptz not null default now(),
  updated_at            timestamptz not null default now(),
  constraint payments_status_valid check (status in
    ('pending','under_review','approved','rejected','refunded')),
  constraint payments_amount_nonneg check (amount >= 0)
);

-- CONFIGURACIÓN DE LA TIENDA ----------------------------------------------
create table vaderetro.store_settings (
  id                   uuid primary key default gen_random_uuid(),
  whatsapp             text,
  email                text,
  instagram            text,
  facebook             text,
  currency             text not null default 'USD',
  country              text not null default 'US',
  payment_link         text,
  payment_link_enabled boolean not null default false,
  created_at           timestamptz not null default now(),
  updated_at           timestamptz not null default now()
);

insert into vaderetro.store_settings (currency, country, payment_link, payment_link_enabled)
values ('USD', 'US', null, false);
```

### Numeración segura de pedidos

`order_number` con formato `VADE-000001`, generado de forma segura ante pedidos simultáneos:

```sql
create sequence vaderetro.order_number_seq;

create or replace function vaderetro.set_order_number()
returns trigger language plpgsql as $$
begin
  if new.order_number is null or new.order_number = '' then
    new.order_number := 'VADE-' || lpad(nextval('vaderetro.order_number_seq')::text, 6, '0');
  end if;
  return new;
end $$;

create trigger orders_set_number
  before insert on vaderetro.orders
  for each row execute function vaderetro.set_order_number();
```

Una secuencia es atómica: dos inserciones concurrentes nunca reciben el mismo número.

### Datos demo

Cargar las 3 categorías (`medallas`, `con-cadena`, `edicion-especial`) y los 6 productos demo que están en `Component.DATA` del prototipo, con sus precios en USD, stock y materiales. `demo-04` va con `stock = 0` para poder probar el estado sin stock.

## Reglas que el build debe respetar

1. Los productos vienen de Supabase; nada de catálogo quemado en el código.
2. No mostrar productos con `is_active = false`.
3. No permitir comprar con `stock = 0`; la cantidad nunca supera el stock.
4. Todos los precios en USD.
5. El carrito persiste ante recarga (localStorage o cookie).
6. El link de pago sale de `store_settings`. Mientras `payment_link_enabled = false`, no mostrar links falsos ni `#`.
7. Abrir el link de pago no cambia `payment_status`: la aprobación es manual (`under_review` → `approved`).
8. `order_items` guarda copia de nombre, precio y cantidad para conservar el historial.
9. El proyecto debe compilar sin errores.

## Fuera de alcance (explícitamente pedido)

Nada de Stripe, PayPal, Pagopar, Bancard, APIs de tarjeta ni webhooks de pago. El cobro es por link externo.

## Pendiente de la cliente

Textos definitivos, catálogo, fotografías, precios, descripciones y el link externo de pago. La carga completa la hacemos nosotros al recibir la información.

Consultas sobre este cliente: **PATRICIO**.
