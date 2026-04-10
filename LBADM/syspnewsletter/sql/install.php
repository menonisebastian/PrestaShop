<?php
/**
 * SQL Install
 *
 * @author    SYSPROVIDER S.L.
 * @copyright 2024 SYSPROVIDER S.L.
 */

$sql = [];

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'syspnl_subscribers` (
    `id_subscriber` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `email`         varchar(255) NOT NULL,
    `id_shop`       int(11) UNSIGNED NOT NULL DEFAULT 1,
    `date_add`      datetime NOT NULL,
    PRIMARY KEY (`id_subscriber`),
    UNIQUE KEY `uq_email_shop` (`email`, `id_shop`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4;';

foreach ($sql as $query) {
    if (Db::getInstance()->execute($query) == false) {
        return false;
    }
}
