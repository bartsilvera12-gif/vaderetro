<?php
// ============================================================================
// VADE RETRO — verificación temporal
//
// Lee los últimos pedidos de Square para confirmar que la nota con la
// dirección de envío está llegando al panel de la vendedora.
//
// SOLO LEE. No cobra, no modifica, no borra nada.
//
// ⚠️  BORRAR ESTE ARCHIVO cuando termine la verificación. Mientras exista,
//     cualquiera que adivine la clave puede ver los datos de los pedidos.
// ============================================================================

define('VADE', 1);
require __DIR__ . '/config.php';

const CLAVE = 'yTEBjAS7Lxro3Qp2Zs0uZqir';

header('Content-Type: application/json; charset=utf-8');

if (($_GET['clave'] ?? '') !== CLAVE) { http_response_code(403); exit('{"error":"clave incorrecta"}'); }
if (TOKEN === '') { http_response_code(500); exit('{"error":"falta el token en config.php"}'); }

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
    'limit'        => 3,
    'query'        => ['sort' => ['sort_field' => 'CREATED_AT', 'sort_order' => 'DESC']],
  ]),
]);
$r = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$j = json_decode($r, true);
if ($http < 200 || $http >= 300) {
  http_response_code(502);
  exit(json_encode(['error' => 'Square rechazo la consulta', 'detalle' => $j['errors'][0]['detail'] ?? substr((string) $r, 0, 300)], JSON_UNESCAPED_UNICODE));
}

// Se devuelve solo lo necesario para verificar, no el pedido completo.
$salida = [];
foreach (($j['orders'] ?? []) as $o) {
  $salida[] = [
    'referencia' => $o['reference_id'] ?? null,
    'estado'     => $o['state'] ?? null,
    'creado'     => $o['created_at'] ?? null,
    'total'      => isset($o['total_money']['amount']) ? number_format($o['total_money']['amount'] / 100, 2) : null,
    'envio'      => isset($o['total_service_charge_money']['amount']) ? number_format($o['total_service_charge_money']['amount'] / 100, 2) : null,
    'piezas'     => array_map(function ($l) {
        return ($l['quantity'] ?? '?') . ' x ' . ($l['name'] ?? '?');
    }, $o['line_items'] ?? []),
    'NOTA'       => $o['note'] ?? '(vacia)',
    'ENVIO_A'    => array_map(function ($f) {
        $d = $f['shipment_details']['recipient'] ?? [];
        $a = $d['address'] ?? [];
        return trim(($d['display_name'] ?? '') . ' — '
          . ($a['address_line_1'] ?? '') . ', ' . ($a['locality'] ?? '') . ', '
          . ($a['administrative_district_level_1'] ?? '') . ' ' . ($a['postal_code'] ?? ''));
    }, $o['fulfillments'] ?? []) ?: '(sin datos de envio)',
  ];
}
echo json_encode(['ambiente' => AMBIENTE, 'pedidos' => $salida], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
