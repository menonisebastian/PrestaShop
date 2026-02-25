<?php
/**
 * Clase estática para realizar la extracción (Scraping) avanzada y multinivel
 */

class SmartPriceScraper
{
    // ========================================================================
    // MODO PROFESIONAL (Opcional): API Key de ScraperAPI.com (Gratis 1000/mes)
    // ========================================================================
    const SCRAPER_API_KEY = 'd71349b190a08bfadcd73abc846ce400'; 

    public static function getCompetitorPrice($url)
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        // Ampliamos el tiempo máximo de ejecución de PHP. 
        // Renderizar JS a través de proxies residenciales puede tardar hasta 40 segundos.
        @set_time_limit(120);

        $host = parse_url($url, PHP_URL_HOST);
        $price = false;

        // ========================================================================
        // FASE 1: INTENTO LOCAL (SIN API - AHORRA CRÉDITOS)
        // ========================================================================
        
        // Intento 1.1: Navegador Chrome Normal
        $html = self::fetchUrl($url, $host, 0, false);

        // Si nos bloquea el WAF, intentamos usar disfraces locales
        if (self::isBlocked($html)) {
            // Intento 1.2: Disfraz de Facebook Bot
            $html = self::fetchUrl($url, $host, 1, false);
            
            if (self::isBlocked($html)) {
                // Intento 1.3: Disfraz de Googlebot
                $html = self::fetchUrl($url, $host, 2, false);
            }
        }

        // Si el HTML local es válido (no está bloqueado), intentamos sacar el precio
        if (!empty($html) && !self::isBlocked($html)) {
            $price = self::parsePriceFromHtml($html, $host);
        }

        // ========================================================================
        // FASE 2: API DE RESCATE (SOLO SI LO LOCAL FALLA O EL PRECIO ESTÁ OCULTO EN JS)
        // ========================================================================
        if ($price === false && !empty(self::SCRAPER_API_KEY)) {
            // Usamos $useApi = true
            $htmlApi = self::fetchUrl($url, $host, 0, true);
            
            if (!empty($htmlApi) && !self::isBlocked($htmlApi)) {
                $price = self::parsePriceFromHtml($htmlApi, $host);
            }
        }

        return $price;
    }

    /**
     * Contiene todas las estrategias de lectura del HTML (Separado y Priorizado)
     */
    private static function parsePriceFromHtml($html, $host)
    {
        // Parsear DOM principal
        $dom = new DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new DOMXPath($dom);

        // --- ESTRATEGIA 1: CASOS ESPECÍFICOS POR DOMINIO (MÁXIMA PRIORIDAD) ---
        // Al poner esto primero, evitamos que coja el precio "sin IVA" del OpenGraph 
        // o el precio de un producto relacionado en el JSON-LD.

        // 1A: PcComponentes
        if (strpos($host, 'pccomponentes') !== false) {
            $pccPrice = $xpath->query('//*[@id="precio-main"] | //div[@id="precio-main"] | //*[@data-e2e="price-card"]//span | //div[contains(@class, "buy-box")]//*[contains(@class, "price")] | //div[contains(@class, "PriceBlock")]');
            if ($pccPrice->length > 0) {
                foreach ($pccPrice as $node) {
                    $clean = self::cleanPriceString($node->nodeValue);
                    // Devolvemos el primero que sea un número válido y mayor que 0
                    if ($clean > 0) return $clean;
                }
            }
        }

        // 1B: Madrid HiFi
        if (strpos($host, 'madridhifi') !== false) {
            $mhPrice = $xpath->query('//*[@id="our_price_display"] | //*[contains(@class, "price")]/span[@class="value"] | //*[contains(@class, "product-price")] | //div[contains(@class, "price")]');
            if ($mhPrice->length > 0) {
                foreach ($mhPrice as $node) {
                    $clean = self::cleanPriceString($node->nodeValue);
                    if ($clean > 0) return $clean;
                }
            }
        }

        // 1C: Amazon
        if (strpos($host, 'amazon') !== false) {
            $amazonPrice = $xpath->query('//span[contains(@class, "a-price")]/span[contains(@class, "a-offscreen")]');
            if ($amazonPrice->length > 0) {
                $clean = self::cleanPriceString($amazonPrice->item(0)->nodeValue);
                if ($clean > 0) return $clean;
            }
        }

        // --- ESTRATEGIA 2: Meta Tags Open Graph (Facebook / Pinterest) ---
        $ogPrice = $xpath->query('//meta[@property="product:price:amount"]/@content | //meta[@name="twitter:data1"]/@content');
        if ($ogPrice->length > 0) {
            $priceStr = $ogPrice->item(0)->nodeValue;
            $cleanPrice = self::cleanPriceString($priceStr);
            if ($cleanPrice !== false && $cleanPrice > 0) return $cleanPrice;
        }

        // --- ESTRATEGIA 3: Extracción directa de JSON-LD (Schema.org) ---
        if (preg_match_all('/<script[^>]*type="application\/ld\+json"[^>]*>(.*?)<\/script>/is', $html, $matches)) {
            foreach ($matches[1] as $jsonStr) {
                $data = @json_decode(trim($jsonStr), true);
                if (!$data) continue;
                
                $dataToProcess = isset($data['@graph']) ? $data['@graph'] : [$data];
                $price = self::extractPriceFromSchema($dataToProcess);
                if ($price !== false && $price > 0) return $price;
            }
        }

        // --- ESTRATEGIA 4: JSON Interno de Next.js (__NEXT_DATA__) ---
        $nextData = $xpath->query('//script[@id="__NEXT_DATA__"]');
        if ($nextData->length > 0) {
            $jsonStr = $nextData->item(0)->nodeValue;
            if (preg_match('/"price"\s*:\s*([0-9]+(?:[\.,][0-9]{1,2})?)/i', $jsonStr, $matches) || 
                preg_match('/"priceAmount"\s*:\s*([0-9]+(?:[\.,][0-9]{1,2})?)/i', $jsonStr, $matches)) {
                $cleanPrice = self::cleanPriceString($matches[1]);
                if ($cleanPrice !== false && $cleanPrice > 0) return $cleanPrice;
            }
        }

        // --- ESTRATEGIA 5: Microdatos HTML Clásicos ---
        $microdata = $xpath->query('//*[@itemprop="price"]');
        if ($microdata->length > 0) {
            $priceStr = $microdata->item(0)->getAttribute('content');
            if (empty($priceStr)) $priceStr = $microdata->item(0)->nodeValue;
            $cleanPrice = self::cleanPriceString($priceStr);
            if ($cleanPrice !== false && $cleanPrice > 0) return $cleanPrice;
        }

        // --- ESTRATEGIA 6: Atributos HTML "data-price" ---
        $dataPrices = $xpath->query('//*[@data-price]/@data-price | //*[@data-product-price]/@data-product-price');
        if ($dataPrices->length > 0) {
            $priceStr = $dataPrices->item(0)->nodeValue;
            $cleanPrice = self::cleanPriceString($priceStr);
            if ($cleanPrice !== false && $cleanPrice > 0) return $cleanPrice;
        }

        // --- ESTRATEGIA 7: Regex Crudo sobre el HTML ---
        if (preg_match('/"price"\s*:\s*"?([0-9]+(?:[\.,][0-9]{1,2})?)"?/i', $html, $matches)) {
            $cleanPrice = self::cleanPriceString($matches[1]);
            if ($cleanPrice !== false && $cleanPrice > 0) return $cleanPrice;
        }

        // --- ESTRATEGIA 8: Búsqueda visual bruta del precio ---
        // Mejorada para captar precios con o sin decimales al lado del símbolo de Euro
        if (preg_match('/([0-9]{1,3}(?:\.[0-9]{3})*(?:,[0-9]{2})?)\s*(?:€|&euro;|EUR)/ui', $html, $matches)) {
            $cleanPrice = self::cleanPriceString($matches[1]);
            if ($cleanPrice !== false && $cleanPrice > 0) return $cleanPrice;
        }

        return false;
    }

    /**
     * Evalúa si el HTML devuelto es una página de bloqueo de Cloudflare/Datadome
     */
    private static function isBlocked($html)
    {
        if (empty(trim($html))) return true;
        $htmlLower = strtolower($html);
        return (
            (strpos($htmlLower, 'cloudflare') !== false && strpos($htmlLower, 'ray id') !== false) ||
            strpos($htmlLower, 'datadome') !== false ||
            strpos($htmlLower, 'enable javascript and cookies') !== false ||
            strpos($htmlLower, 'robot check') !== false ||
            strpos($htmlLower, 'we just need to make sure you\'re not a robot') !== false // Amazon Captcha
        );
    }

    /**
     * Sistema de peticiones rotativo y camuflado
     */
    private static function fetchUrl($url, $host, $botLevel = 0, $useApi = false)
    {
        $ch = curl_init();
        
        if ($useApi && !empty(self::SCRAPER_API_KEY)) {
            // Usando ScraperAPI con JS Rendering y Proxy Español
            $apiUrl = 'http://api.scraperapi.com/?api_key=' . self::SCRAPER_API_KEY . '&url=' . urlencode($url) . '&render=true&country_code=es';
            
            // Forzar Proxies Residenciales (Premium) para los sitios problemáticos
            if (strpos($host, 'madridhifi') !== false || strpos($host, 'pccomponentes') !== false || strpos($host, 'amazon') !== false) {
                $apiUrl .= '&premium=true';
            }

            curl_setopt($ch, CURLOPT_URL, $apiUrl);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60); 
        } else {
            // Conexión Directa desde nuestro servidor
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_TIMEOUT, 12); 
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_ENCODING, ''); 
        curl_setopt($ch, CURLOPT_COOKIEFILE, ""); 
        
        // Evitar fallos de certificados SSL locales que detengan la petición
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        if (defined('CURL_HTTP_VERSION_2_0')) {
            curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2_0);
        }

        if ($botLevel === 1) {
            curl_setopt($ch, CURLOPT_USERAGENT, 'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Connection: keep-alive'
            ]);
        } elseif ($botLevel === 2) {
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Connection: keep-alive'
            ]);
        } else {
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authority: ' . $host,
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                'Accept-Language: es-ES,es;q=0.9,en;q=0.8',
                'Cache-Control: max-age=0',
                'Sec-Fetch-Dest: document',
                'Sec-Fetch-Mode: navigate',
                'Sec-Fetch-Site: none',
                'Upgrade-Insecure-Requests: 1'
            ]);
        }
        
        $html = curl_exec($ch);
        curl_close($ch);

        return $html;
    }

    /**
     * Función auxiliar para recorrer arrays de JSON-LD
     */
    private static function extractPriceFromSchema($schemaArray)
    {
        if (!is_array($schemaArray)) return false;
        
        foreach ($schemaArray as $item) {
            if (isset($item['@type'])) {
                $type = is_array($item['@type']) ? $item['@type'][0] : $item['@type'];
                
                if ($type === 'Product' && isset($item['offers'])) {
                    $offers = is_array($item['offers']) && isset($item['offers'][0]) ? $item['offers'][0] : $item['offers'];
                    if (isset($offers['price'])) {
                        return self::cleanPriceString((string)$offers['price']);
                    }
                } 
                elseif ($type === 'Offer' && isset($item['price'])) {
                    return self::cleanPriceString((string)$item['price']);
                }
            }
        }
        return false;
    }

    /**
     * Limpia la cadena del precio de cualquier formato
     */
    private static function cleanPriceString($string)
    {
        $string = trim($string);
        if (empty($string)) return false;

        $cleanString = preg_replace('/[^0-9\.,]/', '', $string);

        if (strpos($cleanString, ',') !== false && strpos($cleanString, '.') !== false) {
            $lastComma = strrpos($cleanString, ',');
            $lastDot = strrpos($cleanString, '.');
            
            if ($lastComma > $lastDot) {
                $cleanString = str_replace('.', '', $cleanString);
                $cleanString = str_replace(',', '.', $cleanString);
            } else {
                $cleanString = str_replace(',', '', $cleanString);
            }
        } else {
            $cleanString = str_replace(',', '.', $cleanString);
        }

        if (!is_numeric($cleanString)) return false;

        return (float) $cleanString;
    }
}