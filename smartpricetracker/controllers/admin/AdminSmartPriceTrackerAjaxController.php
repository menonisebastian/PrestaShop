<?php
/**
 * Controlador para procesar la petición AJAX asíncrona desde el Back-office
 */

require_once dirname(__FILE__) . '/../../classes/SmartPriceScraper.php';

class AdminSmartPriceTrackerAjaxController extends ModuleAdminController
{
    public function ajaxProcessSaveAndScan()
    {
        $id_product = (int)Tools::getValue('id_product');
        $url = trim(Tools::getValue('competitor_url'));

        if (empty($url) || !Validate::isAbsoluteUrl($url)) {
            die(json_encode([
                'success' => false, 
                'error' => 'Por favor, introduce una URL válida con http:// o https://'
            ]));
        }

        // 1. Ejecutamos nuestro motor de scraping en la URL recibida
        $price = SmartPriceScraper::getCompetitorPrice($url);

        if ($price !== false) {
            // 2. Guardar o actualizar la base de datos `smart_competitor_price`
            $sql = 'INSERT INTO `' . _DB_PREFIX_ . 'smart_competitor_price` (id_product, competitor_url, last_price, last_scan)
                    VALUES (' . $id_product . ', "' . pSQL($url) . '", ' . (float)$price . ', NOW())
                    ON DUPLICATE KEY UPDATE 
                    competitor_url = "' . pSQL($url) . '", 
                    last_price = ' . (float)$price . ', 
                    last_scan = NOW()';
            
            Db::getInstance()->execute($sql);

            // 3. Recalcular la diferencia usando nuestro precio de Prestashop (Con IVA incluido)
            $my_price = Product::getPriceStatic($id_product, true);
            $diff = $my_price - $price;

            // 4. Devolver la respuesta en JSON al Javascript de la plantilla
            die(json_encode([
                'success' => true, 
                'competitor_price' => number_format($price, 2), 
                'my_price' => number_format($my_price, 2),
                'diff' => number_format($diff, 2),
                'diff_raw' => $diff,
                'last_scan' => date('d/m/Y H:i:s')
            ]));
        } else {
            // Falla si no encuentra JSON-LD o la página devuelve error 404/500
            die(json_encode([
                'success' => false, 
                'error' => 'No se ha podido localizar el precio en los datos estructurados (JSON-LD) de esa URL.'
            ]));
        }
    }
}