<?php
// ============================================================================
// VADE RETRO — subir una foto desde el panel
//
// Una pieza nueva trae fotos nuevas. Sin esto habría que entrar al Hostinger a
// mano por cada foto, y entonces "agregar un producto" no sería algo que la
// clienta pueda hacer sola.
//
// QUIÉN PUEDE: solo quien tenga una sesión válida del panel. El navegador manda
// su token de Supabase y acá se le pregunta a Supabase de quién es. No alcanza
// con tener un token cualquiera: tiene que ser el UUID del administrador, el
// mismo que figura en las reglas de la base. Sin esa comprobación esto sería
// una carpeta abierta donde cualquiera puede dejar archivos en el sitio.
//
// QUÉ SE ACEPTA: solo imágenes, y se comprueba abriéndolas, no leyendo el
// nombre ni lo que diga el navegador. Un .php renombrado a .jpg no pasa
// getimagesize(). El nombre final lo inventa el servidor, así que tampoco se
// puede elegir dónde cae ni pisar un archivo que ya existe.
// ============================================================================

if (!defined('VADE')) define('VADE', 1);
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

function fin($c, $d) { http_response_code($c); echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }

const ADMIN_UUID = 'c25d3efd-6061-4d0c-91fe-2d3dc08e6894';
const DESTINO    = __DIR__ . '/../assets';
const TOPE_BYTES = 8 * 1024 * 1024;   // 8 MB: una foto de celular entra holgada
const LADO_MAX   = 1400;              // más grande que esto no se ve mejor, solo pesa

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fin(405, ['ok' => false, 'error' => 'Método no permitido.']);

// --- 1. ¿quién sos? ---------------------------------------------------------
$cab = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
if (stripos($cab, 'Bearer ') !== 0) fin(401, ['ok' => false, 'error' => 'Falta la sesión.']);
$token = trim(substr($cab, 7));

$ch = curl_init(rtrim(SUPABASE_URL, '/') . '/auth/v1/user');
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_TIMEOUT        => 10,
  CURLOPT_HTTPHEADER     => ['apikey: ' . SUPABASE_KEY, 'Authorization: Bearer ' . $token],
]);
$r    = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$user = json_decode((string) $r, true);
if ($http < 200 || $http >= 300 || empty($user['id'])) {
  fin(401, ['ok' => false, 'error' => 'La sesión no vale. Volvé a entrar al panel.']);
}
if ($user['id'] !== ADMIN_UUID) fin(403, ['ok' => false, 'error' => 'Esa cuenta no administra la tienda.']);

// --- 2. ¿qué mandaste? ------------------------------------------------------
if (empty($_FILES['foto']) || ($_FILES['foto']['error'] ?? 1) !== UPLOAD_ERR_OK) {
  // El tope de PHP suele ser más chico que el nuestro y avisa distinto.
  $e = $_FILES['foto']['error'] ?? UPLOAD_ERR_NO_FILE;
  $msg = ($e === UPLOAD_ERR_INI_SIZE || $e === UPLOAD_ERR_FORM_SIZE)
       ? 'La foto pesa más de lo que acepta el servidor.'
       : 'No llegó ninguna foto.';
  fin(400, ['ok' => false, 'error' => $msg]);
}
$tmp = $_FILES['foto']['tmp_name'];
if (filesize($tmp) > TOPE_BYTES) fin(400, ['ok' => false, 'error' => 'La foto pesa más de 8 MB.']);

// Abrirla es la prueba: si no es una imagen de verdad, esto devuelve false.
$info = @getimagesize($tmp);
$tipos = [IMAGETYPE_JPEG => 'jpeg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
if (!$info || !isset($tipos[$info[2]])) {
  fin(400, ['ok' => false, 'error' => 'Eso no es una foto JPG, PNG ni WEBP.']);
}

// --- 3. guardarla -----------------------------------------------------------
// El nombre lo pone el servidor. Si viniera del navegador se podría mandar algo
// como "../pago/config.php" y escribir fuera de la carpeta de fotos.
$base = strtolower(preg_replace('/[^a-z0-9]+/i', '-', (string) ($_POST['nombre'] ?? 'foto')));
$base = trim($base, '-');
if ($base === '' || strlen($base) > 40) $base = 'foto';
$nombre = $base . '-' . substr(bin2hex(random_bytes(4)), 0, 8);

if (!is_dir(DESTINO) || !is_writable(DESTINO)) {
  fin(500, ['ok' => false, 'error' => 'La carpeta assets no se puede escribir. Ponele permiso de escritura.']);
}

// Se reescribe a WEBP y se achica: el resto del sitio es webp, pesan la mitad,
// y así una foto de 12 megapíxeles no frena la tienda en un celular. Si esta
// instalación de PHP no trae GD, se guarda tal cual antes que perder la foto.
$guardado = false;
$destino  = DESTINO . '/' . $nombre . '.webp';
if (function_exists('imagewebp') && function_exists('imagecreatefromjpeg')) {
  $abrir = [
    'jpeg' => 'imagecreatefromjpeg',
    'png'  => 'imagecreatefrompng',
    'webp' => 'imagecreatefromwebp',
  ][$tipos[$info[2]]];
  $im = function_exists($abrir) ? @$abrir($tmp) : false;
  if ($im) {
    $w = imagesx($im); $h = imagesy($im);
    $k = min(1, LADO_MAX / max($w, $h));
    if ($k < 1) {
      $chico = imagescale($im, (int) round($w * $k), (int) round($h * $k));
      if ($chico) { imagedestroy($im); $im = $chico; }
    }
    $guardado = @imagewebp($im, $destino, 84);
    imagedestroy($im);
  }
}
if (!$guardado) {
  $ext = ['jpeg' => 'jpg', 'png' => 'png', 'webp' => 'webp'][$tipos[$info[2]]];
  $destino = DESTINO . '/' . $nombre . '.' . $ext;
  if (!@move_uploaded_file($tmp, $destino)) {
    fin(500, ['ok' => false, 'error' => 'No se pudo guardar la foto.']);
  }
}

fin(200, [
  'ok'   => true,
  // La ruta tal como la escribe el catálogo, lista para pegar en la ficha.
  'ruta' => 'assets/' . basename($destino),
  'peso' => filesize($destino),
]);
