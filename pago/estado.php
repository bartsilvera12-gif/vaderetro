<?php
// ============================================================================
// VADE RETRO — sincroniza el estado de pago
//
// El panel necesita distinguir quién pagó de quién abandonó el checkout. Lo
// normal sería un webhook, pero registrar uno exige entrar al panel de
// desarrolladores de Square, y no tenemos ese acceso.
//
// Así que va al revés: le preguntamos a Square por sus últimas órdenes y
// actualizamos la base con lo que diga. Mismo resultado, sin depender de
// permisos que no tenemos.
//
// NO alcanza con mirar el estado de la orden: Square puede dejarla en OPEN
// aunque el pago haya sido rechazado. Lo que prueba que entró plata es que la
// orden tenga un cobro asociado (tenders) por el total. Marcar como pagado
// algo que no lo está haría que la vendedora despache mercadería regalada.
// ============================================================================

define('VADE', 1);
require __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

function fin($c, $d) { http_response_code($c); echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }

if (TOKEN === '')        fin(500, ['ok' => false, 'error' => 'Falta el token de Square.']);
if (SUPABASE_KEY === '') fin(500, ['ok' => false, 'error' => 'Falta la clave de Supabase.']);

// --- 1. las últimas órdenes de Square --------------------------------------
$base = AMBIENTE === 'produccion'
  ? 'https://connect.squareup.com'
  : 'https://connect.squareupsandbox.com';

$ch = curl_init($base . '/v2/orders/search');
curl_setopt_array($ch, [
  CURLOPT_POST           => true,
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_TIMEOUT        => 20,
  CURLOPT_HTTPHEADER     => [
    'Authorization: Bearer ' . TOKEN,
    'Square-Version: ' . SQUARE_VERSION,
    'Content-Type: application/json',
  ],
  CURLOPT_POSTFIELDS => json_encode([
    'location_ids' => [LOCATION_ID],
    'limit'        => 200,
    'query'        => ['sort' => ['sort_field' => 'CREATED_AT', 'sort_order' => 'DESC']],
  ]),
]);
$r = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http < 200 || $http >= 300) {
  $j = json_decode((string) $r, true);
  fin(502, ['ok' => false, 'error' => 'Square no respondió', 'detalle' => $j['errors'][0]['detail'] ?? $http]);
}

$sq = json_decode((string) $r, true);

// --- 2. quién pagó y quién no ----------------------------------------------
$pagados = [];
$caidos  = [];
$detalle = [];
foreach (($sq['orders'] ?? []) as $o) {
  $ref = $o['reference_id'] ?? '';
  if (!preg_match('/^VADE-(\d{6})$/', $ref, $m)) continue;   // los de respaldo no se tocan
  $id = (int) $m[1];
  // No alcanza con que exista un cobro: un intento rechazado tambien queda
  // registrado, con su monto. Solo cuenta el que Square marca CAPTURED, que
  // es plata efectivamente tomada de la tarjeta.
  $cobrado = 0;
  $estados = [];
  foreach (($o['tenders'] ?? []) as $t) {
    $st = $t['card_details']['status'] ?? ($t['type'] ?? 'DESCONOCIDO');
    $estados[] = $st;
    if ($st === 'CAPTURED') $cobrado += $t['amount_money']['amount'] ?? 0;
  }
  $total = $o['total_money']['amount'] ?? 0;
  $ok = ($cobrado > 0 && $cobrado >= $total);
  if ($ok) $pagados[] = $id; else $caidos[] = $id;
  $detalle[] = [
    'ref'     => $ref,
    'orden'   => $o['state'] ?? null,
    'total'   => number_format($total / 100, 2),
    'cobros'  => $estados ?: ['(ninguno)'],
    'pagado'  => $ok,
  ];
}

// --- 3. actualizar la base --------------------------------------------------
function patch($ids, $estado) {
  if (!$ids) return 0;
  $url = rtrim(SUPABASE_URL, '/') . '/rest/v1/pedidos?id=in.(' . implode(',', $ids) . ')'
       . '&estado=neq.despachado';   // lo ya despachado no se pisa
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_CUSTOMREQUEST  => 'PATCH',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_HTTPHEADER     => [
      'apikey: ' . SUPABASE_KEY,
      'Authorization: Bearer ' . SUPABASE_KEY,
      'Content-Type: application/json',
      'Content-Profile: ' . SUPABASE_SCHEMA,
      'Prefer: return=representation',
    ],
    CURLOPT_POSTFIELDS => json_encode(['estado' => $estado]),
  ]);
  $res = curl_exec($ch);
  curl_close($ch);
  $j = json_decode((string) $res, true);
  return is_array($j) ? count($j) : 0;
}

$n1 = patch($pagados, 'pagado');
$n2 = patch($caidos,  'sin pagar');

// La version permite comprobar desde afuera que subio el archivo correcto.
// Sin esto no habia forma de distinguir una version vieja de una nueva cuando
// los numeros que devuelven coinciden por casualidad.
fin(200, [
  'ok'           => true,
  'version'      => 'v3-capturados',
  'detalle'      => $detalle,
  'pagados'      => count($pagados),
  'sin_pagar'    => count($caidos),
  'actualizados' => $n1 + $n2,
]);
