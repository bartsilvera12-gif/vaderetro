<?php
// ============================================================================
// VADE RETRO — crea el enlace de cobro en Square
//
// El navegador manda QUÉ y CUÁNTO se lleva. Los precios NO llegan de afuera:
// se recalculan acá con la tabla de abajo. Si confiáramos en el monto que
// manda la página, cualquiera podría editarlo y pagar un dólar.
//
// El token vive en config.php, que el servidor ejecuta pero nunca muestra.
// ============================================================================

define('VADE', 1);
require __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

function salir($codigo, $datos) { http_response_code($codigo); echo json_encode($datos, JSON_UNESCAPED_UNICODE); exit; }

// ---------------------------------------------------------------------------
// CATÁLOGO — precios en centavos.
// TIENE QUE COINCIDIR con Component.DATA del index.html. Si cambia un precio
// allá, cambialo acá también o el cobro no va a dar lo que muestra la página.
// ---------------------------------------------------------------------------
$CATALOGO = [
  'bendicion-cristal'  => ['n' => 'Bendición de San Benito · Cristal cortado',    'p' => 7000, 'e' => 1400],
  'bendicion-doble'    => ['n' => 'Bendición de San Benito · Doble medalla',      'p' => 5500, 'e' => 1100],
  'bendicion-laminada' => ['n' => 'Bendición de San Benito · Imagen laminada',    'p' => 4000, 'e' => 1100],
  'medallon-giratorio' => ['n' => 'Bendición de San Benito · Medallón giratorio', 'p' => 5000, 'e' => 1400],
  'bendicion-tejida'   => ['n' => 'Bendición de San Benito · Tejida',             'p' => 3000, 'e' => 1100],
  'decenario-madera'   => ['n' => 'Bendición de San Benito · Decenario en madera','p' => 2500, 'e' => 1100],
  'cordon-largo'       => ['n' => 'Bendición de San Benito · Cordón largo',       'p' => 3000, 'e' => 1100],
];

if (TOKEN === '') salir(500, ['ok' => false, 'error' => 'Falta cargar el token en pago/config.php.']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') salir(405, ['ok' => false, 'error' => 'Método no permitido.']);

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body) || empty($body['items'])) salir(400, ['ok' => false, 'error' => 'Pedido vacío.']);

// --- armar las líneas con los precios de acá, no los del navegador ---------
$lineas = [];
$envio  = 0;
foreach ($body['items'] as $slug => $cant) {
  if (!isset($CATALOGO[$slug])) continue;               // slug desconocido: se ignora
  $cant = (int) $cant;
  if ($cant < 1 || $cant > 20) continue;                // cantidad fuera de rango
  $art = $CATALOGO[$slug];
  $lineas[] = [
    'name'             => $art['n'],
    'quantity'         => (string) $cant,
    'base_price_money' => ['amount' => $art['p'], 'currency' => 'USD'],
  ];
  // Un solo envio por pedido: el mas caro del carrito. Va todo en un paquete.
  if ($art['e'] > $envio) $envio = $art['e'];
}
if (!$lineas) salir(400, ['ok' => false, 'error' => 'No hay piezas válidas en el pedido.']);

// --- datos del comprador, para que la clienta sepa a dónde mandar ----------
$c = is_array($body['cliente'] ?? null) ? $body['cliente'] : [];
$campo = function ($k) use ($c) { return trim(substr((string) ($c[$k] ?? ''), 0, 120)); };

$nota = trim(sprintf(
  "Envío a: %s %s — %s, %s, %s %s. Tel: %s. %s",
  $campo('nombre'), $campo('apellido'), $campo('direccion'),
  $campo('ciudad'), $campo('estado'), $campo('zip'),
  $campo('telefono'), $campo('obs')
));
if (strlen($nota) > 480) $nota = substr($nota, 0, 480);

$referencia = preg_replace('/[^A-Za-z0-9\-]/', '', (string) ($body['pedido'] ?? '')) ?: ('VADE-' . date('ymdHis'));

$orden = [
  'location_id'  => LOCATION_ID,
  'reference_id' => substr($referencia, 0, 40),
  'line_items'   => $lineas,
  'note'         => $nota,
];
if ($envio > 0) {
  $orden['service_charges'] = [[
    'name'              => 'Envío',
    'amount_money'      => ['amount' => $envio, 'currency' => 'USD'],
    'calculation_phase' => 'TOTAL_PHASE',
  ]];
}

$payload = [
  'idempotency_key' => bin2hex(random_bytes(16)),
  'order'           => $orden,
  'checkout_options' => [
    'redirect_url'             => URL_GRACIAS,
    'ask_for_shipping_address' => false,   // la dirección ya la tomó el sitio
  ],
];
$email = filter_var($campo('email'), FILTER_VALIDATE_EMAIL);
if ($email) $payload['pre_populated_data'] = ['buyer_email' => $email];

// --- llamar a Square -------------------------------------------------------
$base = AMBIENTE === 'produccion'
  ? 'https://connect.squareup.com'
  : 'https://connect.squareupsandbox.com';

$ch = curl_init($base . '/v2/online-checkout/payment-links');
curl_setopt_array($ch, [
  CURLOPT_POST           => true,
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_TIMEOUT        => 20,
  CURLOPT_HTTPHEADER     => [
    'Authorization: Bearer ' . TOKEN,
    'Square-Version: ' . SQUARE_VERSION,
    'Content-Type: application/json',
  ],
  CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
]);
$respuesta = curl_exec($ch);
$http      = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$fallo     = curl_error($ch);
curl_close($ch);

if ($fallo) salir(502, ['ok' => false, 'error' => 'No se pudo contactar a Square.', 'detalle' => $fallo]);

$json = json_decode($respuesta, true);
if ($http >= 200 && $http < 300 && !empty($json['payment_link']['url'])) {
  salir(200, ['ok' => true, 'url' => $json['payment_link']['url']]);
}

// Square rechazó el pedido. Se devuelve su mensaje tal cual: no trae el token
// y sin él es imposible saber qué corregir.
salir(502, [
  'ok'      => false,
  'error'   => 'Square rechazó el pedido.',
  'detalle' => $json['errors'][0]['detail'] ?? substr((string) $respuesta, 0, 300),
]);
