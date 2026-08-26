<?php
// ============================================================================
// VADE RETRO — diagnóstico temporal
//
// Le pregunta a Square qué locales tiene la cuenta, para saber cuál poner en
// LOCATION_ID. Sandbox y producción son cuentas distintas y cada una tiene su
// propio local, así que el ID que sirve en pruebas no sirve para cobrar.
//
// SOLO LEE. ⚠️ BORRAR cuando termine el diagnóstico.
// ============================================================================

define('VADE', 1);
require __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

const CLAVE = 'yTEBjAS7Lxro3Qp2Zs0uZqir';
if (($_GET['clave'] ?? '') !== CLAVE) { http_response_code(403); exit('{"error":"clave incorrecta"}'); }

$base = AMBIENTE === 'produccion'
  ? 'https://connect.squareup.com'
  : 'https://connect.squareupsandbox.com';

$ch = curl_init($base . '/v2/locations');
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_TIMEOUT        => 20,
  CURLOPT_HTTPHEADER     => [
    'Authorization: Bearer ' . TOKEN,
    'Square-Version: ' . SQUARE_VERSION,
  ],
]);
$r    = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$j = json_decode((string) $r, true);

if ($http < 200 || $http >= 300) {
  http_response_code(502);
  exit(json_encode([
    'ambiente' => AMBIENTE,
    'error'    => 'Square rechazo la consulta',
    'detalle'  => $j['errors'][0]['detail'] ?? substr((string) $r, 0, 300),
    'codigo'   => $j['errors'][0]['code'] ?? null,
  ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

$out = [];
foreach (($j['locations'] ?? []) as $l) {
  $out[] = [
    'ID'       => $l['id'] ?? null,
    'nombre'   => $l['name'] ?? null,
    'estado'   => $l['status'] ?? null,
    'moneda'   => $l['currency'] ?? null,
    'pais'     => $l['country'] ?? null,
    'tipo'     => $l['type'] ?? null,
  ];
}
echo json_encode(['ambiente' => AMBIENTE, 'locales' => $out], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
