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

require_once('../../../config/config.inc.php');

$id_lang = Context::getContext()->language->id;

echo Db::getInstance()->getValue('SELECT `content` FROM '._DB_PREFIX_.'responsive_popup 
WHERE id_lang='.(int)$id_lang.'');
