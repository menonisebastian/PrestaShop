<?php
/**
 * Controlador AJAX para el Radar de Precios
 * 
 * IMPORTANTE: Este archivo debe estar en:
 * modules/smartpricetracker/controllers/admin/AdminSmartPriceTrackerAjaxController.php
 */

// Cargar la clase SmartPriceScraper
require_once _PS_MODULE_DIR_ . 'smartpricetracker/classes/SmartPriceScraper.php';

class AdminSmartPriceTrackerAjaxController extends ModuleAdminController
{
    public function __construct()
    {
        parent::__construct();
        
        // Permitir AJAX sin token en algunos casos (útil para debug)
        $this->ajax = true;
    }

    /**
     * Método principal para búsqueda de competidores
     * Se llama con: action=SearchCompetitors
     */
    public function ajaxProcessSearchCompetitors()
    {
        // Log para debug (opcional, comentar en producción)
        PrestaShopLogger::addLog('Smart Price Tracker: Iniciando búsqueda AJAX', 1, null, 'SmartPriceTracker');
        
        // Obtener parámetros
        $id_product = (int)Tools::getValue('id_product');
        $search_term = trim(Tools::getValue('search_term'));

        // Validación básica
        if (empty($search_term)) {
            $this->ajaxDie(json_encode([
                'success' => false, 
                'error' => 'Por favor, introduce el nombre del producto para buscar.'
            ]));
        }

        // Validar que el producto existe
        if (!$id_product || !Product::existsInDatabase($id_product, 'product')) {
            $this->ajaxDie(json_encode([
                'success' => false, 
                'error' => 'El producto no existe.'
            ]));
        }

        // Verificar que el método existe
        if (!method_exists('SmartPriceScraper', 'searchCompetitorsByTitle')) {
            $this->ajaxDie(json_encode([
                'success' => false, 
                'error' => 'Error crítico: El método searchCompetitorsByTitle no existe. Por favor, actualiza el archivo SmartPriceScraper.php'
            ]));
        }

        try {
            // Llamar al motor de búsqueda
            $competitors = SmartPriceScraper::searchCompetitorsByTitle($search_term);

            if ($competitors !== false && count($competitors) > 0) {
                
                // Guardar los resultados en formato JSON
                $json_data = json_encode($competitors);

                // Guardar en BD
                $sql = 'INSERT INTO `' . _DB_PREFIX_ . 'smart_competitor_price` (id_product, search_term, competitors_data, last_scan)
                        VALUES (' . (int)$id_product . ', "' . pSQL($search_term) . '", "' . pSQL($json_data, true) . '", NOW())
                        ON DUPLICATE KEY UPDATE 
                        search_term = "' . pSQL($search_term) . '", 
                        competitors_data = "' . pSQL($json_data, true) . '", 
                        last_scan = NOW()';
                
                Db::getInstance()->execute($sql);

                // Obtener el precio del producto actual
                $my_price = Product::getPriceStatic($id_product, true);

                // Calcular diferencias para enviarlas al frontend
                foreach ($competitors as &$comp) {
                    $comp['diff'] = $my_price - $comp['price'];
                    $comp['price_formatted'] = number_format($comp['price'], 2, ',', '.') . ' €';
                    $comp['diff_formatted'] = number_format(abs($comp['diff']), 2, ',', '.') . ' €';
                }

                $this->ajaxDie(json_encode([
                    'success' => true, 
                    'competitors' => $competitors,
                    'my_price_formatted' => number_format($my_price, 2, ',', '.') . ' €',
                    'my_price' => $my_price,
                    'last_scan' => date('d/m/Y H:i:s')
                ]));
            } else {
                $this->ajaxDie(json_encode([
                    'success' => false, 
                    'error' => 'No se han encontrado resultados de precios para este producto en Google Shopping. Intenta con un nombre de búsqueda diferente o verifica que el producto exista en el mercado.'
                ]));
            }
        } catch (Exception $e) {
            // Log del error
            PrestaShopLogger::addLog('Smart Price Tracker Error: ' . $e->getMessage(), 3, null, 'SmartPriceTracker');
            
            $this->ajaxDie(json_encode([
                'success' => false, 
                'error' => 'Error al procesar la búsqueda: ' . $e->getMessage()
            ]));
        }
    }

    /**
     * Método de respuesta AJAX
     */
    protected function ajaxDie($value = null, $controller = null, $method = null)
    {
        // Asegurar que enviamos JSON
        header('Content-Type: application/json');
        parent::ajaxDie($value, $controller, $method);
    }
}
