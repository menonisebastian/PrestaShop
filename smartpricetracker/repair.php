<?php
/**
 * Script de reparación para Smart Price Tracker
 * Ejecuta desde: tutienda.com/modules/smartpricetracker/repair.php
 */

define('_PS_ADMIN_DIR_', getcwd() . '/../../admin');
require_once dirname(__FILE__) . '/../../config/config.inc.php';

echo "<h1>🔧 Reparación Smart Price Tracker</h1>";
echo "<hr>";

$errors = [];
$success = [];

// 1. Verificar y reinstalar Tab
echo "<h2>1. Reparando Tab del Controlador AJAX</h2>";

$id_tab = (int)Tab::getIdFromClassName('AdminSmartPriceTrackerAjax');
if ($id_tab) {
    echo "ℹ️ Tab existente encontrado (ID: $id_tab). Eliminando...<br>";
    $tab = new Tab($id_tab);
    if ($tab->delete()) {
        $success[] = "Tab anterior eliminado correctamente";
    }
}

// Crear nuevo Tab
$tab = new Tab();
$tab->active = 1;
$tab->class_name = 'AdminSmartPriceTrackerAjax';
$tab->name = array();
foreach (Language::getLanguages(true) as $lang) {
    $tab->name[$lang['id_lang']] = 'Smart Price Tracker Ajax';
}
$tab->id_parent = -1;
$tab->module = 'smartpricetracker';

if ($tab->add()) {
    $success[] = "✅ Tab creado correctamente (ID: " . $tab->id . ")";
    echo "✅ Tab creado correctamente (ID: " . $tab->id . ")<br>";
} else {
    $errors[] = "❌ Error al crear el Tab";
    echo "❌ Error al crear el Tab<br>";
}

// 2. Verificar estructura de carpetas
echo "<h2>2. Verificando Estructura de Carpetas</h2>";

$directories = [
    'classes',
    'controllers',
    'controllers/admin',
    'views',
    'views/templates',
    'views/templates/hook'
];

foreach ($directories as $dir) {
    $fullPath = dirname(__FILE__) . '/' . $dir;
    if (!is_dir($fullPath)) {
        if (mkdir($fullPath, 0755, true)) {
            $success[] = "✅ Carpeta creada: $dir";
            echo "✅ Carpeta creada: $dir<br>";
        } else {
            $errors[] = "❌ No se pudo crear: $dir";
            echo "❌ No se pudo crear: $dir<br>";
        }
    } else {
        echo "✅ Carpeta existe: $dir<br>";
    }
}

// 3. Verificar permisos de archivos
echo "<h2>3. Verificando Permisos</h2>";

$files_to_check = [
    'smartpricetracker.php',
    'classes/SmartPriceScraper.php',
    'controllers/admin/AdminSmartPriceTrackerAjaxController.php'
];

foreach ($files_to_check as $file) {
    $fullPath = dirname(__FILE__) . '/' . $file;
    if (file_exists($fullPath)) {
        if (is_readable($fullPath)) {
            echo "✅ $file es legible<br>";
        } else {
            $errors[] = "❌ $file NO es legible";
            echo "❌ $file NO es legible<br>";
        }
    } else {
        $errors[] = "❌ $file NO existe";
        echo "❌ $file NO existe en: $fullPath<br>";
    }
}

// 4. Limpiar caché de PrestaShop
echo "<h2>4. Limpiando Caché</h2>";

$cache_dirs = [
    _PS_CACHE_DIR_ . 'class_index.php',
    _PS_CACHE_DIR_ . 'smarty/compile/*',
    _PS_CACHE_DIR_ . 'smarty/cache/*'
];

foreach ($cache_dirs as $cache) {
    if (strpos($cache, '*') !== false) {
        // Es un patrón con wildcard
        $files = glob($cache);
        if ($files) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
        }
        echo "✅ Limpiado: $cache<br>";
    } else {
        if (file_exists($cache) && @unlink($cache)) {
            echo "✅ Eliminado: $cache<br>";
        }
    }
}

$success[] = "✅ Caché limpiada";

// 5. Verificar tabla de BD
echo "<h2>5. Verificando Base de Datos</h2>";

$sql = 'SHOW TABLES LIKE "' . _DB_PREFIX_ . 'smart_competitor_price"';
$result = Db::getInstance()->executeS($sql);

if (!$result) {
    echo "ℹ️ Tabla no existe. Creando...<br>";
    
    $create_sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_competitor_price` (
        `id_product` INT(10) UNSIGNED NOT NULL PRIMARY KEY,
        `search_term` VARCHAR(255) NOT NULL,
        `competitors_data` TEXT NOT NULL,
        `last_scan` DATETIME NOT NULL
    ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';
    
    if (Db::getInstance()->execute($create_sql)) {
        $success[] = "✅ Tabla creada correctamente";
        echo "✅ Tabla creada correctamente<br>";
    } else {
        $errors[] = "❌ Error al crear la tabla";
        echo "❌ Error al crear la tabla<br>";
    }
} else {
    echo "✅ Tabla existe<br>";
}

// 6. Test del método searchCompetitorsByTitle
echo "<h2>6. Probando Método de Búsqueda</h2>";

if (file_exists(dirname(__FILE__) . '/classes/SmartPriceScraper.php')) {
    require_once dirname(__FILE__) . '/classes/SmartPriceScraper.php';
    
    if (method_exists('SmartPriceScraper', 'searchCompetitorsByTitle')) {
        echo "✅ Método searchCompetitorsByTitle existe<br>";
        $success[] = "✅ Método de búsqueda disponible";
    } else {
        $errors[] = "❌ CRÍTICO: Método searchCompetitorsByTitle NO existe";
        echo "❌ <strong>CRÍTICO:</strong> Método searchCompetitorsByTitle NO existe<br>";
        echo "🔧 <strong>SOLUCIÓN:</strong> Debes reemplazar el archivo classes/SmartPriceScraper.php con la versión actualizada<br>";
    }
} else {
    $errors[] = "❌ Archivo SmartPriceScraper.php no encontrado";
    echo "❌ Archivo SmartPriceScraper.php no encontrado<br>";
}

// 7. Reinstalar hook
echo "<h2>7. Reinstalando Hook</h2>";

$module = Module::getInstanceByName('smartpricetracker');
if ($module) {
    // Desregistrar el hook
    $module->unregisterHook('displayAdminProductsExtra');
    
    // Registrar de nuevo
    if ($module->registerHook('displayAdminProductsExtra')) {
        $success[] = "✅ Hook reinstalado correctamente";
        echo "✅ Hook reinstalado correctamente<br>";
    } else {
        $errors[] = "❌ Error al reinstalar el hook";
        echo "❌ Error al reinstalar el hook<br>";
    }
}

// Resumen
echo "<hr>";
echo "<h2>📊 Resumen de la Reparación</h2>";

if (count($success) > 0) {
    echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<h3 style='color: #155724; margin-top: 0;'>✅ Operaciones Exitosas (" . count($success) . ")</h3>";
    echo "<ul style='color: #155724;'>";
    foreach ($success as $msg) {
        echo "<li>$msg</li>";
    }
    echo "</ul>";
    echo "</div>";
}

if (count($errors) > 0) {
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<h3 style='color: #721c24; margin-top: 0;'>❌ Errores Encontrados (" . count($errors) . ")</h3>";
    echo "<ul style='color: #721c24;'>";
    foreach ($errors as $msg) {
        echo "<li>$msg</li>";
    }
    echo "</ul>";
    echo "</div>";
}

echo "<hr>";
echo "<h2>🎯 Próximos Pasos</h2>";

if (count($errors) > 0) {
    echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px;'>";
    echo "<p><strong>Hay errores que requieren tu atención:</strong></p>";
    echo "<ol>";
    echo "<li>Si falta el método searchCompetitorsByTitle, descarga el archivo SmartPriceScraper.php actualizado</li>";
    echo "<li>Colócalo en: <code>modules/smartpricetracker/classes/SmartPriceScraper.php</code></li>";
    echo "<li>Verifica que el archivo AdminSmartPriceTrackerAjaxController.php esté en: <code>modules/smartpricetracker/controllers/admin/</code></li>";
    echo "<li>Vuelve a ejecutar este script</li>";
    echo "</ol>";
    echo "</div>";
} else {
    echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px;'>";
    echo "<p style='color: #155724; font-size: 18px; margin: 0;'><strong>🎉 ¡Reparación completada con éxito!</strong></p>";
    echo "<p style='color: #155724;'>Ahora puedes:</p>";
    echo "<ol style='color: #155724;'>";
    echo "<li>Ir a un producto en el backoffice</li>";
    echo "<li>Abrir la pestaña 'Módulos'</li>";
    echo "<li>El Radar de Precios debería funcionar correctamente</li>";
    echo "</ol>";
    echo "</div>";
}

echo "<hr>";
echo "<p><a href='diagnostic.php' style='padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px;'>▶️ Ejecutar Diagnóstico Completo</a></p>";
