<?php
/**
 * Módulo Rastreador Inteligente de Precios de Competencia
 * Versión mejorada con búsqueda automática
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/classes/SmartPriceScraper.php';

class SmartPriceTracker extends Module
{
    public function __construct()
    {
        $this->name = 'smartpricetracker';
        $this->tab = 'administration';
        $this->version = '2.1.1';
        $this->author = 'Tu Nombre/Agencia';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Radar de Precios (Competencia)');
        $this->description = $this->l('Busca el producto en Google Shopping y muestra una lista de competidores y sus precios.');
        $this->ps_versions_compliancy = array('min' => '1.7.0.0', 'max' => '8.99.99');
    }

    public function install()
    {
        // Crear tabla para el historial del radar
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_competitor_price` (
            `id_product` INT(10) UNSIGNED NOT NULL PRIMARY KEY,
            `search_term` VARCHAR(255) NOT NULL,
            `competitors_data` TEXT NOT NULL,
            `last_scan` DATETIME NOT NULL
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

        if (!Db::getInstance()->execute($sql)) {
            return false;
        }

        $this->installTab();

        return parent::install() &&
            $this->registerHook('displayAdminProductsExtra');
    }

    public function uninstall()
    {
        $sql = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'smart_competitor_price`';
        Db::getInstance()->execute($sql);
        $this->uninstallTab();

        return parent::uninstall();
    }

    private function installTab()
    {
        $tab = new Tab();
        $tab->active = 1;
        $tab->class_name = 'AdminSmartPriceTrackerAjax';
        $tab->name = array();
        foreach (Language::getLanguages(true) as $lang) {
            $tab->name[$lang['id_lang']] = 'Smart Price Tracker Ajax';
        }
        $tab->id_parent = -1; 
        $tab->module = $this->name;
        return $tab->add();
    }

    private function uninstallTab()
    {
        $id_tab = (int)Tab::getIdFromClassName('AdminSmartPriceTrackerAjax');
        if ($id_tab) {
            $tab = new Tab($id_tab);
            return $tab->delete();
        }
        return true;
    }

    /**
     * Dibuja la pestaña en la ficha del producto
     */
    public function hookDisplayAdminProductsExtra($params)
    {
        // PS 8.1 a veces pasa el id_product en params, otras veces por GET/POST
        $id_product = isset($params['id_product']) ? (int)$params['id_product'] : (int)Tools::getValue('id_product');

        // Prevención: Si el producto es nuevo y aún no se ha guardado, no podemos buscarlo
        if (!$id_product) {
            return '<div class="alert alert-info mt-3" role="alert">
                        <p class="alert-text">
                            <i class="material-icons">info</i>
                            Debes guardar el producto por primera vez para poder usar el Radar de Precios.
                        </p>
                    </div>';
        }

        // Verificar que el producto existe
        if (!Product::existsInDatabase($id_product, 'product')) {
            return '<div class="alert alert-warning mt-3" role="alert">
                        <p class="alert-text">
                            <i class="material-icons">warning</i>
                            El producto no existe en la base de datos.
                        </p>
                    </div>';
        }

        $product = new Product($id_product);
        $id_lang = (int)$this->context->language->id;
        
        // EXTRACCIÓN SEGURA DEL NOMBRE (Evita el error "Array to string conversion")
        $product_name = '';
        if (is_array($product->name)) {
            $product_name = isset($product->name[$id_lang]) && !empty($product->name[$id_lang]) 
                ? $product->name[$id_lang] 
                : current($product->name);
        } else {
            $product_name = $product->name;
        }

        // Si no hay nombre, no podemos buscar
        if (empty($product_name)) {
            return '<div class="alert alert-warning mt-3" role="alert">
                        <p class="alert-text">
                            <i class="material-icons">warning</i>
                            El producto no tiene un nombre asignado. Añade un nombre para poder usar el Radar de Precios.
                        </p>
                    </div>';
        }

        $db = Db::getInstance();

        // Obtener datos guardados de este producto
        $row = $db->getRow('SELECT * FROM `' . _DB_PREFIX_ . 'smart_competitor_price` WHERE id_product = ' . (int)$id_product);
        
        // Si ya habíamos buscado algo, cargamos ese término, si no, usamos el nombre del producto
        $search_term = $row && !empty($row['search_term']) ? $row['search_term'] : $product_name;
        
        $competitors_data = $row && !empty($row['competitors_data']) ? json_decode($row['competitors_data'], true) : [];
        $last_scan = $row ? $row['last_scan'] : null;

        // Obtener el precio actual del producto
        $my_price = Product::getPriceStatic($id_product, true);

        // Pre-calcular diferencias de la caché
        if (is_array($competitors_data) && !empty($competitors_data)) {
            foreach ($competitors_data as &$comp) {
                if (isset($comp['price'])) {
                    $comp['diff'] = $my_price - $comp['price'];
                }
            }
        }

        // Link AJAX
        $ajax_link = $this->context->link->getAdminLink('AdminSmartPriceTrackerAjax');

        // Asignar variables a Smarty
        $this->context->smarty->assign(array(
            'id_product' => $id_product,
            'search_term' => $search_term,
            'competitors' => $competitors_data,
            'last_scan' => $last_scan,
            'my_price' => $my_price,
            'ajax_link' => $ajax_link
        ));

        return $this->display(__FILE__, 'views/templates/hook/admin_products_extra.tpl');
    }
}