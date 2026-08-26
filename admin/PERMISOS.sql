-- ============================================================================
-- VADE RETRO — permisos del panel de administración
--
-- La tabla está cerrada a todo el mundo. Estas reglas abren la puerta a UN
-- usuario concreto, identificado por su UUID.
--
-- Se ata al UUID y no al rol "authenticated" a propósito: si el Supabase
-- tiene el registro abierto, cualquiera podría crearse una cuenta. Con esta
-- regla igual no vería nada, porque su UUID no es el que figura acá.
-- ============================================================================

-- Leer los pedidos
create policy "admin lee pedidos"
  on vaderetro.pedidos
  for select
  to authenticated
  using ( auth.uid() = 'c25d3efd-6061-4d0c-91fe-2d3dc08e6894' );

-- Marcarlos como despachados
create policy "admin actualiza pedidos"
  on vaderetro.pedidos
  for update
  to authenticated
  using      ( auth.uid() = 'c25d3efd-6061-4d0c-91fe-2d3dc08e6894' )
  with check ( auth.uid() = 'c25d3efd-6061-4d0c-91fe-2d3dc08e6894' );

-- El schema tiene que ser alcanzable por la API
grant usage on schema vaderetro to authenticated;
grant select, update on vaderetro.pedidos to authenticated;

-- ----------------------------------------------------------------------------
-- Para sumar otro administrador: creás el usuario en Authentication, copiás su
-- UUID y agregás su id a las dos reglas, así:
--
--   using ( auth.uid() in ('uuid-uno', 'uuid-dos') )
-- ----------------------------------------------------------------------------
