<?php
/**
 * Support contact : soporte@corecreativo.es.
 *
 * NOTICE OF LICENSE
 *
 * This source file is the property of Corecreativo
 * that is bundled with this package.
 * It is also available through the world-wide-web at this URL:
 * https://www.corecreativo.es/
 *
 * @category  front-end
 * @author    Corecreativo (http://www.corecreativo.es/)
 * @copyright 2016-2022 Corecreativo and contributors
 */
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
header("Last-Modified: ".gmdate("D, d M Y H:i:s")." GMT");
						
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
						
header("Location: ../");
exit;