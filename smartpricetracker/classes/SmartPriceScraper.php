<?php
/**
 * Clase estática para realizar la extracción (Scraping) avanzada y multinivel
 */

class SmartPriceScraper
{
    // ========================================================================
    // MODO PROFESIONAL: API Key de ScraperAPI.com (Gratis 1000/mes)
    // ========================================================================
    const SCRAPER_API_KEY = 'd71349b190a08bfadcd73abc846ce400'; 

    public static function getCompetitorPrice($url)
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        @set_time_limit(120);

        $host = parse_url($url, PHP_URL_HOST);
        $price = false;

        // ========================================================================
        // FASE 1: INTENTOS LOCALES (0 CRÉDITOS - MÁXIMA VELOCIDAD)
        // ========================================================================
        $html = self::fetchUrl($url, $host, 0, false, false, false);
        $price = self::parsePriceFromHtml($html, $host);

        if ($price === false && self::isBlocked($html)) {
            $html = self::fetchUrl($url, $host, 1, false, false, false); // Facebook Bot
            $price = self::parsePriceFromHtml($html, $host);
            
            if ($price === false && self::isBlocked($html)) {
                $html = self::fetchUrl($url, $host, 2, false, false, false); // Googlebot
                $price = self::parsePriceFromHtml($html, $host);
            }
        }

        // ========================================================================
        // FASE 2: EL TRUCO DE GOOGLE SEARCH LOCAL (0 CRÉDITOS)
        // Buscamos la URL en Google para leer el precio del "Rich Snippet" indexado.
        // ========================================================================
        if ($price === false) {
            // Buscamos la URL exacta entre comillas en Google España
            $googleUrl = 'https://www.google.es/search?q=' . urlencode('"' . $url . '"') . '&hl=es&gl=es';
            $googleHtml = self::fetchUrl($googleUrl, 'www.google.es', 0, false, false, false);
            
            if (!empty($googleHtml) && !self::isGoogleBlocked($googleHtml)) {
                $price = self::parsePriceFromGoogle($googleHtml);
            }
        }

        // ========================================================================
        // FASE 3: GOOGLE SEARCH VÍA API BÁSICA (1 CRÉDITO)
        // Si Google bloqueó nuestro servidor, usamos la API barata para ver el snippet
        // ========================================================================
        if ($price === false && !empty(self::SCRAPER_API_KEY) && isset($googleHtml) && self::isGoogleBlocked($googleHtml)) {
            $googleHtmlApi = self::fetchUrl($googleUrl, 'www.google.es', 0, true, false, false);
            if (!empty($googleHtmlApi) && !self::isGoogleBlocked($googleHtmlApi)) {
                $price = self::parsePriceFromGoogle($googleHtmlApi);
            }
        }

        // ========================================================================
        // FASE 4: COMPETIDOR VÍA API BÁSICA (1 CRÉDITO)
        // Leemos la web original pero con proxies de centro de datos baratos
        // ========================================================================
        if ($price === false && !empty(self::SCRAPER_API_KEY)) {
            $htmlApi = self::fetchUrl($url, $host, 0, true, false, false);
            if (!empty($htmlApi) && !self::isBlocked($htmlApi)) {
                $price = self::parsePriceFromHtml($htmlApi, $host);
            }
        }

        // ========================================================================
        // FASE 5: COMPETIDOR VÍA API PREMIUM + RENDER JS (10 - 25 CRÉDITOS)
        // El último recurso. Solo si no está en Google y Cloudflare es implacable.
        // ========================================================================
        if ($price === false && !empty(self::SCRAPER_API_KEY)) {
            $needsPremium = (strpos($host, 'madridhifi') !== false || strpos($host, 'pccomponentes') !== false || strpos($host, 'amazon') !== false);
            if ($needsPremium) {
                $htmlApiRender = self::fetchUrl($url, $host, 0, true, true, true);
                if (!empty($htmlApiRender) && !self::isBlocked($htmlApiRender)) {
                    $price = self::parsePriceFromHtml($htmlApiRender, $host);
                }
            }
        }

        return $price;
    }

    /**
     * Extrae el precio de los resultados de búsqueda de Google (Rich Snippets / Shopping)
     */
    private static function parsePriceFromGoogle($html)
    {
        // Aislar la zona de resultados para ignorar precios en menús u otras partes
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

        // Busca patrones típicos de precio en el snippet (Ej: 599,00 €)
        if (preg_match_all('/([0-9]{1,3}(?:\.[0-9]{3})*(?:,[0-9]{2})?)(?:\s|&nbsp;)*(?:€|&euro;|EUR)/ui', $searchBlock, $matches)) {
            foreach ($matches[1] as $match) {
                $clean = self::cleanPriceString($match);
                // Evitamos coger precios minúsculos que suelen ser de "Gastos de envío 3,99 €"
                if ($clean > 5) { 
                    return $clean; // Devuelve el primer precio válido encontrado en el resultado exacto
                }
            }
        }
        return false;
    }

    /**
     * Estrategias de lectura del HTML (Prioridad absoluta al código fuente limpio)
     */
    private static function parsePriceFromHtml($html, $host)
    {
        if (empty(trim($html))) return false;

        $dom = new DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new DOMXPath($dom);

        // --- ESTRATEGIA 1: EXTRACCIÓN DEL NÚCLEO REACT / NEXT.JS ---
        if (strpos($host, 'pccomponentes') !== false) {
            if (preg_match('/"price"\s*:\s*"?([0-9]+(?:[\.,][0-9]{1,2})?)"?/i', $html, $matches) ||
                preg_match('/"priceAmount"\s*:\s*"?([0-9]+(?:[\.,][0-9]{1,2})?)"?/i', $html, $matches)) {
                $clean = self::cleanPriceString($matches[1]);
                if ($clean > 0) return $clean;
            }
            $pccPrice = $xpath->query('//*[@id="precio-main"] | //*[@data-e2e="price-card"]//span | //div[contains(@class, "buy-box")]//*[contains(@class, "price")]');
            if ($pccPrice->length > 0) return self::cleanPriceString($pccPrice->item(0)->nodeValue);
        }

        if (strpos($host, 'madridhifi') !== false) {
            $nextData = $xpath->query('//script[@id="__NEXT_DATA__"]');
            if ($nextData->length > 0) {
                if (preg_match('/"price"\s*:\s*"?([0-9]+(?:[\.,][0-9]{1,2})?)"?/i', $nextData->item(0)->nodeValue, $matches)) {
                    $clean = self::cleanPriceString($matches[1]);
                    if ($clean > 0) return $clean;
                }
            }
            $mhPrice = $xpath->query('//*[@id="our_price_display"] | //*[contains(@class, "product-price")] | //div[contains(@class, "price")]/span[@class="value"]');
            if ($mhPrice->length > 0) return self::cleanPriceString($mhPrice->item(0)->nodeValue);
        }

        if (strpos($host, 'amazon') !== false) {
            $amazonPrice = $xpath->query('//span[contains(@class, "a-price")]/span[contains(@class, "a-offscreen")]');
            if ($amazonPrice->length > 0) return self::cleanPriceString($amazonPrice->item(0)->nodeValue);
        }

        // --- ESTRATEGIA 2: JSON-LD (Schema.org) ---
        if (preg_match_all('/<script[^>]*type="application\/ld\+json"[^>]*>(.*?)<\/script>/is', $html, $matches)) {
            foreach ($matches[1] as $jsonStr) {
                $data = @json_decode(trim($jsonStr), true);
                if (!$data) continue;
                $dataToProcess = isset($data['@graph']) ? $data['@graph'] : [$data];
                $price = self::extractPriceFromSchema($dataToProcess);
                if ($price !== false && $price > 0) return $price;
            }
        }

        // --- ESTRATEGIA 3: Meta Tags Open Graph ---
        $ogPrice = $xpath->query('//meta[@property="product:price:amount"]/@content | //meta[@name="twitter:data1"]/@content');
        if ($ogPrice->length > 0) {
            $cleanPrice = self::cleanPriceString($ogPrice->item(0)->nodeValue);
            if ($cleanPrice !== false && $cleanPrice > 0) return $cleanPrice;
        }

        // --- ESTRATEGIA 4: Atributos y Microdatos HTML ---
        $microdata = $xpath->query('//*[@itemprop="price"]/@content | //*[@data-price]/@data-price');
        if ($microdata->length > 0) {
            $cleanPrice = self::cleanPriceString($microdata->item(0)->nodeValue);
            if ($cleanPrice !== false && $cleanPrice > 0) return $cleanPrice;
        }

        // --- ESTRATEGIA 5: Regex Crudo y Visual ---
        if (preg_match('/([0-9]{1,3}(?:\.[0-9]{3})*(?:,[0-9]{2})?)\s*(?:€|&euro;|EUR)/ui', $html, $matches)) {
            $cleanPrice = self::cleanPriceString($matches[1]);
            if ($cleanPrice !== false && $cleanPrice > 0) return $cleanPrice;
        }

        return false;
    }

    /**
     * Evalúa si Google nos ha lanzado un Captcha
     */
    private static function isGoogleBlocked($html)
    {
        if (empty(trim($html))) return true;
        $htmlLower = strtolower($html);
        return (strpos($htmlLower, 'id="captcha"') !== false || 
                strpos($htmlLower, 'recaptcha') !== false || 
                strpos($htmlLower, 'tráfico inusual') !== false || 
                strpos($htmlLower, 'unusual traffic') !== false);
    }

    /**
     * Evalúa si el HTML devuelto es una página de bloqueo general
     */
    private static function isBlocked($html)
    {
        if (empty(trim($html)) || strlen($html) < 1000) return true; 
        $htmlLower = strtolower($html);
        return (
            (strpos($htmlLower, 'cloudflare') !== false && strpos($htmlLower, 'ray id') !== false) ||
            strpos($htmlLower, 'datadome') !== false ||
            strpos($htmlLower, 'enable javascript and cookies') !== false ||
            strpos($htmlLower, 'robot check') !== false
        );
    }

    /**
     * Sistema de peticiones rotativo: Soporta Local, API Básica y API Premium
     */
    private static function fetchUrl($url, $host, $botLevel = 0, $useApi = false, $renderJs = false, $premium = false)
    {
        $ch = curl_init();
        
        if ($useApi && !empty(self::SCRAPER_API_KEY)) {
            $apiUrl = 'http://api.scraperapi.com/?api_key=' . self::SCRAPER_API_KEY . '&url=' . urlencode($url) . '&country_code=es';
            
            if ($renderJs) {
                $apiUrl .= '&render=true';
            }
            if ($premium) {
                $apiUrl .= '&premium=true';
            }
            
            curl_setopt($ch, CURLOPT_URL, $apiUrl);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60); 
        } else {
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15); 
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_ENCODING, ''); 
        curl_setopt($ch, CURLOPT_COOKIEFILE, ""); 
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        if ($botLevel === 1) {
            curl_setopt($ch, CURLOPT_USERAGENT, 'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)');
        } elseif ($botLevel === 2) {
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)');
        } else {
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authority: ' . $host,
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                'Accept-Language: es-ES,es;q=0.9,en;q=0.8',
                'Cache-Control: max-age=0',
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
                } elseif ($type === 'Offer' && isset($item['price'])) {
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