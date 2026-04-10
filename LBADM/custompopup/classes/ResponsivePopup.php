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

class ResponsivePopup extends ObjectModel
{
    public $id;
    public $id_configuration;
    public $id_shop;
    public $id_lang;
    public $content;

    public static $definition = array(
        'table' => 'responsive_popup',
        'primary' => 'id_configuration',
        'fields' => array(
            'id_shop' => array('type' => self::TYPE_NOTHING),
            'id_lang' => array('type' => self::TYPE_NOTHING),
            'content' => array('type' => self::TYPE_HTML),
        ),
    );
}
