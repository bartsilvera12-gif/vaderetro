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

$subtotal = 0;
foreach ($lineas as $l) $subtotal += $l['base_price_money']['amount'] * (int) $l['quantity'];
$subtotal += $envio;

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

// --- numero de pedido: lo da la base, no el navegador ----------------------
// Si Supabase no responde, el cobro NO se cae: se usa un numero con fecha y
// azar. Preferible un numero feo a una venta perdida.
$referencia = 'VADE-' . date('ymdHis') . '-' . strtoupper(bin2hex(random_bytes(2)));

if (SUPABASE_KEY !== '') {
  $fila = [
    'estado'  => 'pendiente',
    'total'   => round(($subtotal ?? 0) / 100, 2),
    'envio'   => round($envio / 100, 2),
    'items'   => $lineas,
    'cliente' => $c,
  ];
  $sb = curl_init(rtrim(SUPABASE_URL, '/') . '/rest/v1/pedidos');
  curl_setopt_array($sb, [
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 8,
    CURLOPT_HTTPHEADER     => [
      'apikey: ' . SUPABASE_KEY,
      'Authorization: Bearer ' . SUPABASE_KEY,
      'Content-Type: application/json',
      'Content-Profile: ' . SUPABASE_SCHEMA,
      'Prefer: return=representation',
    ],
    CURLOPT_POSTFIELDS => json_encode($fila, JSON_UNESCAPED_UNICODE),
  ]);
  $sbr = curl_exec($sb);
  $sbc = curl_getinfo($sb, CURLINFO_HTTP_CODE);
  curl_close($sb);
  $sbj = json_decode((string) $sbr, true);
  if ($sbc >= 200 && $sbc < 300 && !empty($sbj[0]['id'])) {
    $referencia = 'VADE-' . str_pad((string) $sbj[0]['id'], 6, '0', STR_PAD_LEFT);
  }
}

// La direccion se manda por tres vias distintas porque la nota del pedido
// Square la descarta sin avisar. La nota del ITEM si aparece en el detalle
// del pedido, y metadata queda como respaldo consultable.
$lineas[0]['note'] = substr($nota, 0, 500);

$orden = [
  'location_id'  => LOCATION_ID,
  'reference_id' => substr($referencia, 0, 40),
  'line_items'   => $lineas,
  'note'         => substr($nota, 0, 500),
  'metadata'     => ['envio_a' => substr($nota, 0, 255)],
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
    // Square no maneja el envio: lo cobramos nosotros como cargo aparte y la
    // direccion la toma nuestro formulario. Si se prende, Square agrega su
    // propia seccion de tarifas y el comprador podria pagar el envio dos veces.
    'ask_for_shipping_address' => false,
  ],
];
// Lo que ya nos dio el comprador va precargado en la pagina de Square, para
// que no tenga que escribirlo dos veces.
$pre = [];
$email = filter_var($campo('email'), FILTER_VALIDATE_EMAIL);
if ($email) $pre['buyer_email'] = $email;
// El telefono es una comodidad, no un requisito: solo se manda cuando se
// puede afirmar que es un numero valido. Asumir que diez digitos era EE.UU.
// convertia un numero paraguayo en +10982487844, y Square rechazaba el
// pedido ENTERO. Un dato de contacto no puede tumbar una venta.
$crudo = trim($campo('telefono'));
$tel   = preg_replace('/\D/', '', $crudo);
if (strpos($crudo, '+') === 0 && strlen($tel) >= 8) {
  $pre['buyer_phone_number'] = '+' . $tel;                       // ya viene con pais
} elseif (strlen($tel) === 11 && $tel[0] === '1') {
  $pre['buyer_phone_number'] = '+' . $tel;                       // EE.UU. con el 1
} elseif (strlen($tel) === 10 && $tel[0] >= '2') {
  $pre['buyer_phone_number'] = '+1' . $tel;                      // EE.UU.: nunca empieza en 0 ni 1
}
if ($pre) $payload['pre_populated_data'] = $pre;

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

// Red de seguridad: si Square rechaza por algo de los datos precargados, se
// reintenta sin ellos. El comprador escribe su email a mano y la venta se
// concreta igual. Nunca perder una venta por una comodidad.
if (!$fallo && $http >= 400 && isset($payload['pre_populated_data'])) {
  unset($payload['pre_populated_data']);
  $ch = curl_init($base . '/v2/online-checkout/payment-links');
  curl_setopt_array($ch, [
    CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20,
    CURLOPT_HTTPHEADER => [
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
}

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
