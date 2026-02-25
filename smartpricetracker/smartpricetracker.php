<?php
/**
 * Módulo Rastreador Inteligente de Precios de Competencia
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

// Requerimos la clase que hace el scraping de forma segura
require_once dirname(__FILE__) . '/classes/SmartPriceScraper.php';

class SmartPriceTracker extends Module
{
    public function __construct()
    {
        // El nombre debe coincidir EXACTAMENTE con el de la carpeta y el archivo .php
        $this->name = 'smartpricetracker';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'Sebastián/Sysproviders';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Smart Price Tracker (Competencia)');
        $this->description = $this->l('Rastrea el precio de un competidor mediante JSON-LD al entrar en un producto para comparar precios.');
        $this->ps_versions_compliancy = array('min' => '1.7.0.0', 'max' => '8.99.99');
    }

    public function install()
    {
        // Crear tabla en la base de datos con un prefijo único para este módulo
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'smart_competitor_price` (
            `id_product` INT(10) UNSIGNED NOT NULL PRIMARY KEY,
            `competitor_url` VARCHAR(500) NOT NULL,
            `last_price` DECIMAL(20,6) NOT NULL,
            `last_scan` DATETIME NOT NULL
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

        if (!Db::getInstance()->execute($sql)) {
            return false;
        }

        // Instalar pestaña oculta para las peticiones AJAX en el Back-office
        $this->installTab();

        return parent::install() &&
            $this->registerHook('displayAdminProductsExtra');
    }

    public function uninstall()
    {
        // Eliminar la tabla de la base de datos
        $sql = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'smart_competitor_price`';
        Db::getInstance()->execute($sql);

        $this->uninstallTab();

        return parent::uninstall();
    }

    private function installTab()
    {
        $tab = new Tab();
        $tab->active = 1;
        // El class_name debe coincidir con el prefijo "Admin" + el nombre del controlador sin "Controller"
        $tab->class_name = 'AdminSmartPriceTrackerAjax';
        $tab->name = array();
        foreach (Language::getLanguages(true) as $lang) {
            $tab->name[$lang['id_lang']] = 'Smart Price Tracker Ajax';
        }
        $tab->id_parent = -1; // Pestaña oculta, no visible en el menú izquierdo
        $tab->module = $this->name;
        $tab->add();
    }

    private function uninstallTab()
    {
        $id_tab = (int)Tab::getIdFromClassName('AdminSmartPriceTrackerAjax');
        if ($id_tab) {
            $tab = new Tab($id_tab);
            $tab->delete();
        }
    }

    /**
     * Hook que dibuja nuestra pestaña en la ficha de edición del producto
     */
    public function hookDisplayAdminProductsExtra($params)
    {
        $id_product = (int)$params['id_product'];
        $db = Db::getInstance();

        // 1. Obtener datos guardados de este producto en nuestra tabla
        $row = $db->getRow('SELECT * FROM `' . _DB_PREFIX_ . 'smart_competitor_price` WHERE id_product = ' . $id_product);
        
        $competitor_url = $row ? $row['competitor_url'] : '';
        $last_price = $row ? (float)$row['last_price'] : 0.0;
        $last_scan = $row ? $row['last_scan'] : null;

        // 2. Auto-escaneo: Si han pasado más de 24 horas, volvemos a raspar silenciosamente
        if ($competitor_url && $last_scan && strtotime($last_scan) < strtotime('-24 hours')) {
            $new_price = SmartPriceScraper::getCompetitorPrice($competitor_url);
            if ($new_price !== false) {
                $last_price = $new_price;
                $last_scan = date('Y-m-d H:i:s');
                $db->update('smart_competitor_price', [
                    'last_price' => $last_price,
                    'last_scan' => $last_scan
                ], 'id_product = ' . $id_product);
            }
        }

        // 3. Obtener el precio final de nuestra tienda (con impuestos incluidos)
        $my_price = Product::getPriceStatic($id_product, true);
        
        // 4. Calcular diferencia de precios
        $diff = 0;
        if ($last_price > 0) {
            $diff = $my_price - $last_price;
        }

        // 5. Preparar enlace seguro al controlador AJAX para el botón manual
        $ajax_link = $this->context->link->getAdminLink('AdminSmartPriceTrackerAjax');

        // Asignar variables a la plantilla Smarty
        $this->context->smarty->assign(array(
            'id_product' => $id_product,
            'competitor_url' => $competitor_url,
            'last_price' => $last_price,
            'last_scan' => $last_scan,
            'my_price' => $my_price,
            'diff' => $diff,
            'ajax_link' => $ajax_link
        ));

        // Renderizar la vista
        return $this->display(__FILE__, 'views/templates/hook/admin_products_extra.tpl');
    }
}