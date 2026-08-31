<?php
// ============================================================================
// VADE RETRO — el catálogo, para la vitrina y para el cobro
//
// Devuelve las piezas activas en el mismo formato que usaba la lista escrita a
// mano dentro del index.html, así la página no distingue de dónde vinieron.
//
// Lo lee el servidor con la clave de servicio, no el navegador: la clave nunca
// sale de acá. Por eso tampoco hace falta abrirle la tabla a nadie en Supabase.
//
// Además deja una copia en disco cada vez que la base contesta bien. Esa copia
// es la red de seguridad del cobro: si Supabase se cae justo cuando alguien
// está comprando, crear.php cobra con los últimos precios buenos que se
// conocieron, en vez de con los que quedaron viejos dentro del código.
// ============================================================================

// config.php se niega a correr si no viene de uno de nuestros archivos. Cuando
// a este lo incluye crear.php la constante ya esta puesta, y volver a ponerla
// escupiria un aviso de PHP en medio del JSON.
if (!defined('VADE')) define('VADE', 1);
require_once __DIR__ . '/config.php';

// La copia va en un .php y no en un .json, y arranca con una linea que corta
// la ejecucion. Asi, si alguien pide el archivo por su direccion, el servidor
// lo interpreta, se topa con el exit y devuelve una pagina vacia en vez del
// contenido. Con .json quedaba a la vista de cualquiera que adivinara el
// nombre. No hay nada secreto ahi —son los mismos precios de la vitrina— pero
// es un archivo interno y no tiene por que servirse.
//
// La otra salida era bloquearlo por .htaccess. Esta no depende de que el
// servidor este configurado de una manera concreta.
const VADE_CACHE_CATALOGO = __DIR__ . '/catalogo-cache.php';
const VADE_CACHE_TAPA     = "<?php http_response_code(404); exit; ?>\n";

/**
 * Trae las piezas activas. Devuelve null si la base no contesta — null es
 * "no sé", que no es lo mismo que [] ("no hay piezas"). Confundirlos vaciaría
 * la tienda ante cualquier hipo de red.
 */
function vade_catalogo_de_la_base() {
  if (SUPABASE_KEY === '') return null;
  $url = rtrim(SUPABASE_URL, '/')
       . '/rest/v1/productos?select=slug,precio,envio,datos&activo=eq.true&order=orden.asc';
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 8,
    CURLOPT_HTTPHEADER     => [
      'apikey: ' . SUPABASE_KEY,
      'Authorization: Bearer ' . SUPABASE_KEY,
      'Accept-Profile: ' . SUPABASE_SCHEMA,
    ],
  ]);
  $r    = curl_exec($ch);
  $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  if ($http < 200 || $http >= 300) return null;

  $filas = json_decode((string) $r, true);
  if (!is_array($filas)) return null;

  $out = [];
  foreach ($filas as $f) {
    $d = is_array($f['datos'] ?? null) ? $f['datos'] : [];
    // El precio y el envío SIEMPRE salen de las columnas, nunca de `datos`:
    // son los dos números con los que se cobra y tienen que estar tipados.
    $d['slug']  = $f['slug'];
    $d['price'] = (float) $f['precio'];
    $d['ship']  = (float) $f['envio'];
    // La tienda no lleva stock: la clienta no lo pidió y un contador mal
    // llevado frena ventas. null quiere decir "sin límite".
    $d['stock'] = null;
    $out[] = $d;
  }
  return $out;
}

/** La última copia buena que se guardó en disco, o null si no hay. */
function vade_catalogo_de_cache() {
  if (!is_readable(VADE_CACHE_CATALOGO)) return null;
  $crudo = (string) file_get_contents(VADE_CACHE_CATALOGO);
  // Sacar la tapa antes de leer el JSON.
  if (strpos($crudo, '<?php') === 0) {
    $corte = strpos($crudo, "\n");
    $crudo = $corte === false ? '' : substr($crudo, $corte + 1);
  }
  $j = json_decode($crudo, true);
  return is_array($j) && $j ? $j : null;
}

/**
 * El catálogo que vale, con su origen. Primero la base; si no contesta, la
 * copia en disco. El origen se devuelve para que quien llame pueda decidir:
 * la vitrina sirve igual con una copia de hace un rato, el cobro tal vez no.
 */
function vade_catalogo() {
  $vivo = vade_catalogo_de_la_base();
  if ($vivo !== null && count($vivo)) {
    @file_put_contents(VADE_CACHE_CATALOGO,
      VADE_CACHE_TAPA . json_encode($vivo, JSON_UNESCAPED_UNICODE), LOCK_EX);
    return ['origen' => 'base', 'items' => $vivo];
  }
  $copia = vade_catalogo_de_cache();
  if ($copia !== null) return ['origen' => 'copia', 'items' => $copia];
  return ['origen' => 'nada', 'items' => []];
}

// Cuando otro archivo lo incluye (crear.php) solo quiere las funciones, no la
// respuesta. Se avisa con una constante y no mirando el nombre del script:
// bajo CGI ese dato no siempre viene, y si falla se colaria un JSON en medio
// de la respuesta del cobro.
if (defined('VADE_CATALOGO_SOLO_FUNCIONES')) return;

// ---------------------------------------------------------------------------
// Servido directo: es lo que pide la vitrina al abrir.
// no-store porque un precio editado tiene que verse en la próxima visita, no
// cuando al navegador se le ocurra.
// ---------------------------------------------------------------------------
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$c = vade_catalogo();
if ($c['origen'] === 'nada') {
  // Sin base y sin copia no hay nada honesto que decir. La página se queda con
  // su lista de respaldo, que es justamente para esto.
  http_response_code(503);
  echo json_encode(['ok' => false, 'error' => 'Catálogo no disponible.'], JSON_UNESCAPED_UNICODE);
  exit;
}
echo json_encode(['ok' => true, 'origen' => $c['origen'], 'items' => $c['items']], JSON_UNESCAPED_UNICODE);
