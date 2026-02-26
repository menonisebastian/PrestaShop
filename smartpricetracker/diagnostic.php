<?php
/**
 * Script de diagnóstico para Smart Price Tracker
 * Ejecuta este archivo desde el navegador: tutienda.com/modules/smartpricetracker/diagnostic.php
 */

// Configuración de PrestaShop
define('_PS_ADMIN_DIR_', getcwd() . '/../../admin');
require_once dirname(__FILE__) . '/../../config/config.inc.php';

echo "<h1>Diagnóstico Smart Price Tracker</h1>";
echo "<hr>";

// 1. Verificar que el módulo está instalado
echo "<h2>1. Estado del Módulo</h2>";
$module = Module::getInstanceByName('smartpricetracker');
if ($module && $module->id) {
    echo "✅ Módulo instalado (ID: " . $module->id . ")<br>";
    echo "✅ Versión: " . $module->version . "<br>";
} else {
    echo "❌ El módulo no está instalado<br>";
}

// 2. Verificar que el Tab existe
echo "<h2>2. Controlador AJAX (Tab)</h2>";
$id_tab = (int)Tab::getIdFromClassName('AdminSmartPriceTrackerAjax');
if ($id_tab) {
    $tab = new Tab($id_tab);
    echo "✅ Tab encontrado (ID: " . $id_tab . ")<br>";
    echo "✅ Activo: " . ($tab->active ? 'Sí' : 'No') . "<br>";
    echo "✅ Módulo asociado: " . $tab->module . "<br>";
} else {
    echo "❌ El Tab no existe<br>";
    echo "🔧 Solución: Reinstala el módulo<br>";
}

// 3. Verificar archivos del módulo
echo "<h2>3. Archivos del Módulo</h2>";
$files = [
    'smartpricetracker.php' => dirname(__FILE__) . '/smartpricetracker.php',
    'SmartPriceScraper.php' => dirname(__FILE__) . '/classes/SmartPriceScraper.php',
    'AdminSmartPriceTrackerAjaxController.php' => dirname(__FILE__) . '/controllers/admin/AdminSmartPriceTrackerAjaxController.php',
    'admin_products_extra.tpl' => dirname(__FILE__) . '/views/templates/hook/admin_products_extra.tpl'
];

foreach ($files as $name => $path) {
    if (file_exists($path)) {
        echo "✅ $name existe<br>";
    } else {
        echo "❌ $name NO ENCONTRADO en: $path<br>";
    }
}

// 4. Verificar el método searchCompetitorsByTitle
echo "<h2>4. Método searchCompetitorsByTitle</h2>";
if (file_exists(dirname(__FILE__) . '/classes/SmartPriceScraper.php')) {
    require_once dirname(__FILE__) . '/classes/SmartPriceScraper.php';
    if (method_exists('SmartPriceScraper', 'searchCompetitorsByTitle')) {
        echo "✅ Método searchCompetitorsByTitle existe<br>";
        
        // Probar el método con una búsqueda simple
        echo "<h3>Probando búsqueda...</h3>";
        try {
            $result = SmartPriceScraper::searchCompetitorsByTitle('iPhone 15');
            if ($result !== false && is_array($result)) {
                echo "✅ Búsqueda exitosa. Encontrados: " . count($result) . " resultados<br>";
                if (count($result) > 0) {
                    echo "<pre>" . print_r($result[0], true) . "</pre>";
                }
            } else {
                echo "⚠️ La búsqueda no devolvió resultados (puede ser normal si Google bloquea)<br>";
            }
        } catch (Exception $e) {
            echo "❌ Error al ejecutar búsqueda: " . $e->getMessage() . "<br>";
        }
    } else {
        echo "❌ Método searchCompetitorsByTitle NO EXISTE<br>";
        echo "🔧 Solución: Reemplaza el archivo SmartPriceScraper.php<br>";
    }
} else {
    echo "❌ Archivo SmartPriceScraper.php no encontrado<br>";
}

// 5. Verificar la base de datos
echo "<h2>5. Tabla de Base de Datos</h2>";
$sql = 'SHOW TABLES LIKE "' . _DB_PREFIX_ . 'smart_competitor_price"';
$result = Db::getInstance()->executeS($sql);
if ($result) {
    echo "✅ Tabla smart_competitor_price existe<br>";
    
    // Contar registros
    $count = Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'smart_competitor_price`');
    echo "📊 Registros en la tabla: " . $count . "<br>";
} else {
    echo "❌ Tabla smart_competitor_price NO EXISTE<br>";
    echo "🔧 Solución: Reinstala el módulo<br>";
}

// 6. Verificar el link AJAX
echo "<h2>6. Link del Controlador AJAX</h2>";
try {
    $context = Context::getContext();
    $ajax_link = $context->link->getAdminLink('AdminSmartPriceTrackerAjax');
    echo "✅ URL AJAX generada: <a href='$ajax_link' target='_blank'>$ajax_link</a><br>";
    echo "🔧 Prueba acceder a esta URL en otra pestaña<br>";
} catch (Exception $e) {
    echo "❌ Error al generar link: " . $e->getMessage() . "<br>";
}

// 7. Verificar PHP extensions
echo "<h2>7. Extensiones PHP Requeridas</h2>";
$extensions = ['curl', 'dom', 'json', 'mbstring'];
foreach ($extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "✅ $ext está instalada<br>";
    } else {
        echo "❌ $ext NO está instalada<br>";
    }
}

// 8. Test de conectividad
echo "<h2>8. Test de Conectividad</h2>";
echo "Probando conexión a Google...<br>";
$ch = curl_init('https://www.google.com');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$result = curl_exec($ch);
$error = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode == 200) {
    echo "✅ Conexión a Google exitosa (HTTP $httpCode)<br>";
} else {
    echo "❌ Error de conexión a Google: $error (HTTP $httpCode)<br>";
}

echo "<hr>";
echo "<h2>Resumen</h2>";
echo "<p>Si todos los checks están en verde ✅, el módulo debería funcionar.</p>";
echo "<p>Si hay errores ❌, sigue las soluciones 🔧 indicadas.</p>";
