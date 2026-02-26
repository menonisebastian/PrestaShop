<?php
/**
 * Clase para Scraping con Auto-Limpiador de Títulos y Ancla de Alta Precisión
 */

class SmartPriceScraper
{
    const SCRAPER_API_KEY = 'd71349b190a08bfadcd73abc846ce400'; 

    public static function searchCompetitorsByTitle($search_term)
    {
        if (empty(trim($search_term))) {
            return false;
        }

        // =====================================
        // AUTO-LIMPIADOR DE BÚSQUEDAS
        // =====================================
        $clean_term = $search_term;
        // 1. Quitar referencias (Ref: 123, SKU: 123)
        $clean_term = preg_replace('/\b(ref|sku|ean|id)\s*[:\-]?\s*[a-zA-Z0-9\-\_]+\b/i', '', $clean_term);
        // 2. Quitar todo lo que esté entre paréntesis o corchetes
        $clean_term = preg_replace('/[\(\[].*?[\)\]]/', '', $clean_term);
        // 3. Quitar palabras comerciales inútiles para Google Shopping
        $clean_term = preg_replace('/\b(comprar|barato|oferta|nuevo|envío|gratis|descuento|garantía|pack)\b/i', '', $clean_term);
        // 4. Limpiar espacios extra
        $clean_term = trim(preg_replace('/\s+/', ' ', $clean_term));

        // Si al limpiar nos quedamos sin texto, usamos el original
        if (empty($clean_term) || strlen($clean_term) < 3) {
            $clean_term = $search_term;
        }

        PrestaShopLogger::addLog('Smart Price Tracker: Buscando original "' . $search_term . '" -> Limpio: "' . $clean_term . '"', 1, null, 'SmartPriceTracker');
        
        $googleShoppingUrl = 'https://www.google.es/search?q=' . urlencode($clean_term) . '&tbm=shop&hl=es&gl=es';
        
        // 1. Conexión Directa
        $html = self::fetchUrlForSearch($googleShoppingUrl, false);
        if (!empty($html) && !self::isGoogleBlocked($html)) {
            $competitors = self::parseGoogleHeuristic($html, $clean_term);
            if (count($competitors) > 0) return $competitors;
        }
        
        // 2. Conexión API
        if (!empty(self::SCRAPER_API_KEY)) {
            $html = self::fetchUrlForSearch($googleShoppingUrl, true);
            if (!empty($html) && !self::isGoogleBlocked($html)) {
                $competitors = self::parseGoogleHeuristic($html, $clean_term);
                if (count($competitors) > 0) return $competitors;
            }
        }
        
        // 3. Fallback Búsqueda Normal
        $googleNormalUrl = 'https://www.google.es/search?q=' . urlencode($clean_term . ' precio') . '&hl=es&gl=es';
        $html = self::fetchUrlForSearch($googleNormalUrl, false);
        if (!empty($html) && !self::isGoogleBlocked($html)) {
            $competitors = self::parseGoogleHeuristic($html, $clean_term);
            if (count($competitors) > 0) return $competitors;
        }

        return false;
    }

    private static function parseGoogleHeuristic($html, $search_term)
    {
        $rawCompetitors = [];

        $dom = new DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new DOMXPath($dom);
        
        $priceNodes = $xpath->query('//text()[contains(., "€") or contains(., "EUR")]');
        
        foreach ($priceNodes as $node) {
            $priceText = $node->nodeValue;
            
            if (preg_match('/([0-9]{1,3}(?:\.[0-9]{3})*(?:,[0-9]{2})?)/', $priceText, $match)) {
                $price = self::cleanPriceString($match[1]);
                
                if ($price > 5) {
                    
                    $seller = '';
                    $url = '#';
                    
                    // 1. Encontrar el contenedor
                    $container = $node->parentNode;
                    $parent = $node->parentNode;
                    for ($i = 0; $i < 6; $i++) {
                        if ($parent && $parent->parentNode) {
                            $parent = $parent->parentNode;
                            if (in_array($parent->nodeName, ['div', 'tr', 'li'])) {
                                $container = $parent;
                            }
                        }
                    }

                    // 2. BUSCAR ENLACE
                    $links = [];
                    foreach ($xpath->query('.//a[@href]', $container) as $l) $links[] = $l;
                    $p = $node->parentNode;
                    for ($j = 0; $j < 8; $j++) {
                        if ($p && $p->nodeName === 'a' && $p->hasAttribute('href')) $links[] = $p;
                        if ($p) $p = $p->parentNode;
                    }

                    foreach ($links as $link) {
                        $href = $link->getAttribute('href');
                        
                        if (strpos($href, 'support.google') !== false || strpos($href, 'policies') !== false || strpos($href, 'search?') !== false) continue;
                        
                        $realUrl = $href;
                        if (preg_match('/[?&](url|q|adurl)=([^&]+)/i', $href, $uMatch)) {
                            $realUrl = urldecode($uMatch[2]);
                        }
                        
                        if (filter_var($realUrl, FILTER_VALIDATE_URL) && strpos($realUrl, 'google.com/search') === false) {
                            $url = $realUrl;
                            break;
                        } elseif (strpos($realUrl, '/') === 0) {
                            $url = 'https://www.google.es' . $realUrl;
                            break;
                        }
                    }

                    // 3. BUSCAR VENDEDOR
                    if ($url !== '#' && strpos($url, 'google.') === false) {
                        $host = parse_url($url, PHP_URL_HOST);
                        if ($host) {
                            $seller = self::formatSellerName($host);
                        }
                    }

                    if (empty($seller)) {
                        $merchantNodes = $xpath->query('.//*[@data-merchant]', $container);
                        if ($merchantNodes->length > 0) {
                            $seller = trim($merchantNodes->item(0)->getAttribute('data-merchant'));
                        }
                    }

                    if (empty($seller)) {
                        $texts = [];
                        $textNodes = $xpath->query('.//text()', $container);
                        foreach ($textNodes as $tn) {
                            $t = trim($tn->nodeValue);
                            $len = mb_strlen($t, 'UTF-8');
                            if ($len >= 3 && $len <= 25) {
                                $texts[] = $t;
                            }
                        }

                        $bad_words = [
                            'envío', 'gratis', 'comprar', 'usado', 'reacondicionado', 
                            'opiniones', 'desde', 'oferta', 'precio', 'más', 'mas', 
                            'comparar', 'detalles', 'resultados', 'búsqueda', 'busqueda', 
                            'mín', 'máx', 'portátiles', 'filtros', 'ordenar', 'opciones',
                            'buscar', 'conjunto', 'nuevo', 'cesta', 'carrito', 'añadir', 'ver', 'tienda',
                            'segunda mano', 'seller', 'stock', 'entrega', 'días', 'dias',
                            'iva', 'incluido', 'impuestos', 'pack', 'set', 'kit', 'color'
                        ];

                        $normalized_search = str_replace('-', ' ', $search_term);
                        $searchWordsLower = array_filter(explode(' ', mb_strtolower($normalized_search, 'UTF-8')), function($w) { 
                            return mb_strlen($w, 'UTF-8') > 2; 
                        });

                        foreach ($texts as $t) {
                            $tNormalized = str_replace('-', ' ', $t);
                            $tLower = mb_strtolower($tNormalized, 'UTF-8');
                            $isValid = true;
                            
                            $wordsArray = array_filter(explode(' ', $tNormalized));
                            $wordsCount = count($wordsArray);

                            if (preg_match('/[0-9€\$\%\+]/', $t) || $wordsCount > 3) {
                                $isValid = false;
                            } else {
                                foreach ($bad_words as $bad) {
                                    if (strpos($tLower, $bad) !== false) {
                                        $isValid = false;
                                        break;
                                    }
                                }
                                if ($isValid) {
                                    foreach ($searchWordsLower as $sw) {
                                        if (strpos($tLower, $sw) !== false) {
                                            $isValid = false;
                                            break;
                                        }
                                    }
                                }
                            }

                            if ($isValid) {
                                $seller = trim($t); 
                                break;
                            }
                        }
                    }

                    // 4. FILTRO ESTRICTO: TOLERANCIA CERO DOMINIOS
                    $seller = trim($seller);
                    $isValidDomain = false;
                    
                    if (preg_match('/([a-zA-Z0-9\-\_]+\.(es|com|net|eu|it|fr|pt|de|uk|shop|store|org|info))(\s|$)/i', $seller, $m)) {
                        $isValidDomain = true;
                        $seller = ucfirst(strtolower($m[1]));
                    } 
                    elseif ($url !== '#' && strpos($url, 'google.') === false) {
                        $host = parse_url($url, PHP_URL_HOST);
                        if ($host && preg_match('/\.(es|com|net|eu|it|fr|pt|de|uk|shop|store|org|info)$/i', $host)) {
                            $isValidDomain = true;
                            $seller = self::formatSellerName($host);
                        }
                    }

                    if ($isValidDomain) {
                        $rawCompetitors[] = [
                            'seller' => $seller,
                            'price' => $price,
                            'url' => $url,
                            'title' => ''
                        ];
                    }
                }
            }
        }
        
        // =====================================
        // 5. CRIBADO FINAL: ANCLA DE ALTA PRECISIÓN
        // =====================================
        if (empty($rawCompetitors)) return [];

        $prices = array_column($rawCompetitors, 'price');
        sort($prices);
        
        // Para evitar que las fundas baratas arruinen el cálculo, 
        // buscamos el precio medio SOLO en la mitad más cara de los resultados.
        $halfIndex = floor(count($prices) / 2);
        $upperHalf = array_slice($prices, $halfIndex);
        
        if (count($upperHalf) > 0) {
            $anchorPrice = $upperHalf[floor(count($upperHalf) / 2)];
        } else {
            $anchorPrice = $prices[0];
        }

        $finalCompetitors = [];
        $sellerBestDiff = [];

        foreach ($rawCompetitors as $comp) {
            // Descartamos todo lo que cueste menos del 40% del Ancla Real
            if ($comp['price'] < ($anchorPrice * 0.40) || $comp['price'] > ($anchorPrice * 1.80)) {
                continue;
            }

            $sellerKey = mb_strtolower($comp['seller'], 'UTF-8');
            $diffToAnchor = abs($comp['price'] - $anchorPrice);

            // Si hay varios precios para una tienda, nos quedamos con el que más se parezca al Ancla Real
            if (!isset($finalCompetitors[$sellerKey])) {
                $finalCompetitors[$sellerKey] = $comp;
                $sellerBestDiff[$sellerKey] = $diffToAnchor;
            } else {
                if ($diffToAnchor < $sellerBestDiff[$sellerKey]) {
                    $finalCompetitors[$sellerKey] = $comp;
                    $sellerBestDiff[$sellerKey] = $diffToAnchor;
                }
            }
        }
        
        $competitors = array_values($finalCompetitors);
        usort($competitors, function($a, $b) {
            return $a['price'] <=> $b['price'];
        });

        return array_slice($competitors, 0, 15);
    }

    private static function formatSellerName($host)
    {
        $host = strtolower(trim($host));
        $host = preg_replace('/^www\./', '', $host);
        return ucfirst($host);
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

    private static function fetchUrlForSearch($url, $useApi = false)
    {
        $ch = curl_init();
        if ($useApi && !empty(self::SCRAPER_API_KEY)) {
            $apiUrl = 'http://api.scraperapi.com/?api_key=' . self::SCRAPER_API_KEY . '&url=' . urlencode($url) . '&country_code=es';
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
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36');
        
        $html = curl_exec($ch);
        curl_close($ch);
        return $html;
    }

    private static function isGoogleBlocked($html)
    {
        if (empty(trim($html))) return true;
        $htmlLower = strtolower($html);
        return (strpos($htmlLower, 'captcha') !== false || strpos($htmlLower, 'tráfico inusual') !== false);
    }
}