<?php
/**
 * SQL Installation Script
 *
 * @author    SYSPROVIDER S.L.
 * @copyright 2024 SYSPROVIDER S.L.
 */

$sql = array();

// Si en el futuro necesitas una tabla para estadísticas o múltiples popups, descomenta esto:
/*
$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'sysppopup_stats` (
    `id_popup_stat` int(11) NOT NULL AUTO_INCREMENT,
    `date_add` datetime NOT NULL,
    `id_customer` int(11) DEFAULT NULL,
    `action` varchar(50) NOT NULL,
    PRIMARY KEY (`id_popup_stat`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';
*/

foreach ($sql as $query) {
    if (Db::getInstance()->execute($query) == false) {
        return false;
    }
}
