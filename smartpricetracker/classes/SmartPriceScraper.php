<?php
/**
 * Clase para Scraping con extracción mejorada de Google Shopping
 * Versión 2.3 - Corrige extracción de vendedores y URLs
 */

class SmartPriceScraper
{
    const SCRAPER_API_KEY = 'd71349b190a08bfadcd73abc846ce400'; 

    /**
     * Busca productos en Google Shopping
     */
    public static function searchCompetitorsByTitle($search_term)
    {
        if (empty(trim($search_term))) {
            return false;
        }

        $competitors = [];
        
        PrestaShopLogger::addLog('Smart Price Tracker: Buscando "' . $search_term . '"', 1, null, 'SmartPriceTracker');
        
        // ========================================================================
        // ESTRATEGIA 1: Búsqueda directa en Google Shopping
        // ========================================================================
        $googleShoppingUrl = 'https://www.google.es/search?q=' . urlencode($search_term) . '&tbm=shop&hl=es&gl=es';
        
        // Intentar sin API (0 créditos)
        $html = self::fetchUrlForSearch($googleShoppingUrl, false, false);
        
        if (!empty($html) && !self::isGoogleBlocked($html)) {
            $competitors = self::parseGoogleShoppingResults($html);
            if (count($competitors) > 0) {
                PrestaShopLogger::addLog('Smart Price Tracker: Encontrados ' . count($competitors) . ' resultados (método directo)', 1, null, 'SmartPriceTracker');
                return $competitors;
            }
        }
        
        // ========================================================================
        // ESTRATEGIA 2: Google Shopping via API
        // ========================================================================
        if (!empty(self::SCRAPER_API_KEY)) {
            PrestaShopLogger::addLog('Smart Price Tracker: Intentando con ScraperAPI (básica)', 1, null, 'SmartPriceTracker');
            
            $html = self::fetchUrlForSearch($googleShoppingUrl, true, false);
            
            if (!empty($html) && !self::isGoogleBlocked($html)) {
                $competitors = self::parseGoogleShoppingResults($html);
                if (count($competitors) > 0) {
                    PrestaShopLogger::addLog('Smart Price Tracker: Encontrados ' . count($competitors) . ' resultados (ScraperAPI básica)', 1, null, 'SmartPriceTracker');
                    return $competitors;
                }
            }
        }
        
        // ========================================================================
        // ESTRATEGIA 3: Búsqueda normal de Google
        // ========================================================================
        PrestaShopLogger::addLog('Smart Price Tracker: Intentando búsqueda normal de Google', 1, null, 'SmartPriceTracker');
        
        $googleNormalUrl = 'https://www.google.es/search?q=' . urlencode($search_term . ' precio comprar') . '&hl=es&gl=es';
        
        $html = self::fetchUrlForSearch($googleNormalUrl, false, false);
        
        if (!empty($html) && !self::isGoogleBlocked($html)) {
            $competitors = self::parseGoogleNormalResults($html, $search_term);
            if (count($competitors) > 0) {
                PrestaShopLogger::addLog('Smart Price Tracker: Encontrados ' . count($competitors) . ' resultados (Google normal)', 1, null, 'SmartPriceTracker');
                return $competitors;
            }
        }
        
        PrestaShopLogger::addLog('Smart Price Tracker: No se encontraron resultados con ninguna estrategia', 2, null, 'SmartPriceTracker');
        return false;
    }

    /**
     * MEJORADO: Extrae resultados de Google Shopping con mejor parsing
     */
    private static function parseGoogleShoppingResults($html)
    {
        $competitors = [];
        
        // Guardar fragmento del HTML para debug
        $htmlSample = substr($html, 0, 2000);
        PrestaShopLogger::addLog('Smart Price Tracker: HTML sample (primeros 2000 chars): ' . $htmlSample, 1, null, 'SmartPriceTracker');
        
        // ========================================================================
        // MÉTODO 1: Parsing con DOMDocument (más preciso)
        // ========================================================================
        $dom = new DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new DOMXPath($dom);
        
        // Buscar contenedores de productos de Google Shopping
        // Google Shopping usa diferentes estructuras según la región/momento
        $productContainers = $xpath->query('//div[@data-docid] | //div[contains(@class, "sh-dgr__content")]');
        
        PrestaShopLogger::addLog('Smart Price Tracker: Encontrados ' . $productContainers->length . ' contenedores de productos', 1, null, 'SmartPriceTracker');
        
        if ($productContainers->length > 0) {
            foreach ($productContainers as $container) {
                $competitor = self::extractProductFromContainer($xpath, $container);
                
                if ($competitor !== false && $competitor['price'] > 0) {
                    $competitors[] = $competitor;
                    PrestaShopLogger::addLog('Smart Price Tracker: Extraído: ' . $competitor['seller'] . ' - ' . $competitor['price'] . '€ - URL: ' . substr($competitor['url'], 0, 100), 1, null, 'SmartPriceTracker');
                }
                
                if (count($competitors) >= 15) {
                    break;
                }
            }
        }
        
        // ========================================================================
        // MÉTODO 2: Si DOMDocument no funcionó, usar Regex (fallback)
        // ========================================================================
        if (count($competitors) === 0) {
            PrestaShopLogger::addLog('Smart Price Tracker: DOMDocument no encontró resultados, usando regex', 1, null, 'SmartPriceTracker');
            $competitors = self::parseGoogleShoppingRegex($html);
        }
        
        return $competitors;
    }

    /**
     * Extrae un producto de un contenedor de Google Shopping
     */
    private static function extractProductFromContainer($xpath, $container)
    {
        $competitor = [
            'seller' => 'Desconocido',
            'price' => 0,
            'url' => '#',
            'title' => ''
        ];

        // ========================================================================
        // 1. EXTRAER PRECIO (Prioridad máxima)
        // ========================================================================
        
        // Método A: Buscar en atributos aria-label (más confiable)
        $priceElements = $xpath->query('.//*[@aria-label]', $container);
        foreach ($priceElements as $elem) {
            $ariaLabel = $elem->getAttribute('aria-label');
            if (preg_match('/([0-9]{1,3}(?:\.[0-9]{3})*(?:,[0-9]{2})?)(?:\s)*(?:€|EUR)/ui', $ariaLabel, $match)) {
                $price = self::cleanPriceString($match[1]);
                if ($price > 0) {
                    $competitor['price'] = $price;
                    break;
                }
            }
        }
        
        // Método B: Buscar spans con clases de precio
        if ($competitor['price'] === 0) {
            $priceNodes = $xpath->query('.//span[contains(@class, "price")] | .//b | .//span[contains(text(), "€")]', $container);
            foreach ($priceNodes as $node) {
                $priceText = trim($node->nodeValue);
                if (preg_match('/([0-9]{1,3}(?:\.[0-9]{3})*(?:,[0-9]{2})?)(?:\s)*(?:€|EUR)/ui', $priceText, $match)) {
                    $price = self::cleanPriceString($match[1]);
                    if ($price > 0) {
                        $competitor['price'] = $price;
                        break;
                    }
                }
            }
        }
        
        // Método C: Buscar en todo el texto del contenedor
        if ($competitor['price'] === 0) {
            $containerText = $container->nodeValue;
            if (preg_match('/([0-9]{1,3}(?:\.[0-9]{3})*(?:,[0-9]{2})?)(?:\s)*€/ui', $containerText, $match)) {
                $price = self::cleanPriceString($match[1]);
                if ($price > 0) {
                    $competitor['price'] = $price;
                }
            }
        }

        // ========================================================================
        // 2. EXTRAER NOMBRE DEL VENDEDOR
        // ========================================================================
        
        // Método A: Atributo data-merchant
        if ($container->hasAttribute('data-merchant')) {
            $competitor['seller'] = $container->getAttribute('data-merchant');
        }
        
        // Método B: Buscar divs/spans con clases relacionadas con tienda
        if ($competitor['seller'] === 'Desconocido') {
            $sellerNodes = $xpath->query('.//div[contains(@class, "merchant")] | .//span[contains(@class, "merchant")] | .//div[contains(@class, "store")] | .//span[contains(@class, "store")] | .//a[contains(@class, "merchant")]', $container);
            if ($sellerNodes->length > 0) {
                $sellerText = trim($sellerNodes->item(0)->nodeValue);
                if (!empty($sellerText) && strlen($sellerText) < 100) {
                    $competitor['seller'] = $sellerText;
                }
            }
        }
        
        // Método C: Extraer del título del enlace
        if ($competitor['seller'] === 'Desconocido') {
            $linkNodes = $xpath->query('.//a[@title]', $container);
            if ($linkNodes->length > 0) {
                $title = $linkNodes->item(0)->getAttribute('title');
                // El título a veces contiene "en [NombreTienda]"
                if (preg_match('/en\s+([A-Za-zÁ-ú\s]+)/ui', $title, $match)) {
                    $competitor['seller'] = trim($match[1]);
                }
            }
        }

        // ========================================================================
        // 3. EXTRAER URL DE LA TIENDA
        // ========================================================================
        
        $linkNodes = $xpath->query('.//a[@href]', $container);
        
        foreach ($linkNodes as $linkNode) {
            $href = $linkNode->getAttribute('href');
            
            // Ignorar enlaces internos de Google
            if (strpos($href, '/support.google.com') !== false || 
                strpos($href, 'support.google.com') !== false ||
                strpos($href, 'policies.google.com') !== false) {
                continue;
            }
            
            // Si es un enlace de redirección de Google, extraer la URL real
            if (strpos($href, '/url?') !== false || strpos($href, '/aclk?') !== false || strpos($href, 'google.com/url') !== false) {
                
                // Intentar extraer el parámetro 'url'
                if (preg_match('/[?&]url=([^&]+)/i', $href, $matches)) {
                    $realUrl = urldecode($matches[1]);
                    $competitor['url'] = $realUrl;
                    
                    // Extraer el dominio como vendedor si aún es desconocido
                    if ($competitor['seller'] === 'Desconocido') {
                        $host = parse_url($realUrl, PHP_URL_HOST);
                        if ($host) {
                            $competitor['seller'] = self::formatSellerName($host);
                        }
                    }
                    break;
                }
                
                // Intentar extraer el parámetro 'q'
                if (preg_match('/[?&]q=([^&]+)/i', $href, $matches)) {
                    $realUrl = urldecode($matches[1]);
                    if (filter_var($realUrl, FILTER_VALIDATE_URL)) {
                        $competitor['url'] = $realUrl;
                        
                        if ($competitor['seller'] === 'Desconocido') {
                            $host = parse_url($realUrl, PHP_HOST);
                            if ($host) {
                                $competitor['seller'] = self::formatSellerName($host);
                            }
                        }
                        break;
                    }
                }
            }
            // Si es una URL directa
            elseif (filter_var($href, FILTER_VALIDATE_URL)) {
                $competitor['url'] = $href;
                
                if ($competitor['seller'] === 'Desconocido') {
                    $host = parse_url($href, PHP_URL_HOST);
                    if ($host) {
                        $competitor['seller'] = self::formatSellerName($host);
                    }
                }
                break;
            }
        }

        // ========================================================================
        // 4. EXTRAER TÍTULO DEL PRODUCTO
        // ========================================================================
        
        $titleNodes = $xpath->query('.//h3 | .//h4 | .//div[contains(@class, "title")] | .//span[contains(@class, "title")]', $container);
        if ($titleNodes->length > 0) {
            $competitor['title'] = trim($titleNodes->item(0)->nodeValue);
        }

        // Solo devolver si tiene precio válido
        if ($competitor['price'] > 0) {
            return $competitor;
        }
        
        return false;
    }

    /**
     * Formatea el nombre del vendedor a partir del dominio
     */
    private static function formatSellerName($host)
    {
        // Quitar www.
        $host = str_replace('www.', '', $host);
        
        // Extraer el nombre del dominio (antes del TLD)
        $parts = explode('.', $host);
        if (count($parts) >= 2) {
            $name = $parts[0];
            
            // Capitalizar y hacer más legible
            $name = ucfirst($name);
            
            // Reemplazar guiones por espacios
            $name = str_replace('-', ' ', $name);
            
            return $name;
        }
        
        return ucfirst($host);
    }

    /**
     * Extrae resultados de búsqueda normal de Google
     */
    private static function parseGoogleNormalResults($html, $search_term)
    {
        $competitors = [];
        
        $dom = new DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new DOMXPath($dom);
        
        $results = $xpath->query('//div[@class="g"] | //div[contains(@class, "tF2Cxc")]');
        
        foreach ($results as $result) {
            $competitor = [
                'seller' => 'Desconocido',
                'price' => 0,
                'url' => '#',
                'title' => ''
            ];

            // Título
            $titleNodes = $xpath->query('.//h3', $result);
            if ($titleNodes->length > 0) {
                $competitor['title'] = trim($titleNodes->item(0)->nodeValue);
            }

            // URL y vendedor
            $linkNodes = $xpath->query('.//a[@href]', $result);
            if ($linkNodes->length > 0) {
                $url = $linkNodes->item(0)->getAttribute('href');
                if (filter_var($url, FILTER_VALIDATE_URL)) {
                    $competitor['url'] = $url;
                    $host = parse_url($url, PHP_URL_HOST);
                    if ($host) {
                        $competitor['seller'] = self::formatSellerName($host);
                    }
                }
            }

            // Precio
            $textContent = $result->nodeValue;
            if (preg_match('/([0-9]{1,3}(?:\.[0-9]{3})*(?:,[0-9]{2})?)(?:\s)*(?:€|EUR)/ui', $textContent, $priceMatch)) {
                $price = self::cleanPriceString($priceMatch[1]);
                if ($price !== false && $price > 10) {
                    $competitor['price'] = $price;
                }
            }

            if ($competitor['price'] > 0 && $competitor['url'] !== '#') {
                $competitors[] = $competitor;
            }

            if (count($competitors) >= 10) {
                break;
            }
        }

        return $competitors;
    }

    /**
     * Método de fallback usando regex
     */
    private static function parseGoogleShoppingRegex($html)
    {
        $competitors = [];
        
        // Buscar bloques que parezcan productos
        if (preg_match_all('/<div[^>]*data-docid="([^"]+)"[^>]*>(.*?)<\/div>/is', $html, $blocks, PREG_SET_ORDER)) {
            
            foreach ($blocks as $block) {
                $blockHtml = $block[0];
                
                // Buscar precio
                if (preg_match('/([0-9]{1,3}(?:\.[0-9]{3})*(?:,[0-9]{2})?)(?:\s)*€/ui', $blockHtml, $priceMatch)) {
                    $price = self::cleanPriceString($priceMatch[1]);
                    
                    if ($price !== false && $price > 20) {
                        
                        // Buscar URL
                        $url = '#';
                        if (preg_match('/<a[^>]*href="([^"]+)"[^>]*>/i', $blockHtml, $urlMatch)) {
                            $potentialUrl = $urlMatch[1];
                            
                            // Decodificar URL de Google
                            if (preg_match('/[?&]url=([^&]+)/i', $potentialUrl, $realUrlMatch)) {
                                $url = urldecode($realUrlMatch[1]);
                            } elseif (filter_var($potentialUrl, FILTER_VALIDATE_URL)) {
                                $url = $potentialUrl;
                            }
                        }
                        
                        // Intentar extraer vendedor
                        $seller = 'Competidor';
                        if ($url !== '#') {
                            $host = parse_url($url, PHP_URL_HOST);
                            if ($host) {
                                $seller = self::formatSellerName($host);
                            }
                        }
                        
                        $competitors[] = [
                            'seller' => $seller,
                            'price' => $price,
                            'url' => $url,
                            'title' => ''
                        ];
                        
                        if (count($competitors) >= 10) {
                            break;
                        }
                    }
                }
            }
        }
        
        // Si no encontró nada con data-docid, buscar precios directamente
        if (count($competitors) === 0) {
            if (preg_match_all('/([0-9]{1,3}(?:\.[0-9]{3})*(?:,[0-9]{2})?)(?:\s)*€/ui', $html, $allPrices, PREG_SET_ORDER)) {
                $seenPrices = [];
                foreach ($allPrices as $priceMatch) {
                    $price = self::cleanPriceString($priceMatch[1]);
                    if ($price !== false && $price > 20 && !in_array($price, $seenPrices)) {
                        $competitors[] = [
                            'seller' => 'Competidor',
                            'price' => $price,
                            'url' => '#',
                            'title' => ''
                        ];
                        $seenPrices[] = $price;
                        
                        if (count($competitors) >= 10) {
                            break;
                        }
                    }
                }
            }
        }
        
        PrestaShopLogger::addLog('Smart Price Tracker: Regex encontró ' . count($competitors) . ' resultados', 1, null, 'SmartPriceTracker');
        return $competitors;
    }

    /**
     * Petición HTTP para búsquedas
     */
    private static function fetchUrlForSearch($url, $useApi = false, $renderJs = false)
    {
        $ch = curl_init();
        
        if ($useApi && !empty(self::SCRAPER_API_KEY)) {
            $apiUrl = 'http://api.scraperapi.com/?api_key=' . self::SCRAPER_API_KEY . '&url=' . urlencode($url) . '&country_code=es';
            
            if ($renderJs) {
                $apiUrl .= '&render=true';
            }
            
            curl_setopt($ch, CURLOPT_URL, $apiUrl);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        } else {
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_ENCODING, '');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: es-ES,es;q=0.9',
            'Cache-Control: no-cache'
        ]);
        
        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            PrestaShopLogger::addLog('Smart Price Tracker: HTTP ' . $httpCode, 2, null, 'SmartPriceTracker');
            return false;
        }

        return $html;
    }

    // ========================================================================
    // MÉTODOS EXISTENTES (getCompetitorPrice, etc.) - SIN CAMBIOS
    // ========================================================================
    
    public static function getCompetitorPrice($url)
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        @set_time_limit(120);
        $host = parse_url($url, PHP_URL_HOST);
        $price = false;

        $html = self::fetchUrl($url, $host, 0, false, false, false);
        $price = self::parsePriceFromHtml($html, $host);

        if ($price === false && self::isBlocked($html)) {
            $html = self::fetchUrl($url, $host, 1, false, false, false);
            $price = self::parsePriceFromHtml($html, $host);
            
            if ($price === false && self::isBlocked($html)) {
                $html = self::fetchUrl($url, $host, 2, false, false, false);
                $price = self::parsePriceFromHtml($html, $host);
            }
        }

        if ($price === false) {
            $googleUrl = 'https://www.google.es/search?q=' . urlencode('"' . $url . '"') . '&hl=es&gl=es';
            $googleHtml = self::fetchUrl($googleUrl, 'www.google.es', 0, false, false, false);
            
            if (!empty($googleHtml) && !self::isGoogleBlocked($googleHtml)) {
                $price = self::parsePriceFromGoogle($googleHtml);
            }
        }

        if ($price === false && !empty(self::SCRAPER_API_KEY)) {
            $htmlApi = self::fetchUrl($url, $host, 0, true, false, false);
            if (!empty($htmlApi) && !self::isBlocked($htmlApi)) {
                $price = self::parsePriceFromHtml($htmlApi, $host);
            }
        }

        return $price;
    }

    private static function parsePriceFromGoogle($html)
    {
        $start = strpos($html, 'id="search"');
        $searchBlock = $html;
        
        if ($start !== false) {
            $end = strpos($html, 'id="bottomads"');
            if ($end !== false && $end > $start) {
                $searchBlock = substr($html, $start, $end - $start);
            } else {
                $searchBlock = substr($html, $start);
            }
        }

        if (preg_match_all('/([0-9]{1,3}(?:\.[0-9]{3})*(?:,[0-9]{2})?)(?:\s)*(?:€|EUR)/ui', $searchBlock, $matches)) {
            foreach ($matches[1] as $match) {
                $clean = self::cleanPriceString($match);
                if ($clean > 5) { 
                    return $clean;
                }
            }
        }
        return false;
    }

    private static function parsePriceFromHtml($html, $host)
    {
        if (empty(trim($html))) return false;

        $dom = new DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new DOMXPath($dom);

        if (strpos($host, 'pccomponentes') !== false) {
            if (preg_match('/"price"\s*:\s*"?([0-9]+(?:[\.,][0-9]{1,2})?)"?/i', $html, $matches)) {
                $clean = self::cleanPriceString($matches[1]);
                if ($clean > 0) return $clean;
            }
        }

        if (strpos($host, 'amazon') !== false) {
            $amazonPrice = $xpath->query('//span[contains(@class, "a-price")]/span[contains(@class, "a-offscreen")]');
            if ($amazonPrice->length > 0) return self::cleanPriceString($amazonPrice->item(0)->nodeValue);
        }

        if (preg_match_all('/<script[^>]*type="application\/ld\+json"[^>]*>(.*?)<\/script>/is', $html, $matches)) {
            foreach ($matches[1] as $jsonStr) {
                $data = @json_decode(trim($jsonStr), true);
                if (!$data) continue;
                $dataToProcess = isset($data['@graph']) ? $data['@graph'] : [$data];
                $price = self::extractPriceFromSchema($dataToProcess);
                if ($price !== false && $price > 0) return $price;
            }
        }

        $ogPrice = $xpath->query('//meta[@property="product:price:amount"]/@content');
        if ($ogPrice->length > 0) {
            $cleanPrice = self::cleanPriceString($ogPrice->item(0)->nodeValue);
            if ($cleanPrice !== false && $cleanPrice > 0) return $cleanPrice;
        }

        if (preg_match('/([0-9]{1,3}(?:\.[0-9]{3})*(?:,[0-9]{2})?)\s*€/ui', $html, $matches)) {
            $cleanPrice = self::cleanPriceString($matches[1]);
            if ($cleanPrice !== false && $cleanPrice > 0) return $cleanPrice;
        }

        return false;
    }

    private static function isGoogleBlocked($html)
    {
        if (empty(trim($html))) return true;
        $htmlLower = strtolower($html);
        return (strpos($htmlLower, 'captcha') !== false || 
                strpos($htmlLower, 'tráfico inusual') !== false);
    }

    private static function isBlocked($html)
    {
        if (empty(trim($html)) || strlen($html) < 1000) return true; 
        $htmlLower = strtolower($html);
        return (strpos($htmlLower, 'cloudflare') !== false && strpos($htmlLower, 'ray id') !== false);
    }

    private static function fetchUrl($url, $host, $botLevel = 0, $useApi = false, $renderJs = false, $premium = false)
    {
        $ch = curl_init();
        
        if ($useApi && !empty(self::SCRAPER_API_KEY)) {
            $apiUrl = 'http://api.scraperapi.com/?api_key=' . self::SCRAPER_API_KEY . '&url=' . urlencode($url) . '&country_code=es';
            if ($renderJs) $apiUrl .= '&render=true';
            if ($premium) $apiUrl .= '&premium=true';
            
            curl_setopt($ch, CURLOPT_URL, $apiUrl);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60); 
        } else {
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15); 
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        
        $html = curl_exec($ch);
        curl_close($ch);
        return $html;
    }

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
            }
        }
        return false;
    }

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