<?php
/**
 * Recordatorio de carrito abandonado — LBADM
 * Archivo: /modules/lbadm_abandoned_cart/cron/lbadm_abandoned_cart.php
 *
 * CONFIGURACIÓN DEL CRON (ejecutar una vez al día es suficiente):
 * 0 10 * * * php /var/www/html/modules/lbadm_abandoned_cart/cron/lbadm_abandoned_cart.php >> /var/log/lbadm_cart.log 2>&1
 *
 * O si se prefiere llamada HTTP (añadir el token correcto):
 * 0 10 * * * curl -s "https://tutienda.com/modules/lbadm_abandoned_cart/cron/lbadm_abandoned_cart.php?token=TOKEN" >> /var/log/lbadm_cart.log 2>&1
 *
 * TABLA REQUERIDA (ejecutar una sola vez en MySQL):
 * CREATE TABLE IF NOT EXISTS `ps_lbadm_cart_reminder` (
 * `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
 * `id_cart`     INT UNSIGNED NOT NULL,
 * `id_customer` INT UNSIGNED NOT NULL,
 * `email`       VARCHAR(255) NOT NULL,
 * `date_sent`   DATETIME NOT NULL,
 * `status`      TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=enviado, 0=error',
 * `error_msg`   VARCHAR(512) DEFAULT NULL,
 * PRIMARY KEY (`id`),
 * UNIQUE KEY `uq_id_cart` (`id_cart`),
 * KEY `idx_date_sent` (`date_sent`)
 * ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
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
 */
function lbadm_log(string $nivel, string $mensaje): void
{
    $linea = sprintf('[%s] [%s] %s', date('Y-m-d H:i:s'), strtoupper($nivel), $mensaje);
    echo $linea . PHP_EOL;
}

/**
 * Crea la tabla de seguimiento si no existe.
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
 * Devuelve true si ya se procesó esta VERSIÓN del carrito.
 * Si el carrito se actualizó (fecha_upd mayor a nuestro date_sent),
 * devolverá false para volver a enviar.
 */
function lbadm_ya_procesado(int $idCart, string $cartDateUpd): bool
{
    $dateSent = Db::getInstance()->getValue(
        'SELECT `date_sent` FROM `' . _DB_PREFIX_ . 'lbadm_cart_reminder`
         WHERE `id_cart` = ' . $idCart
    );

    if (!$dateSent) {
        return false; // Nunca se ha enviado nada para este carrito
    }

    // Si la fecha de envío es posterior o igual a la última actualización del carrito,
    // significa que ya avisamos sobre los items actuales.
    if (strtotime($dateSent) >= strtotime($cartDateUpd)) {
        return true;
    }

    // El carrito se modificó después de nuestro último envío.
    return false;
}

/**
 * Registra el resultado del envío.
 * Si el carrito ya existía, actualiza su fecha de envío y estado (ON DUPLICATE KEY UPDATE).
 */
function lbadm_registrar(int $idCart, int $idCustomer, string $email, bool $ok, string $error = ''): void
{
    $db = Db::getInstance();
    $status = (int) $ok;
    $dateSent = date('Y-m-d H:i:s');
    $errorMsg = $ok ? 'NULL' : '\'' . pSQL(substr($error, 0, 512)) . '\'';

    $sql = 'INSERT INTO `' . _DB_PREFIX_ . 'lbadm_cart_reminder`
        (`id_cart`, `id_customer`, `email`, `date_sent`, `status`, `error_msg`)
        VALUES (
            ' . $idCart . ',
            ' . $idCustomer . ',
            \'' . pSQL($email) . '\',
            \'' . pSQL($dateSent) . '\',
            ' . $status . ',
            ' . $errorMsg . '
        )
        ON DUPLICATE KEY UPDATE
            `date_sent` = VALUES(`date_sent`),
            `status` = VALUES(`status`),
            `error_msg` = VALUES(`error_msg`)';

    $db->execute($sql);
}

// ── 4. Configuración ────────────────────────────────────────────────────────

$horasMin = 0;
$horasMax = 25;
$idShop = (int) Configuration::get('PS_SHOP_DEFAULT');
$idLang = (int) Configuration::get('PS_LANG_DEFAULT');
$shopName = Configuration::get('PS_SHOP_NAME');

// MOCK DEL CONTEXTO: Evita los Warnings de iso_code en CLI
$context = Context::getContext();
if (!Validate::isLoadedObject($context->currency)) {
    $context->currency = new Currency((int) Configuration::get('PS_CURRENCY_DEFAULT'));
}
if (!Validate::isLoadedObject($context->language)) {
    $context->language = new Language($idLang);
}
if (!Validate::isLoadedObject($context->shop)) {
    $context->shop = new Shop($idShop);
}

$shopUrl = $context->link->getBaseLink($idShop);
$cartUrl = $shopUrl . 'carrito?action=show';
$shopLogoUrl = $shopUrl . 'img/' . Configuration::get('PS_LOGO');

// ── 5. Inicio ───────────────────────────────────────────────────────────────

lbadm_log('info', '=== Inicio proceso carrito abandonado ===');
lbadm_log('info', "Ventana: -{$horasMin}h / -{$horasMax}h | Shop: {$idShop}");

try {
    lbadm_ensure_table();
} catch (RuntimeException $e) {
    lbadm_log('error', 'Error creando tabla: ' . $e->getMessage());
    exit(1);
}

// ── 6. Consulta de carritos abandonados ─────────────────────────────────────

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

// Umbral de euros para envío gratis
define('LBADM_SHIPPING_THRESHOLD', 100);

foreach ($carts as $cart) {

    $idCart = (int) $cart['id_cart'];
    $idCustomer = (int) $cart['id_customer'];
    $cartDateUpd = $cart['date_upd'];
    $email = $cart['email'];
    $nombre = $cart['firstname'] . ' ' . $cart['lastname'];

    // 7a. Comprobar si YA se envió para esta versión (fecha_upd) del carrito
    if (lbadm_ya_procesado($idCart, $cartDateUpd)) {
        $omitidos++;
        continue;
    }

    // 7b. Cargar productos mediante consulta directa
    $sqlProducts = '
        SELECT cp.id_product, cp.id_product_attribute, cp.quantity, pl.name
        FROM `' . _DB_PREFIX_ . 'cart_product` cp
        LEFT JOIN `' . _DB_PREFIX_ . 'product_lang` pl
               ON (pl.id_product = cp.id_product AND pl.id_lang = ' . (int) $idLang . ' AND pl.id_shop = ' . (int) $idShop . ')
        WHERE cp.id_cart = ' . (int) $idCart;

    $productos = Db::getInstance()->executeS($sqlProducts);

    // Si borraron todos los productos, lo ignoramos sin registrar nada
    if (empty($productos)) {
        lbadm_log('info', "Carrito #{$idCart} vacío (cliente borró todo) — omitido.");
        $omitidos++;
        continue;
    }

    // 7c. Construir lista de productos Y calcular total del carrito
    $listaProductos = '';
    $totalCarrito = 0.0;

    foreach ($productos as $prod) {
        $price = Product::getPriceStatic((int) $prod['id_product'], true, (int) $prod['id_product_attribute']);
        $total_wt = $price * (int) $prod['quantity'];
        $totalCarrito += $total_wt;

        $listaProductos .= '• ' . $prod['name']
            . ' ×' . (int) $prod['quantity']
            . '  —  ' . Tools::displayPrice($total_wt, $context->currency) . "\n";
    }
    $listaProductos = trim($listaProductos);

    // 7d. Calcular aviso de envío gratis y construir su bloque HTML
    $threshold = LBADM_SHIPPING_THRESHOLD;
    $shippingConseguido = ($totalCarrito >= $threshold);
    $shippingRestante = number_format(max(0, $threshold - $totalCarrito), 2, ',', '');
    $shippingPorcentaje = min(100, (int) round(($totalCarrito / $threshold) * 100));

    if ($shippingConseguido) {
        $noticeBlock = '
        <div style="background-color:#e8f5e9; border:1px solid #4caf50;
                    border-left:4px solid #4caf50; border-radius:4px;
                    padding:10px 14px; font-family:Arial,sans-serif;
                    font-size:14px; color:#2e7d32; text-align:center;
                    margin-bottom:20px;">
          &#127881; &#161;Tienes env&#237;o gratis!
        </div>';
    } else {
        $pct = $shippingPorcentaje;
        $noticeBlock = '
        <div style="background-color:#fff8e1; border:1px solid #f5c518;
                    border-left:4px solid #f5c518; border-radius:4px;
                    padding:10px 14px; font-family:Arial,sans-serif;
                    font-size:14px; color:#555555; text-align:center;
                    margin-bottom:8px;">
          &#128666; &#161;Te faltan <strong>' . $shippingRestante . '&#8364;</strong> para env&#237;o gratis!
        </div>
        <table width="100%" cellpadding="0" cellspacing="0" border="0"
               style="background-color:#e0e0e0; border-radius:50px; height:10px; margin-bottom:20px;">
          <tr>
            <td width="' . $pct . '%" style="background-color:#f5c518; height:10px; border-radius:50px; font-size:0; line-height:0;">&nbsp;</td>
            <td style="font-size:0; line-height:0;">&nbsp;</td>
          </tr>
        </table>';
    }

    // 7e. Leer la plantilla HTML y sustituir todas las variables manualmente
    //     (evita que Mail::Send escape el HTML del bloque del aviso)
    $mailDir = _PS_MODULE_DIR_ . 'lbadm_abandoned_cart/mails/es/';
    $htmlTemplate = file_get_contents($mailDir . 'lbadm_abandoned_cart.html');

    $allVars = [
        '{firstname}' => $cart['firstname'],
        '{lastname}' => $cart['lastname'],
        '{cart_url}' => $cartUrl,
        '{shop_name}' => $shopName,
        '{products}' => $listaProductos,
        '{shop_url}' => $shopUrl,
        '{shop_logo}' => $shopLogoUrl,
        '{shipping_notice}' => $noticeBlock,
    ];

    $htmlFinal = str_replace(array_keys($allVars), array_values($allVars), $htmlTemplate);

    // Leer y sustituir también la plantilla .txt (Mail::Send la requiere junto al .html)
    $txtTemplate = file_get_contents($mailDir . 'lbadm_abandoned_cart.txt');
    // {shipping_notice} no aplica en plain text — se sustituye por una línea legible
    $txtVars = $allVars;
    if ($shippingConseguido) {
        $txtVars['{shipping_notice}'] = '🎉 ¡Tienes envío gratis!';
    } else {
        $txtVars['{shipping_notice}'] = '🚚 Te faltan ' . $shippingRestante . '€ para envío gratis.';
    }
    $txtFinal = str_replace(array_keys($txtVars), array_values($txtVars), $txtTemplate);

    // Escribir ambos archivos temporales con nombre único por carrito
    $templateName = 'lbadm_ac_tmp_' . $idCart;
    $tmpHtml = $mailDir . $templateName . '.html';
    $tmpTxt = $mailDir . $templateName . '.txt';
    file_put_contents($tmpHtml, $htmlFinal);
    file_put_contents($tmpTxt, $txtFinal);

    // 7f. Enviar email apuntando a los archivos temporales (variables ya sustituidas)
    $enviado = false;
    $errorMsg = '';

    try {
        $enviado = Mail::Send(
            $idLang,
            $templateName,
            Mail::l('Olvidaste algo en tu carrito 🛒'),
            [],          // templateVars vacío: todo ya está reemplazado
            $email,
            $nombre,
            null,
            null,
            null,
            null,
            $mailDir,
            false,
            $idShop
        );

        if (!$enviado) {
            $errorMsg = 'Mail::Send devolvió false (revisar configuración SMTP)';
        }
    } catch (Exception $e) {
        $errorMsg = $e->getMessage();
    }

    // Eliminar ambos archivos temporales independientemente del resultado
    @unlink($tmpHtml);
    @unlink($tmpTxt);

    // 7g. Registrar resultado en BD
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
exit($errores > 0 ? 1 : 0);