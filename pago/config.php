<?php
// ============================================================================
// VADE RETRO — configuración de cobro
//
// ESTE ARCHIVO GUARDA LA LLAVE DE LA CAJA. No se sube a GitHub ni se comparte.
// Se edita directamente en el servidor de Hostinger.
// ============================================================================

if (!defined('VADE')) { http_response_code(403); exit('403'); }

// ---------------------------------------------------------------------------
// 1. AMBIENTE
//    'sandbox'    = pruebas. No mueve plata real. Empezá siempre por acá.
//    'produccion' = cobra de verdad.
// ---------------------------------------------------------------------------
const AMBIENTE = 'sandbox';

// ---------------------------------------------------------------------------
// 2. TOKEN DE ACCESO  ← PEGAR ACÁ, entre las comillas
//    Se saca del panel de Square: Developer > Applications > Credentials.
//    Ojo: el de sandbox y el de producción son distintos. Pegá el que
//    corresponda al AMBIENTE elegido arriba.
// ---------------------------------------------------------------------------
const TOKEN = '';

// ---------------------------------------------------------------------------
// 3. LOCATION ID  — este no es secreto
// ---------------------------------------------------------------------------
const LOCATION_ID = 'LZXZ481HWS958';

// ---------------------------------------------------------------------------
// 4. A DÓNDE VUELVE EL COMPRADOR DESPUÉS DE PAGAR
// ---------------------------------------------------------------------------
const URL_GRACIAS = 'https://vaderetro.org/#/gracias';

// ---------------------------------------------------------------------------
// 5. VERSIÓN DE LA API DE SQUARE
//    Si Square responde un error que menciona "version", cambiá solo esta
//    línea por la fecha que indique el panel de desarrolladores.
// ---------------------------------------------------------------------------
const SQUARE_VERSION = '2024-10-17';

// ---------------------------------------------------------------------------
// 6. SUPABASE — para que el numero de pedido sea unico
//    El numero salia de un contador en el navegador de cada comprador, asi que
//    todos empezaban en VADE-000001. Ahora lo da la base, que lleva una sola
//    cuenta para todos.
//
//    La clave va acá y en ningún otro lado: es la de servicio, saltea las
//    restricciones de la base. Nunca en el sitio, nunca por chat ni por mail.
// ---------------------------------------------------------------------------
const SUPABASE_URL    = 'https://api.neura.com.py';
const SUPABASE_SCHEMA = 'vaderetro';
const SUPABASE_KEY    = '';   // ← pegar acá la service_role (rotada)
