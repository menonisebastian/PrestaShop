<?php
/**
 * SQL Uninstallation Script
 *
 * @author    SYSPROVIDER S.L.
 * @copyright 2024 SYSPROVIDER S.L.
 */

$sql = array();

// Si has creado tablas en install.php, descoméntalas aquí:
/*
$sql[] = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'sysppopup_stats`';
*/

foreach ($sql as $query) {
    if (Db::getInstance()->execute($query) == false) {
        return false;
    }
}
