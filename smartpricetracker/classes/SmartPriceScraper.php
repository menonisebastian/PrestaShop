<?php
/**
 * Clase estática para realizar la extracción (Scraping) avanzada y multinivel
 */

class SmartPriceScraper
{
    /**
     * Obtiene el precio de una URL usando múltiples estrategias de SEO, DOM y Regex
     * @param string $url URL del competidor
     * @return float|bool Retorna el precio o false si falla
     */
    public static function getCompetitorPrice($url)
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $host = parse_url($url, PHP_URL_HOST);

        // Intento 1: Como usuario normal con cabeceras estrictas
        $html = self::fetchUrl($url, $host, false);

        // Detectar si hemos sido bloqueados por un WAF (Cloudflare, Datadome, Captcha)
        $isBlocked = empty($html) 
            || strpos($html, 'Cloudflare') !== false 
            || strpos($html, 'datadome') !== false 
            || strpos($html, 'captcha') !== false
            || strpos($html, 'Robot Check') !== false;

        // Intento 2: Si nos bloquean, reintentamos disfrazados de Googlebot
        if ($isBlocked) {
            $html = self::fetchUrl($url, $host, true);
        }

        if (empty($html)) {
            return false;
        }

        $dom = new DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new DOMXPath($dom);

        // --- ESTRATEGIA 1: JSON-LD (Schema.org) ---
        $scripts = $xpath->query('//script[@type="application/ld+json"]');
        foreach ($scripts as $script) {
            $data = json_decode($script->nodeValue, true);
            if (!$data) continue;
            
            if (isset($data['@graph'])) {
                $data = $data['@graph'];
            }

            if (is_array($data)) {
                $price = self::extractPriceFromSchema($data);
                if ($price !== false) return $price;
            } else {
                $price = self::extractPriceFromSchema([$data]);
                if ($price !== false) return $price;
            }
        }

        // --- ESTRATEGIA 2: Meta Tags Open Graph (Facebook / Pinterest) ---
        $ogPrice = $xpath->query('//meta[@property="product:price:amount"]/@content | //meta[@name="twitter:data1"]/@content');
        if ($ogPrice->length > 0) {
            $priceStr = $ogPrice->item(0)->nodeValue;
            $cleanPrice = self::cleanPriceString($priceStr);
            if ($cleanPrice !== false) return $cleanPrice;
        }

        // --- ESTRATEGIA 3: Microdatos HTML Clásicos (itemprop="price") ---
        $microdata = $xpath->query('//*[@itemprop="price"]');
        if ($microdata->length > 0) {
            $priceStr = $microdata->item(0)->getAttribute('content');
            if (empty($priceStr)) {
                $priceStr = $microdata->item(0)->nodeValue;
            }
            $cleanPrice = self::cleanPriceString($priceStr);
            if ($cleanPrice !== false) return $cleanPrice;
        }

        // --- ESTRATEGIA 4: Atributos HTML "data-price" (Muy común en SPAs y catálogos) ---
        $dataPrices = $xpath->query('//*[@data-price]/@data-price | //*[@data-product-price]/@data-product-price | //*[@data-baseprice]/@data-baseprice');
        if ($dataPrices->length > 0) {
            $priceStr = $dataPrices->item(0)->nodeValue;
            $cleanPrice = self::cleanPriceString($priceStr);
            if ($cleanPrice !== false && $cleanPrice > 0) return $cleanPrice;
        }

        // --- ESTRATEGIA 5: Casos Específicos (Amazon, PcComponentes, Madrid HiFi) ---
        
        // Amazon
        if (strpos($host, 'amazon') !== false) {
            $amazonPrice = $xpath->query('//span[contains(@class, "a-price")]/span[contains(@class, "a-offscreen")]');
            if ($amazonPrice->length > 0) {
                $priceStr = $amazonPrice->item(0)->nodeValue;
                $cleanPrice = self::cleanPriceString($priceStr);
                if ($cleanPrice !== false) return $cleanPrice;
            }
        }

        // Madrid HiFi
        if (strpos($host, 'madridhifi') !== false) {
            $mhPrice = $xpath->query('//*[@id="our_price_display"] | //*[contains(@class, "current-price")] | //*[contains(@class, "price")]/span[@class="value"]');
            if ($mhPrice->length > 0) {
                $priceStr = $mhPrice->item(0)->nodeValue;
                $cleanPrice = self::cleanPriceString($priceStr);
                if ($cleanPrice !== false) return $cleanPrice;
            }
        }

        // --- ESTRATEGIA 6: Regex Crudo sobre el HTML (Salvavidas para JS / React / Vue) ---
        // Extrae el precio si está dentro de objetos Javascript en línea (Ej: window.__INITIAL_STATE__)
        if (preg_match('/"price"\s*:\s*"?([0-9]+[\.,][0-9]{2})"?/i', $html, $matches) || 
            preg_match('/"priceAmount"\s*:\s*"?([0-9]+[\.,][0-9]{2})"?/i', $html, $matches)) {
            $cleanPrice = self::cleanPriceString($matches[1]);
            if ($cleanPrice !== false) return $cleanPrice;
        }

        // --- ESTRATEGIA 7: Clases Genéricas de Front-end ---
        $commonClasses = ['current-price', 'product-price', 'price', 'precio-main'];
        foreach ($commonClasses as $class) {
            $elements = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " ' . $class . ' ")]');
            foreach ($elements as $el) {
                $priceStr = $el->nodeValue;
                if (strlen(trim($priceStr)) < 20 && preg_match('/[0-9]/', $priceStr)) {
                    $cleanPrice = self::cleanPriceString($priceStr);
                    if ($cleanPrice !== false && $cleanPrice > 0) return $cleanPrice;
                }
            }
        }

        return false; // Si todas las estrategias fallan (Bloqueo irresoluble sin API de terceros)
    }

    /**
     * Realiza la petición cURL aislando la configuración de cabeceras
     */
    private static function fetchUrl($url, $host, $asGooglebot = false)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 12); 
        curl_setopt($ch, CURLOPT_ENCODING, ''); 
        curl_setopt($ch, CURLOPT_COOKIEFILE, ""); 

        if ($asGooglebot) {
            // Cabeceras simulando ser el rastreador oficial de Google (Bypass de WAF)
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Connection: keep-alive'
            ]);
        } else {
            // Cabeceras de usuario humano normal
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authority: ' . $host,
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                'Accept-Language: es-ES,es;q=0.9,en;q=0.8',
                'Cache-Control: max-age=0',
                'Referer: https://www.google.es/',
                'Sec-Ch-Ua: "Chromium";v="122", "Not(A:Brand";v="24", "Google Chrome";v="122"',
                'Sec-Ch-Ua-Mobile: ?0',
                'Sec-Ch-Ua-Platform: "Windows"',
                'Sec-Fetch-Dest: document',
                'Sec-Fetch-Mode: navigate',
                'Sec-Fetch-Site: cross-site',
                'Upgrade-Insecure-Requests: 1'
            ]);
        }
        
        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400 && !$asGooglebot) {
            return false; // Forzar el reintento de Googlebot
        }

        return $html;
    }

    /**
     * Función recursiva auxiliar para buscar la propiedad 'price' en JSON-LD
     */
    private static function extractPriceFromSchema($schemaArray)
    {
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
     * Limpia la cadena del precio (maneja comas, puntos y miles europeos/americanos)
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