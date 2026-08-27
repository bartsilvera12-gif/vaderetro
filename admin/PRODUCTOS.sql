-- ============================================================================
-- VADE RETRO — el catálogo pasa a la base
--
-- Hasta ahora las siete piezas vivían escritas dentro del index.html y otra vez
-- dentro de pago/crear.php. Cambiar un precio era editar dos archivos y volver
-- a subirlos, y si te olvidabas de uno la página mostraba un precio y Square
-- cobraba otro. Con esta tabla hay UNA sola fuente y el panel la edita.
--
-- Correr una vez en Supabase → SQL Editor. Es idempotente: se puede volver a
-- correr sin romper nada ni duplicar las piezas.
--
-- POR QUÉ precio Y envio SON COLUMNAS Y EL RESTO ES jsonb:
-- el servidor solo necesita cobrar bien, y para eso le alcanza con esos dos
-- números, que además así quedan tipados (no puede entrar "70,00" ni un texto).
-- Todo lo demás —fotos, textos, colores, medidas— cambia seguido y no lo mira
-- nadie más que la vitrina, así que va en un solo campo libre y agregar un dato
-- nuevo no obliga a tocar la tabla.
-- ============================================================================

create table if not exists vaderetro.productos (
  id      bigserial primary key,
  slug    text        not null unique,   -- el identificador que viaja en el carrito
  orden   int         not null default 100,
  activo  boolean     not null default true,
  precio  numeric(10,2) not null,
  envio   numeric(10,2) not null default 0,
  datos   jsonb       not null default '{}'::jsonb,
  creado  timestamptz not null default now()
);

create index if not exists productos_orden on vaderetro.productos (activo, orden);

alter table vaderetro.productos enable row level security;

-- ----------------------------------------------------------------------------
-- Permisos. Mismo criterio que los pedidos: atados al UUID del administrador y
-- no al rol "authenticated", porque si el registro está abierto cualquiera
-- podría crearse una cuenta.
--
-- La vitrina NO lee de acá: lo hace pago/catalogo.php con la clave de servicio,
-- que vive en el servidor. Por eso no hace falta ninguna regla de lectura
-- pública, y la clave anónima sigue sin servir para ver nada.
-- ----------------------------------------------------------------------------
drop policy if exists "admin lee productos"    on vaderetro.productos;
drop policy if exists "admin crea productos"   on vaderetro.productos;
drop policy if exists "admin edita productos"  on vaderetro.productos;
drop policy if exists "admin borra productos"  on vaderetro.productos;

create policy "admin lee productos"   on vaderetro.productos for select to authenticated
  using ( auth.uid() = 'c25d3efd-6061-4d0c-91fe-2d3dc08e6894' );
create policy "admin crea productos"  on vaderetro.productos for insert to authenticated
  with check ( auth.uid() = 'c25d3efd-6061-4d0c-91fe-2d3dc08e6894' );
create policy "admin edita productos" on vaderetro.productos for update to authenticated
  using      ( auth.uid() = 'c25d3efd-6061-4d0c-91fe-2d3dc08e6894' )
  with check ( auth.uid() = 'c25d3efd-6061-4d0c-91fe-2d3dc08e6894' );
create policy "admin borra productos" on vaderetro.productos for delete to authenticated
  using ( auth.uid() = 'c25d3efd-6061-4d0c-91fe-2d3dc08e6894' );

grant usage on schema vaderetro to authenticated;
grant select, insert, update, delete on vaderetro.productos to authenticated;
grant usage, select on sequence vaderetro.productos_id_seq to authenticated;

-- ----------------------------------------------------------------------------
-- Las siete piezas que ya estaban en el sitio, tal cual, con sus precios.
-- "on conflict do nothing": si ya se corrió antes, no pisa lo que la clienta
-- haya editado desde el panel.
-- ----------------------------------------------------------------------------
insert into vaderetro.productos (slug, orden, activo, precio, envio, datos) values
  ('bendicion-cristal', 10, true, 70.00, 14.00, '{"img":"assets/cristal-1.webp","fit":"contain","ratioNum":"0.523","cardImg":"assets/cristal-card.webp","imgs":["assets/cristal-1.webp","assets/cristal-2.webp","assets/cristal-3.webp","assets/cristal-4.webp"],"oldPrice":null,"cat":"puerta","badgeTxt":null,"name":{"es":"Bendición de San Benito · Cristal cortado","en":"Saint Benedict Blessing · Cut crystal"},"desc":{"es":"Bordada en cristal cortado, con medalla giratoria.","en":"Embroidered in cut crystal, with a rotating medal."},"descLong":{"es":"Bendición de San Benito bordada en cristal cortado, para colgar de la manija de la entrada. La medalla es giratoria, así que pueden verse sus dos caras: la imagen del santo y la cruz con sus inscripciones.","en":"Saint Benedict blessing embroidered in cut crystal, to hang from the handle of the entrance door. The medal rotates, so both of its faces can be seen: the image of the saint and the cross with its inscriptions."},"colors":[{"css":"#C9A46A","name":{"es":"Miel","en":"Honey"}},{"css":"linear-gradient(135deg,#0F2349 50%,#C9A46A 50%)","name":{"es":"Azul con miel","en":"Blue with honey"}}],"specs":{"es":["Bendición de San Benito bordada en cristal cortado.","La medalla es giratoria.","Medidas: 12 pulgadas de largo · 5,5 pulgadas de ancho de la medalla."],"en":["Saint Benedict blessing embroidered in cut crystal.","The medal rotates.","Dimensions: 12 inches long · 5.5 inches wide at the medal."]}}'::jsonb),
  ('bendicion-doble', 20, true, 55.00, 11.00, '{"img":"assets/doble-1.webp","fit":"contain","ratioNum":"0.523","cardImg":"assets/doble-card.webp","imgs":["assets/doble-1.webp","assets/doble-2.webp","assets/doble-3.webp"],"oldPrice":null,"cat":"puerta","badgeTxt":null,"name":{"es":"Bendición de San Benito · Doble medalla","en":"Saint Benedict Blessing · Double medal"},"desc":{"es":"Bordada en cristal cortado, con dos medallas.","en":"Embroidered in cut crystal, with two medals."},"descLong":{"es":"Bendición de San Benito bordada en cristal cortado, con dos medallas: una grande arriba y otra menor debajo, rematadas en borla.","en":"Saint Benedict blessing embroidered in cut crystal, with two medals: a large one above and a smaller one below, finished with a tassel."},"colors":[{"css":"#C9A46A","name":{"es":"Miel","en":"Honey"}},{"css":"linear-gradient(135deg,#C9A46A 50%,#0F2349 50%)","name":{"es":"Miel con azul","en":"Honey with blue"}}],"specs":{"es":["Bendición de San Benito bordada en cristal cortado, doble medalla.","Medidas: 15 pulgadas de largo.","Primera medalla: 4 pulgadas de ancho.","Segunda medalla: 2 pulgadas y 3 centímetros."],"en":["Saint Benedict blessing embroidered in cut crystal, double medal.","Dimensions: 15 inches long.","First medal: 4 inches wide.","Second medal: 2 inches and 3 centimetres."]}}'::jsonb),
  ('bendicion-laminada', 30, true, 40.00, 11.00, '{"img":"assets/laminada-1.webp","fit":"contain","ratioNum":"0.571","cardImg":"assets/laminada-card.webp","imgs":["assets/laminada-1.webp","assets/laminada-2.webp","assets/laminada-3.webp"],"oldPrice":null,"cat":"puerta","badgeTxt":null,"name":{"es":"Bendición de San Benito · Imagen laminada","en":"Saint Benedict Blessing · Laminated image"},"desc":{"es":"Bordada en cristal cortado, con imagen a color laminada.","en":"Embroidered in cut crystal, with a laminated colour image."},"descLong":{"es":"Bendición de San Benito bordada en cristal cortado, con la imagen del santo a color laminada de un lado y la cruz esmaltada del otro.","en":"Saint Benedict blessing embroidered in cut crystal, with the laminated colour image of the saint on one side and the enamelled cross on the other."},"colors":[],"specs":{"es":["Bendición de San Benito bordada en cristal cortado.","Imagen a color laminada.","Color miel.","Medidas: 11 pulgadas de largo · 2 1/2 pulgadas de ancho de la medalla bordada."],"en":["Saint Benedict blessing embroidered in cut crystal.","Laminated colour image.","Honey.","Dimensions: 11 inches long · 2 1/2 inches wide at the embroidered medal."]}}'::jsonb),
  ('medallon-giratorio', 40, true, 50.00, 14.00, '{"img":"assets/giratorio-1.webp","fit":"contain","ratioNum":"0.546","cardImg":"assets/giratorio-card.webp","imgs":["assets/giratorio-1.webp","assets/giratorio-2.webp","assets/giratorio-3.webp","assets/giratorio-4.webp"],"oldPrice":null,"cat":"puerta","badgeTxt":null,"name":{"es":"Bendición de San Benito · Medallón giratorio","en":"Saint Benedict Blessing · Rotating medallion"},"desc":{"es":"Medallón de metal giratorio.","en":"Rotating metal medallion."},"descLong":{"es":"Bendición de San Benito con medallón de metal giratorio, para colgar de la manija de la entrada. Al girar deja ver sus dos caras.","en":"Saint Benedict blessing with a rotating metal medallion, to hang from the handle of the entrance door. Turning it reveals both of its faces."},"colors":[],"specs":{"es":["Medallón de metal giratorio.","Medidas: 13 pulgadas de largo · 4 3/4 pulgadas de ancho de la medalla."],"en":["Rotating metal medallion.","Dimensions: 13 inches long · 4 3/4 inches wide at the medal."]}}'::jsonb),
  ('bendicion-tejida', 50, true, 30.00, 11.00, '{"img":"assets/tejida-1.webp","fit":"contain","ratioNum":"0.571","cardImg":"assets/tejida-card.webp","imgs":["assets/tejida-1.webp","assets/tejida-2.webp","assets/tejida-3.webp"],"oldPrice":null,"cat":"puerta","badgeTxt":null,"name":{"es":"Bendición de San Benito · Tejida","en":"Saint Benedict Blessing · Woven"},"desc":{"es":"Tejida en marrón y ámbar, con cinco medallas.","en":"Woven in brown and amber, with five medals."},"descLong":{"es":"Bendición de San Benito tejida, en marrón y ámbar, con cinco medallas que cuelgan de cordones de distinto largo.","en":"Woven Saint Benedict blessing, in brown and amber, with five medals hanging from cords of different lengths."},"colors":[],"specs":{"es":["Bendición de San Benito tejida.","5 medallas.","Color marrón y ámbar.","Medidas: 15 pulgadas de largo."],"en":["Woven Saint Benedict blessing.","5 medals.","Brown and amber.","Dimensions: 15 inches long."]}}'::jsonb),
  ('decenario-madera', 60, true, 25.00, 11.00, '{"img":"assets/decenario-1.webp","fit":"contain","ratioNum":"0.667","cardImg":"assets/decenario-card.webp","imgs":["assets/decenario-1.webp","assets/decenario-2.webp","assets/decenario-3.webp","assets/decenario-4.webp"],"oldPrice":null,"cat":"puerta","badgeTxt":null,"name":{"es":"Bendición de San Benito · Decenario en madera","en":"Saint Benedict Blessing · Wooden decade"},"desc":{"es":"Decenario con cuentas de madera y medalla en metal.","en":"Wooden-bead decade with a metal medal."},"descLong":{"es":"Bendición de San Benito en formato decenario, con cuentas de madera y medalla en metal. Se ajusta de dos maneras en la entrada: colgando a lo largo de la manija, o en redondo alrededor de ella.","en":"Saint Benedict blessing in decade format, with wooden beads and a metal medal. It fits the entrance two ways: hanging long from the handle, or wrapped round it."},"colors":[],"specs":{"es":["Decenario con cuentas en madera.","Medalla en metal.","Dos modos de ajuste: largo o redondo.","Medidas: 14 pulgadas de largo · medalla 1 3/4 pulgadas de ancho."],"en":["Decade with wooden beads.","Metal medal.","Two ways to fit it: long or round.","Dimensions: 14 inches long · medal 1 3/4 inches wide."]}}'::jsonb),
  ('cordon-largo', 70, true, 30.00, 11.00, '{"img":"assets/cordon-1.webp","fit":"contain","ratioNum":"0.523","cardImg":"assets/cordon-card.webp","imgs":["assets/cordon-1.webp","assets/cordon-2.webp","assets/cordon-3.webp","assets/cordon-4.webp"],"oldPrice":null,"cat":"puerta","badgeTxt":null,"name":{"es":"Bendición de San Benito · Cordón largo","en":"Saint Benedict Blessing · Long cord"},"desc":{"es":"Medalla en metal sobre cordón con cuentas.","en":"Metal medal on a beaded cord."},"descLong":{"es":"Bendición de San Benito para colgar de la manija de la entrada, con la medalla en metal sobre cordón con cuentas. Es la pieza más larga del catálogo, pensada para puertas altas donde se ve de cuerpo entero.","en":"Saint Benedict blessing to hang from the handle of the entrance door, with a metal medal on a beaded cord. It is the longest piece in the catalogue, made for tall doors where it is seen full length."},"colors":[],"specs":{"es":["Medalla en metal.","Medidas: 20 pulgadas de largo · 3 1/2 pulgadas de ancho de la medalla."],"en":["Metal medal.","Dimensions: 20 inches long · 3 1/2 inches wide at the medal."]}}'::jsonb)
on conflict (slug) do nothing;
