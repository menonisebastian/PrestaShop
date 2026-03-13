<?php
/**
 * Recordatorio de carrito abandonado - LBADM
 * Se ejecuta via cron cada hora
 * Envía email a carritos abandonados hace entre 24h y 25h
 */

// Cargar PrestaShop
require_once(dirname(__FILE__) . '/../../config/config.inc.php');
require_once(dirname(__FILE__) . '/../../init.php');

// Seguridad: solo ejecutar desde CLI o con token
$token = Tools::getValue('token');
$validToken = md5(_COOKIE_KEY_ . 'lbadm_abandoned_cart');
if (php_sapi_name() !== 'cli' && $token !== $validToken) {
    die('Acceso no autorizado');
}

// Configuración
$horasMin = 24;
$horasMax = 25;
$idShop   = (int)Configuration::get('PS_SHOP_DEFAULT');
$shopUrl  = Context::getContext()->link->getBaseLink($idShop);

// Buscar carritos abandonados en la ventana de 24-25 horas
$sql = '
    SELECT 
        c.id_cart,
        c.id_customer,
        c.date_upd,
        cu.firstname,
        cu.lastname,
        cu.email
    FROM `' . _DB_PREFIX_ . 'cart` c
    INNER JOIN `' . _DB_PREFIX_ . 'customer` cu ON c.id_customer = cu.id_customer
    LEFT JOIN `' . _DB_PREFIX_ . 'orders` o ON o.id_cart = c.id_cart
    WHERE o.id_order IS NULL
      AND c.id_customer > 0
      AND c.id_shop = ' . $idShop . '
      AND c.date_upd < DATE_SUB(NOW(), INTERVAL ' . $horasMin . ' HOUR)
      AND c.date_upd > DATE_SUB(NOW(), INTERVAL ' . $horasMax . ' HOUR)
';

$carts = Db::getInstance()->executeS($sql);

if (empty($carts)) {
    echo date('Y-m-d H:i:s') . " - Sin carritos abandonados en esta ventana.\n";
    exit;
}

foreach ($carts as $cart) {

    // Comprobar que no se le ha enviado ya el email
    $yaEnviado = Db::getInstance()->getValue('
        SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'lbadm_cart_reminder`
        WHERE id_cart = ' . (int)$cart['id_cart']
    );

    if ($yaEnviado) {
        continue;
    }

    // Obtener productos del carrito
    $cartObj   = new Cart((int)$cart['id_cart']);
    $productos = $cartObj->getProducts();

    if (empty($productos)) {
        continue;
    }

    // Construir lista de productos para el email
    $listaProductos = '';
    foreach ($productos as $prod) {
        $listaProductos .= '- ' . $prod['name'] . ' x' . (int)$prod['quantity'] 
                         . ' — ' . Tools::displayPrice($prod['total_wt']) . "\n";
    }

    // Enviar email
    $templateVars = [
        '{firstname}'   => $cart['firstname'],
        '{lastname}'    => $cart['lastname'],
        '{cart_url}'    => $shopUrl . 'carrito?action=show',
        '{shop_name}'   => Configuration::get('PS_SHOP_NAME'),
        '{products}'    => $listaProductos,
        '{shop_url}'    => $shopUrl,
    ];

    $enviado = Mail::Send(
        (int)Configuration::get('PS_LANG_DEFAULT'),
        'lbadm_abandoned_cart',
        'Olvidaste algo en tu carrito 🛒',
        $templateVars,
        $cart['email'],
        $cart['firstname'] . ' ' . $cart['lastname'],
        null,
        null,
        null,
        null,
        _PS_MODULE_DIR_ . 'lbadm_abandoned_cart/mails/'
    );

    if ($enviado) {
        // Registrar envío para no repetirlo
        Db::getInstance()->insert('lbadm_cart_reminder', [
            'id_cart'    => (int)$cart['id_cart'],
            'id_customer'=> (int)$cart['id_customer'],
            'email'      => pSQL($cart['email']),
            'date_sent'  => date('Y-m-d H:i:s'),
        ]);
        echo date('Y-m-d H:i:s') . " - Email enviado a: " . $cart['email'] . "\n";
    }
}

echo date('Y-m-d H:i:s') . " - Proceso completado.\n";
```

---

## Archivo 2 — Template del email (HTML)

Crear en:
```
/modules/lbadm_abandoned_cart/mails/es/lbadm_abandoned_cart.html