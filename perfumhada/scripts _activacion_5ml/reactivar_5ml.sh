#!/bin/bash
DB_USER="perfumhada_syspre"
DB_PASS='0RDK&6mBq[URw0z$'
DB_NAME="perfumhada_syspre"

echo ">>> Restaurando stock original desde backup..."

mariadb -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "
-- Restaurar quantity y out_of_stock desde el backup
UPDATE ps_stock_available sa
JOIN ps_stock_backup_5ml bk ON sa.id_stock_available = bk.id_stock_available
SET sa.quantity = bk.quantity,
    sa.out_of_stock = bk.out_of_stock;

-- Reactivar producto independiente 527
UPDATE ps_product SET available_for_order = 1 WHERE id_product = 527;
UPDATE ps_product_shop SET available_for_order = 1 WHERE id_product = 527;
"

echo ">>> Verificando..."
mariadb -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "
SELECT sa.id_product_attribute, sa.quantity, sa.out_of_stock
FROM ps_stock_available sa
JOIN ps_product_attribute_combination pac ON sa.id_product_attribute = pac.id_product_attribute
JOIN ps_category_product cp ON sa.id_product = cp.id_product
WHERE pac.id_attribute = 6
AND cp.id_category IN (82, 114)
AND sa.quantity > 0
LIMIT 5;
"

echo ">>> Listo. Stock original restaurado."
