<?php
/**
 * Controlador para procesar el Radar de Precios masivo
 */

require_once dirname(__FILE__) . '/../../classes/SmartPriceScraper.php';

class AdminSmartPriceTrackerAjaxController extends ModuleAdminController
{
    public function ajaxProcessSearchCompetitors()
    {
        $id_product = (int)Tools::getValue('id_product');
        $search_term = trim(Tools::getValue('search_term'));

        if (empty($search_term)) {
            die(json_encode([
                'success' => false, 
                'error' => 'Por favor, introduce el nombre del producto para buscar.'
            ]));
        }

        // Validar que el producto existe
        if (!$id_product || !Product::existsInDatabase($id_product, 'product')) {
            die(json_encode([
                'success' => false, 
                'error' => 'El producto no existe.'
            ]));
        }

        // Llamamos al motor de búsqueda masiva (AHORA SÍ EXISTE)
        $competitors = SmartPriceScraper::searchCompetitorsByTitle($search_term);

        if ($competitors !== false && count($competitors) > 0) {
            
            // Guardar los resultados en formato JSON
            $json_data = json_encode($competitors);

            // Guardar en BD
            $sql = 'INSERT INTO `' . _DB_PREFIX_ . 'smart_competitor_price` (id_product, search_term, competitors_data, last_scan)
                    VALUES (' . $id_product . ', "' . pSQL($search_term) . '", "' . pSQL($json_data, true) . '", NOW())
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

            die(json_encode([
                'success' => true, 
                'competitors' => $competitors,
                'my_price_formatted' => number_format($my_price, 2, ',', '.') . ' €',
                'my_price' => $my_price,
                'last_scan' => date('d/m/Y H:i:s')
            ]));
        } else {
            die(json_encode([
                'success' => false, 
                'error' => 'No se han encontrado resultados de precios para este producto en Google Shopping. Intenta con un nombre de búsqueda diferente o verifica que el producto exista en el mercado.'
            ]));
        }
    }
}