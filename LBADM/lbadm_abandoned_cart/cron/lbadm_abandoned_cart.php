<?php
/**
 * Recordatorio de carrito abandonado — LBADM
 * Archivo: /modules/lbadm_abandoned_cart/cron/lbadm_abandoned_cart.php
 *
 * CONFIGURACIÓN DEL CRON (ejecutar una vez al día es suficiente):
 *   0 10 * * * php /var/www/html/modules/lbadm_abandoned_cart/cron/lbadm_abandoned_cart.php >> /var/log/lbadm_cart.log 2>&1
 *
 * O si se prefiere llamada HTTP (añadir el token correcto):
 *   0 10 * * * curl -s "https://tutienda.com/modules/lbadm_abandoned_cart/cron/lbadm_abandoned_cart.php?token=TOKEN" >> /var/log/lbadm_cart.log 2>&1
 *
 * TABLA REQUERIDA (ejecutar una sola vez en MySQL):
 *   CREATE TABLE IF NOT EXISTS `ps_lbadm_cart_reminder` (
 *     `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
 *     `id_cart`     INT UNSIGNED NOT NULL,
 *     `id_customer` INT UNSIGNED NOT NULL,
 *     `email`       VARCHAR(255) NOT NULL,
 *     `date_sent`   DATETIME NOT NULL,
 *     `status`      TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=enviado, 0=error',
 *     `error_msg`   VARCHAR(512) DEFAULT NULL,
 *     PRIMARY KEY (`id`),
 *     UNIQUE KEY `uq_id_cart` (`id_cart`),
 *     KEY `idx_date_sent` (`date_sent`)
 *   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 */

// ── 1. Bootstrap PrestaShop ─────────────────────────────────────────────────

// Bootstrap PrestaShop para CLI
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'losbuenosairesdemadrid.syspre.sysprovider.com';
$_SERVER['REQUEST_URI'] = '/';

if (!defined('_PS_ADMIN_DIR_')) {
    define('_PS_ADMIN_DIR_', '');
}

$psRoot = dirname(__FILE__, 4);
require_once($psRoot . '/config/config.inc.php');

// No cargar init.php — arranca el FrontController y hace redirects HTTP
// Solo cargar las clases que necesitamos
require_once($psRoot . '/classes/Cart.php');
require_once($psRoot . '/classes/Mail.php');

// ── 2. Seguridad ────────────────────────────────────────────────────────────

$validToken = md5(_COOKIE_KEY_ . 'lbadm_abandoned_cart');
$esCli = (php_sapi_name() === 'cli');

if (!$esCli && Tools::getValue('token') !== $validToken) {
    header('HTTP/1.1 403 Forbidden');
    die('Acceso no autorizado');
}

// ── 3. Helpers ──────────────────────────────────────────────────────────────

/**
 * Escribe una línea en el log con timestamp.
 * Usa echo para que el cron capture la salida en el fichero de log.
 */
function lbadm_log(string $nivel, string $mensaje): void
{
    $linea = sprintf('[%s] [%s] %s', date('Y-m-d H:i:s'), strtoupper($nivel), $mensaje);
    echo $linea . PHP_EOL;
}

/**
 * Crea la tabla de seguimiento si no existe.
 * Se ejecuta en cada llamada pero es un NO-OP si ya existe (IF NOT EXISTS).
 */
function lbadm_ensure_table(): void
{
    $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'lbadm_cart_reminder` (
        `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `id_cart`     INT UNSIGNED NOT NULL,
        `id_customer` INT UNSIGNED NOT NULL,
        `email`       VARCHAR(255) NOT NULL,
        `date_sent`   DATETIME NOT NULL,
        `status`      TINYINT(1) NOT NULL DEFAULT 1 COMMENT \'1=enviado, 0=error\',
        `error_msg`   VARCHAR(512) DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_id_cart` (`id_cart`),
        KEY `idx_date_sent` (`date_sent`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';

    if (!Db::getInstance()->execute($sql)) {
        throw new RuntimeException('No se pudo crear la tabla lbadm_cart_reminder');
    }
}

/**
 * Devuelve true si ya se intentó enviar el recordatorio para este carrito
 * (tanto si fue exitoso como si hubo error previo, para no reintentar indefinidamente).
 */
function lbadm_ya_procesado(int $idCart): bool
{
    $count = (int) Db::getInstance()->getValue(
        'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'lbadm_cart_reminder`
         WHERE `id_cart` = ' . $idCart
    );
    return $count > 0;
}

/**
 * Registra el resultado del envío en la tabla de seguimiento.
 */
function lbadm_registrar(int $idCart, int $idCustomer, string $email, bool $ok, string $error = ''): void
{
    Db::getInstance()->insert(
        'lbadm_cart_reminder',
        [
            'id_cart' => $idCart,
            'id_customer' => $idCustomer,
            'email' => pSQL($email),
            'date_sent' => date('Y-m-d H:i:s'),
            'status' => (int) $ok,
            'error_msg' => $ok ? null : pSQL(substr($error, 0, 512)),
        ],
        false,      // null_values
        true,       // use_cache
        Db::INSERT_IGNORE  // evita fallo si por alguna race-condition se duplica
    );
}

// ── 4. Configuración ────────────────────────────────────────────────────────

// Ventana de carritos a procesar: entre 24 y 25 horas de antigüedad.
// Con el cron a las 10:00 h cada día, esto cubre los carritos abandonados
// el día anterior a esa misma hora — ventana de exactamente 1h de margen.
// Si se necesita mayor precisión, reducir la ventana a HORAS_MIN=23, MAX=25.
$horasMin = 0;
$horasMax = 25;
$idShop = (int) Configuration::get('PS_SHOP_DEFAULT');
$idLang = (int) Configuration::get('PS_LANG_DEFAULT');
$shopName = Configuration::get('PS_SHOP_NAME');
$shopUrl = Context::getContext()->link->getBaseLink($idShop);
$cartUrl = $shopUrl . 'carrito?action=show';

// ── 5. Inicio ───────────────────────────────────────────────────────────────

lbadm_log('info', '=== Inicio proceso carrito abandonado ===');
lbadm_log('info', "Ventana: -{$horasMin}h / -{$horasMax}h | Shop: {$idShop}");

// Garantizar que la tabla existe antes de cualquier consulta
try {
    lbadm_ensure_table();
} catch (RuntimeException $e) {
    lbadm_log('error', 'Error creando tabla: ' . $e->getMessage());
    exit(1);
}

// ── 6. Consulta de carritos abandonados ─────────────────────────────────────
//
// Notas sobre la query:
//  - Se filtra cu.active = 1 para no enviar a cuentas desactivadas.
//  - Se filtra cu.newsletter = 1 si quieres respetar solo suscriptores
//    (comentado por defecto — descomenta si aplica legalmente).
//  - LEFT JOIN con orders garantiza que no haya pedido asociado.
//  - No se une ya con lbadm_cart_reminder para simplificar; el check
//    de duplicado se hace en PHP para mayor claridad.

$sql = '
    SELECT
        c.id_cart,
        c.id_customer,
        c.date_upd,
        cu.firstname,
        cu.lastname,
        cu.email
    FROM `' . _DB_PREFIX_ . 'cart` c
    INNER JOIN `' . _DB_PREFIX_ . 'customer` cu
           ON cu.id_customer = c.id_customer
          AND cu.active = 1
          -- AND cu.newsletter = 1
    LEFT JOIN `' . _DB_PREFIX_ . 'orders` o
           ON o.id_cart = c.id_cart
    WHERE o.id_order  IS NULL
      AND c.id_customer  > 0
      AND c.id_shop      = ' . $idShop . '
      AND c.date_upd < DATE_SUB(NOW(), INTERVAL ' . $horasMin . ' HOUR)
      AND c.date_upd > DATE_SUB(NOW(), INTERVAL ' . $horasMax . ' HOUR)
    ORDER BY c.date_upd ASC
';

$carts = Db::getInstance()->executeS($sql);

if (empty($carts)) {
    lbadm_log('info', 'Sin carritos en la ventana de tiempo. Proceso terminado.');
    exit(0);
}

lbadm_log('info', 'Carritos encontrados en ventana: ' . count($carts));

// ── 7. Bucle de envío ───────────────────────────────────────────────────────

$enviados = 0;
$omitidos = 0;
$errores = 0;

foreach ($carts as $cart) {

    $idCart = (int) $cart['id_cart'];
    $idCustomer = (int) $cart['id_customer'];
    $email = $cart['email'];
    $nombre = $cart['firstname'] . ' ' . $cart['lastname'];

    // 7a. Saltar si ya fue procesado (enviado o fallido anteriormente)
    if (lbadm_ya_procesado($idCart)) {
        $omitidos++;
        continue;
    }

    // 7b. Cargar productos del carrito
    $cartObj = new Cart($idCart);
    $productos = $cartObj->getProducts();

    if (empty($productos)) {
        lbadm_log('info', "Carrito #{$idCart} vacío — omitido.");
        $omitidos++;
        continue;
    }

    // 7c. Construir lista de productos (usada en la plantilla TXT)
    $listaProductos = '';
    foreach ($productos as $prod) {
        $listaProductos .= '• ' . $prod['name']
            . ' ×' . (int) $prod['quantity']
            . '  —  ' . Tools::displayPrice($prod['total_wt']) . "\n";
    }
    $listaProductos = trim($listaProductos);

    // 7d. Variables de plantilla
    $templateVars = [
        '{firstname}' => $cart['firstname'],
        '{lastname}' => $cart['lastname'],
        '{cart_url}' => $cartUrl,
        '{shop_name}' => $shopName,
        '{products}' => $listaProductos,
        '{shop_url}' => $shopUrl,
    ];

    // 7e. Enviar email
    $enviado = false;
    $errorMsg = '';

    try {
        $enviado = Mail::Send(
            $idLang,
            'lbadm_abandoned_cart',                         // nombre de la plantilla
            Mail::l('Olvidaste algo en tu carrito 🛒'),     // asunto
            $templateVars,
            $email,
            $nombre,
            null,   // from
            null,   // from name
            null,   // file attachment
            null,   // mode smtp
            _PS_MODULE_DIR_ . 'lbadm_abandoned_cart/mails/',
            false,  // die on error
            $idShop
        );

        if (!$enviado) {
            $errorMsg = 'Mail::Send devolvió false (revisar configuración SMTP)';
        }
    } catch (Exception $e) {
        $errorMsg = $e->getMessage();
    }

    // 7f. Registrar resultado en BD
    lbadm_registrar($idCart, $idCustomer, $email, $enviado, $errorMsg);

    if ($enviado) {
        lbadm_log('info', "✔ Email enviado a: {$email} (carrito #{$idCart})");
        $enviados++;
    } else {
        lbadm_log('error', "✘ Fallo al enviar a: {$email} (carrito #{$idCart}) — {$errorMsg}");
        $errores++;
    }
}

// ── 8. Resumen ──────────────────────────────────────────────────────────────

lbadm_log('info', "=== Resumen: enviados={$enviados} | omitidos={$omitidos} | errores={$errores} ===");
exit($errores > 0 ? 1 : 0); // exit code 1 si hubo errores → el cron lo puede monitorizar